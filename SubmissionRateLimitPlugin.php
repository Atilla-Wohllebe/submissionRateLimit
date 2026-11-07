<?php

/**
 * @file plugins/generic/submissionRateLimit/SubmissionRateLimitPlugin.php
 *
 * Copyright (c) 2026 Atilla Wohllebe
 * Distributed under the GNU GPL v3. For full terms see the file LICENSE.
 *
 * @class SubmissionRateLimitPlugin
 *
 * @brief Limits how many submissions a user account can start and complete
 *   within configurable time windows (e.g. 1 per 15 minutes, 2 per hour, 5 per day).
 *   Starting a submission counts even if it is never completed.
 */

namespace APP\plugins\generic\submissionRateLimit;

use APP\core\Application;
use APP\plugins\generic\submissionRateLimit\classes\Constants;
use APP\plugins\generic\submissionRateLimit\classes\RateLimiter;
use Illuminate\Support\Facades\Event;
use PKP\context\Context;
use PKP\core\APIRouter;
use PKP\linkAction\LinkAction;
use PKP\linkAction\request\VueModal;
use PKP\observers\events\SubmissionSubmitted;
use PKP\plugins\GenericPlugin;
use PKP\plugins\Hook;
use PKP\plugins\PluginSettingsController;
use PKP\security\Role;
use PKP\submission\PKPSubmission;

class SubmissionRateLimitPlugin extends GenericPlugin
{
    private ?SubmissionRateLimitSettingsController $controller = null;

    /** @copydoc GenericPlugin::register() */
    public function register($category, $path, $mainContextId = null): bool
    {
        $success = parent::register($category, $path, $mainContextId);

        if (Application::isUnderMaintenance()) {
            return $success;
        }

        if ($success && $this->getEnabled($mainContextId)) {
            // Block creating a new submission when a limit is reached (API and wizard)
            Hook::add('Submission::validate', $this->validateNewSubmission(...));

            // Show a notice instead of the "Start a new submission" form when a limit is reached
            Hook::add('TemplateManager::display', $this->replaceStartPage(...));

            // Log every newly created (still incomplete) submission
            Hook::add('Submission::add', $this->recordStartedSubmission(...));

            // Block the final submit step when a limit is reached
            Hook::add('Submission::validateSubmit', $this->validateSubmit(...));

            // Show the reason in the review step of the submission wizard
            Hook::add('Template::SubmissionWizard::Section', $this->addWizardNotice(...));

            // Log every completed submission
            Event::listen(SubmissionSubmitted::class, $this->recordSubmission(...));

            // Settings API (available from OJS 3.5.0-4 on)
            if (class_exists(PluginSettingsController::class)) {
                $this->controller = new SubmissionRateLimitSettingsController($this);
                Hook::add('APIHandler::endpoints::plugin', function (string $hookName, APIRouter $apiRouter): bool {
                    $apiRouter->registerPluginApiControllers([$this->controller]);
                    return Hook::CONTINUE;
                });
            }
        }

        return $success;
    }

    /** @copydoc Plugin::getInstallMigration() */
    public function getInstallMigration()
    {
        return new SubmissionRateLimitSchemaMigration();
    }

    public function getDisplayName(): string
    {
        return __('plugins.generic.submissionRateLimit.displayName');
    }

    public function getDescription(): string
    {
        return __('plugins.generic.submissionRateLimit.description');
    }

    /**
     * Whether journal managers, editors, assistants and site admins are exempt (default: yes)
     */
    public function isEditorialStaffExempt(int $contextId): bool
    {
        $value = $this->getSetting($contextId, Constants::EXEMPT_EDITORS);
        return $value === null ? true : (bool) $value;
    }

    /**
     * Hook callback for Submission::validateSubmit
     *
     * @param array $args [&$errors, Submission $submission, Context $context]
     */
    public function validateSubmit(string $hookName, array $args): bool
    {
        $errors = &$args[0];
        /** @var PKPSubmission $submission */
        $submission = $args[1];
        /** @var Context $context */
        $context = $args[2];

        $user = Application::get()->getRequest()->getUser();
        if (!$user || !$context) {
            return Hook::CONTINUE;
        }

        // Do not interfere with submissions that are already submitted
        if (!$submission->getData('submissionProgress')) {
            return Hook::CONTINUE;
        }

        $contextId = (int) $context->getId();
        if ($this->isExempt($user, $contextId)) {
            return Hook::CONTINUE;
        }

        $message = $this->getLimitMessage($contextId, (int) $user->getId(), Constants::EVENT_SUBMITTED);
        if ($message) {
            $errors[Constants::ERROR_KEY] = $message;
        }

        return Hook::CONTINUE;
    }

    /**
     * Hook callback for Submission::validate
     *
     * Only acts when a new submission is about to be created ($submission is null).
     *
     * @param array $args [&$errors, ?Submission $submission, array $props, array $allowedLocales, string $primaryLocale]
     */
    public function validateNewSubmission(string $hookName, array $args): bool
    {
        $errors = &$args[0];
        $submission = $args[1];
        $props = $args[2];
        if ($submission !== null) {
            return Hook::CONTINUE;
        }

        $request = Application::get()->getRequest();
        $user = $request->getUser();
        $contextId = (int) ($props['contextId'] ?? $request->getContext()?->getId());
        if (!$user || !$contextId || $this->isExempt($user, $contextId)) {
            return Hook::CONTINUE;
        }

        $message = $this->getLimitMessage($contextId, (int) $user->getId(), Constants::EVENT_STARTED);
        if ($message) {
            // Plain key for API clients
            $errors[Constants::ERROR_KEY] = [$message];
            // Shown below the title field of the "Start a new submission" form
            if (!isset($errors['title'])) {
                $errors['title'] = [$message];
            }
        }

        return Hook::CONTINUE;
    }

    /**
     * Hook callback for TemplateManager::display
     *
     * Replaces the "Start a new submission" page with a notice when a limit is reached.
     *
     * @param array $args [$templateMgr, &$template, &$output]
     */
    public function replaceStartPage(string $hookName, array $args): bool
    {
        $templateMgr = $args[0];
        $template = &$args[1];
        if ($template !== 'submission/start.tpl') {
            return Hook::CONTINUE;
        }

        $request = Application::get()->getRequest();
        $user = $request->getUser();
        $context = $request->getContext();
        if (!$user || !$context || $this->isExempt($user, (int) $context->getId())) {
            return Hook::CONTINUE;
        }

        $message = $this->getLimitMessage((int) $context->getId(), (int) $user->getId(), Constants::EVENT_STARTED);
        if ($message) {
            $templateMgr->assign(Constants::TEMPLATE_MESSAGE, $message);
            $template = $this->getTemplateResource('startBlocked.tpl');
        }

        return Hook::CONTINUE;
    }

    /**
     * Hook callback for Submission::add: log a newly created submission
     *
     * @param array $args [Submission $submission]
     */
    public function recordStartedSubmission(string $hookName, array $args): bool
    {
        $submission = $args[0];
        $user = Application::get()->getRequest()->getUser();
        if ($user && $submission) {
            (new RateLimiter($this))->record(
                (int) $submission->getData('contextId'),
                (int) $user->getId(),
                (int) $submission->getId(),
                Constants::EVENT_STARTED
            );
        }
        return Hook::CONTINUE;
    }

    /**
     * Event listener: log a completed submission for the submitting user
     */
    public function recordSubmission(SubmissionSubmitted $event): void
    {
        $user = Application::get()->getRequest()->getUser();
        if (!$user) {
            return;
        }
        (new RateLimiter($this))->record(
            (int) $event->context->getId(),
            (int) $user->getId(),
            (int) $event->submission->getId(),
            Constants::EVENT_SUBMITTED
        );
    }

    /**
     * Hook callback for Template::SubmissionWizard::Section
     *
     * Adds a notice to the review section of the wizard. It is rendered by Vue
     * and only shown when the submit validation returned a rate limit error.
     *
     * @param array $args [&$params, $templateMgr, &$output]
     */
    public function addWizardNotice(string $hookName, array $args): bool
    {
        $output = &$args[2];
        $key = Constants::ERROR_KEY;
        $output .= '<notification v-if="section.type === \'review\' && errors.' . $key . '" type="warning" class="submissionWizard__review_errors">'
            . '{{ errors.' . $key . ' }}'
            . '</notification>';
        return Hook::CONTINUE;
    }

    /**
     * Add the settings action to the plugin's entry in the plugin list
     *
     * @param \APP\core\Request $request
     * @param array $actionArgs
     */
    public function getActions($request, $actionArgs): array
    {
        $actions = parent::getActions($request, $actionArgs);

        $context = $request->getContext();
        if (!$this->getEnabled() || !$this->controller || !$context) {
            return $actions;
        }

        $apiUrl = $request->getDispatcher()->url(
            $request,
            Application::ROUTE_API,
            $context->getPath(),
            $this->controller->getHandlerPath()
        );

        $form = new SubmissionRateLimitSettingsForm($apiUrl);

        array_unshift($actions, new LinkAction(
            'settings',
            new VueModal(
                'PkpFormModal',
                [
                    'title' => $this->getDisplayName(),
                    'formConfig' => $form->getConfig(),
                    'getApiUrl' => $apiUrl,
                ]
            ),
            __('manager.plugins.settings'),
            null
        ));

        return $actions;
    }

    /**
     * Localized message if the user has reached a limit, otherwise null
     *
     * @param string $eventType Constants::EVENT_STARTED or Constants::EVENT_SUBMITTED
     */
    private function getLimitMessage(int $contextId, int $userId, string $eventType): ?string
    {
        $now = time();
        $violation = (new RateLimiter($this))->findViolation($contextId, $userId, $eventType, $now);
        if (!$violation) {
            return null;
        }
        $key = $eventType === Constants::EVENT_STARTED ? 'limitReached.start' : 'limitReached';
        // Only the waiting time is shown, the configured limits are not disclosed
        return __('plugins.generic.submissionRateLimit.' . $key, [
            'wait' => $this->formatWait($violation['nextAllowed'] - $now),
        ]);
    }

    /**
     * Whether the limits do not apply to this user in this journal
     */
    private function isExempt(\PKP\user\User $user, int $contextId): bool
    {
        return $this->isEditorialStaffExempt($contextId) && $this->isEditorialStaff($user, $contextId);
    }

    /**
     * Whether the user is site admin or editorial staff in the journal
     */
    private function isEditorialStaff(\PKP\user\User $user, int $contextId): bool
    {
        return $user->hasRole([Role::ROLE_ID_SITE_ADMIN], Application::SITE_CONTEXT_ID)
            || $user->hasRole([Role::ROLE_ID_MANAGER, Role::ROLE_ID_SUB_EDITOR, Role::ROLE_ID_ASSISTANT], $contextId);
    }

    /**
     * Human readable waiting time, rounded up
     */
    private function formatWait(int $seconds): string
    {
        $seconds = max($seconds, 60);
        if ($seconds < 2 * 3600) {
            return __('plugins.generic.submissionRateLimit.wait.minutes', ['count' => (int) ceil($seconds / 60)]);
        }
        if ($seconds < 2 * 86400) {
            return __('plugins.generic.submissionRateLimit.wait.hours', ['count' => (int) ceil($seconds / 3600)]);
        }
        return __('plugins.generic.submissionRateLimit.wait.days', ['count' => (int) ceil($seconds / 86400)]);
    }
}
