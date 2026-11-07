<?php

/**
 * @file plugins/generic/submissionRateLimit/SubmissionRateLimitSettingsController.php
 *
 * Copyright (c) 2026 Atilla Wohllebe
 * Distributed under the GNU GPL v3. For full terms see the file LICENSE.
 *
 * @class SubmissionRateLimitSettingsController
 *
 * @brief API controller to read and save the plugin settings.
 *   Access (journal managers and site admins) is enforced by PluginSettingsController.
 */

namespace APP\plugins\generic\submissionRateLimit;

use APP\plugins\generic\submissionRateLimit\classes\Constants;
use APP\plugins\generic\submissionRateLimit\classes\RateLimiter;
use APP\plugins\generic\submissionRateLimit\formRequests\EditSubmissionRateLimitSettings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use PKP\plugins\PluginSettingsController;

class SubmissionRateLimitSettingsController extends PluginSettingsController
{
    public function get(Request $illuminateRequest): JsonResponse
    {
        $contextId = $this->getRequest()->getContext()->getId();
        return response()->json($this->getValues($contextId), Response::HTTP_OK);
    }

    public function edit(EditSubmissionRateLimitSettings $illuminateRequest): JsonResponse
    {
        $contextId = $this->getRequest()->getContext()->getId();
        $data = $illuminateRequest->validated();

        for ($i = 1; $i <= Constants::RULE_COUNT; $i++) {
            $max = $data[Constants::maxField($i)] ?? null;
            $period = $data[Constants::periodField($i)] ?? null;
            $unit = $data[Constants::unitField($i)] ?? Constants::UNIT_DAYS;
            $this->plugin->updateSetting($contextId, Constants::maxField($i), is_numeric($max) ? (int) $max : 0, 'int');
            $this->plugin->updateSetting($contextId, Constants::periodField($i), is_numeric($period) ? (int) $period : 0, 'int');
            $this->plugin->updateSetting($contextId, Constants::unitField($i), $unit ?: Constants::UNIT_DAYS, 'string');
        }
        $this->plugin->updateSetting($contextId, Constants::EXEMPT_EDITORS, (bool) ($data[Constants::EXEMPT_EDITORS] ?? false), 'bool');
        $this->plugin->updateSetting($contextId, Constants::CONFIGURED, true, 'bool');

        return response()->json($this->getValues($contextId), Response::HTTP_OK);
    }

    /**
     * Current values in the shape expected by the settings form
     */
    private function getValues(int $contextId): array
    {
        /** @var SubmissionRateLimitPlugin $plugin */
        $plugin = $this->plugin;
        $values = [];
        foreach ((new RateLimiter($plugin))->getRuleSettings($contextId) as $i => $rule) {
            $values[Constants::maxField($i)] = $rule['max'] ?: '';
            $values[Constants::periodField($i)] = $rule['period'] ?: '';
            $values[Constants::unitField($i)] = $rule['unit'];
        }
        $values[Constants::EXEMPT_EDITORS] = $plugin->isEditorialStaffExempt($contextId);
        return $values;
    }
}
