<?php

/**
 * @file plugins/generic/submissionRateLimit/classes/Constants.php
 *
 * Copyright (c) 2026 Atilla Wohllebe
 * Distributed under the GNU GPL v3. For full terms see the file LICENSE.
 *
 * @class Constants
 *
 * @brief Setting names, units and defaults used by the Submission Rate Limit plugin.
 */

namespace APP\plugins\generic\submissionRateLimit\classes;

class Constants
{
    /** Number of configurable limit rules shown in the settings form */
    public const RULE_COUNT = 4;

    /** Setting that marks that the settings form has been saved at least once */
    public const CONFIGURED = 'configured';

    /** Setting holding the "exempt editorial staff" option */
    public const EXEMPT_EDITORS = 'exemptEditors';

    /** Key used for the validation error returned to the submission wizard */
    public const ERROR_KEY = 'submissionRateLimit';

    /** Database table that logs started and completed submissions per user */
    public const LOG_TABLE = 'submission_rate_limit_log';

    /** Log entry type: a new (still incomplete) submission was created */
    public const EVENT_STARTED = 'started';

    /** Log entry type: a submission was completed ("Submit" in the wizard) */
    public const EVENT_SUBMITTED = 'submitted';

    /** Template variable holding the message on the blocked start page */
    public const TEMPLATE_MESSAGE = 'submissionRateLimitMessage';

    /** Longest supported time window (and log retention) in seconds: 366 days */
    public const MAX_WINDOW_SECONDS = 31622400;

    public const UNIT_MINUTES = 'minutes';
    public const UNIT_HOURS = 'hours';
    public const UNIT_DAYS = 'days';

    /** Seconds per unit */
    public const UNIT_SECONDS = [
        self::UNIT_MINUTES => 60,
        self::UNIT_HOURS => 3600,
        self::UNIT_DAYS => 86400,
    ];

    /**
     * Default rules, used until the settings have been saved once:
     * 1 per 15 minutes, 2 per hour, 5 per day.
     */
    public const DEFAULT_RULES = [
        1 => ['max' => 1, 'period' => 15, 'unit' => self::UNIT_MINUTES],
        2 => ['max' => 2, 'period' => 1, 'unit' => self::UNIT_HOURS],
        3 => ['max' => 5, 'period' => 1, 'unit' => self::UNIT_DAYS],
        4 => ['max' => null, 'period' => null, 'unit' => self::UNIT_DAYS],
    ];

    public static function maxField(int $i): string
    {
        return "rule{$i}Max";
    }

    public static function periodField(int $i): string
    {
        return "rule{$i}Period";
    }

    public static function unitField(int $i): string
    {
        return "rule{$i}Unit";
    }
}
