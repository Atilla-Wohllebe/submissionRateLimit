<?php

/**
 * @file plugins/generic/submissionRateLimit/SubmissionRateLimitSettingsForm.php
 *
 * Copyright (c) 2026 Atilla Wohllebe
 * Distributed under the GNU GPL v3. For full terms see the file LICENSE.
 *
 * @class SubmissionRateLimitSettingsForm
 *
 * @brief Settings form shown in a modal from the plugin list.
 */

namespace APP\plugins\generic\submissionRateLimit;

use APP\plugins\generic\submissionRateLimit\classes\Constants;
use PKP\components\forms\FieldOptions;
use PKP\components\forms\FieldSelect;
use PKP\components\forms\FieldText;
use PKP\components\forms\FormComponent;

class SubmissionRateLimitSettingsForm extends FormComponent
{
    public $id = 'submissionRateLimitSettings';
    public $method = 'PUT';

    public function __construct(string $action)
    {
        $this->action = $action;

        $unitOptions = [];
        foreach (array_keys(Constants::UNIT_SECONDS) as $unit) {
            $unitOptions[] = ['value' => $unit, 'label' => __('plugins.generic.submissionRateLimit.unit.' . $unit)];
        }

        for ($i = 1; $i <= Constants::RULE_COUNT; $i++) {
            $groupId = "rule{$i}";
            $this->addGroup([
                'id' => $groupId,
                'label' => __('plugins.generic.submissionRateLimit.rule', ['number' => $i]),
                'description' => $i === 1 ? __('plugins.generic.submissionRateLimit.rule.description') : '',
            ]);
            $this->addField(new FieldText(Constants::maxField($i), [
                'label' => __('plugins.generic.submissionRateLimit.rule.max'),
                'inputType' => 'number',
                'size' => 'small',
                'groupId' => $groupId,
                'value' => '',
            ]));
            $this->addField(new FieldText(Constants::periodField($i), [
                'label' => __('plugins.generic.submissionRateLimit.rule.period'),
                'inputType' => 'number',
                'size' => 'small',
                'groupId' => $groupId,
                'value' => '',
            ]));
            $this->addField(new FieldSelect(Constants::unitField($i), [
                'label' => __('plugins.generic.submissionRateLimit.rule.unit'),
                'options' => $unitOptions,
                'groupId' => $groupId,
                'value' => Constants::UNIT_DAYS,
            ]));
        }

        $this->addGroup([
            'id' => 'options',
            'label' => __('plugins.generic.submissionRateLimit.options'),
        ]);
        $this->addField(new FieldOptions(Constants::EXEMPT_EDITORS, [
            'label' => __('plugins.generic.submissionRateLimit.exemptEditors'),
            'options' => [
                ['value' => true, 'label' => __('plugins.generic.submissionRateLimit.exemptEditors.description')],
            ],
            'groupId' => 'options',
            'value' => true,
        ]));
    }
}
