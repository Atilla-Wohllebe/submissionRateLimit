<?php

/**
 * @file plugins/generic/submissionRateLimit/formRequests/EditSubmissionRateLimitSettings.php
 *
 * Copyright (c) 2026 Atilla Wohllebe
 * Distributed under the GNU GPL v3. For full terms see the file LICENSE.
 *
 * @class EditSubmissionRateLimitSettings
 *
 * @brief Validates the plugin settings before they are saved.
 */

namespace APP\plugins\generic\submissionRateLimit\formRequests;

use APP\plugins\generic\submissionRateLimit\classes\Constants;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class EditSubmissionRateLimitSettings extends FormRequest
{
    /**
     * The settings form is sent as form data, so checkbox values arrive as the
     * strings "true"/"false" (or "" when unset). Convert them to real booleans
     * before validation, because Laravel's `boolean` rule rejects "true"/"false".
     */
    protected function prepareForValidation(): void
    {
        $value = $this->input(Constants::EXEMPT_EDITORS);
        if (is_array($value)) {
            $value = !empty($value);
        }
        $this->merge([
            Constants::EXEMPT_EDITORS => filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? false,
        ]);
    }

    public function rules(): array
    {
        $rules = [
            Constants::EXEMPT_EDITORS => ['nullable', 'boolean'],
        ];
        for ($i = 1; $i <= Constants::RULE_COUNT; $i++) {
            $rules[Constants::maxField($i)] = ['nullable', 'integer', 'min:0', 'max:100000'];
            $rules[Constants::periodField($i)] = ['nullable', 'integer', 'min:0', 'max:527040'];
            $rules[Constants::unitField($i)] = ['nullable', Rule::in(array_keys(Constants::UNIT_SECONDS))];
        }
        return $rules;
    }
}
