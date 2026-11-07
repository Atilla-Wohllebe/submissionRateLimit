<?php

/**
 * @file plugins/generic/submissionRateLimit/classes/RateLimiter.php
 *
 * Copyright (c) 2026 Atilla Wohllebe
 * Distributed under the GNU GPL v3. For full terms see the file LICENSE.
 *
 * @class RateLimiter
 *
 * @brief Reads the configured rules, logs started and completed submissions
 *   and checks whether a user may start or complete another submission.
 */

namespace APP\plugins\generic\submissionRateLimit\classes;

use APP\plugins\generic\submissionRateLimit\SubmissionRateLimitPlugin;
use APP\plugins\generic\submissionRateLimit\SubmissionRateLimitSchemaMigration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class RateLimiter
{
    /** Whether the log table is known to exist in this request */
    private static bool $tableChecked = false;

    public function __construct(private SubmissionRateLimitPlugin $plugin)
    {
    }

    /**
     * Get the settings of all rule slots, falling back to the defaults
     * as long as the settings form has never been saved.
     *
     * @return array<int, array{max: ?int, period: ?int, unit: string}>
     */
    public function getRuleSettings(int $contextId): array
    {
        $configured = (bool) $this->plugin->getSetting($contextId, Constants::CONFIGURED);

        $rules = [];
        for ($i = 1; $i <= Constants::RULE_COUNT; $i++) {
            if (!$configured) {
                $rules[$i] = Constants::DEFAULT_RULES[$i];
                continue;
            }
            $max = $this->plugin->getSetting($contextId, Constants::maxField($i));
            $period = $this->plugin->getSetting($contextId, Constants::periodField($i));
            $unit = $this->plugin->getSetting($contextId, Constants::unitField($i));
            $rules[$i] = [
                'max' => is_numeric($max) ? (int) $max : null,
                'period' => is_numeric($period) ? (int) $period : null,
                'unit' => isset(Constants::UNIT_SECONDS[$unit]) ? $unit : Constants::UNIT_DAYS,
            ];
        }
        return $rules;
    }

    /**
     * Get only the active rules (max >= 1 and a period > 0), with their window in seconds.
     *
     * @return array<int, array{max: int, period: int, unit: string, seconds: int}>
     */
    public function getActiveRules(int $contextId): array
    {
        $active = [];
        foreach ($this->getRuleSettings($contextId) as $rule) {
            if (empty($rule['max']) || empty($rule['period']) || $rule['max'] < 1 || $rule['period'] < 1) {
                continue;
            }
            $seconds = min($rule['period'] * Constants::UNIT_SECONDS[$rule['unit']], Constants::MAX_WINDOW_SECONDS);
            $active[] = $rule + ['seconds' => $seconds];
        }
        return $active;
    }

    /**
     * Find the rule that currently blocks the user, if any.
     *
     * If several rules are exceeded, the one that blocks the longest is returned.
     *
     * @param string $eventType Constants::EVENT_STARTED or Constants::EVENT_SUBMITTED
     *
     * @return ?array The violated rule plus `nextAllowed` (unix timestamp)
     */
    public function findViolation(int $contextId, int $userId, string $eventType, ?int $now = null): ?array
    {
        $rules = $this->getActiveRules($contextId);
        if (empty($rules)) {
            return null;
        }
        $this->ensureTable();
        $now ??= time();

        $longest = max(array_column($rules, 'seconds'));
        $timestamps = DB::table(Constants::LOG_TABLE)
            ->where('context_id', $contextId)
            ->where('user_id', $userId)
            ->where('event_type', $eventType)
            ->where('date_submitted', '>', date('Y-m-d H:i:s', $now - $longest))
            ->orderByDesc('date_submitted')
            ->pluck('date_submitted')
            ->map(fn ($date) => strtotime((string) $date))
            ->all();

        $violation = null;
        foreach ($rules as $rule) {
            $since = $now - $rule['seconds'];
            $inWindow = array_values(array_filter($timestamps, fn (int $ts) => $ts > $since));
            if (count($inWindow) < $rule['max']) {
                continue;
            }
            // Allowed again once the max-th most recent entry leaves the window
            $nextAllowed = $inWindow[$rule['max'] - 1] + $rule['seconds'];
            if (!$violation || $nextAllowed > $violation['nextAllowed']) {
                $violation = $rule + ['nextAllowed' => $nextAllowed];
            }
        }
        return $violation;
    }

    /**
     * Log a started or completed submission and remove entries older than the
     * longest supported window.
     *
     * @param string $eventType Constants::EVENT_STARTED or Constants::EVENT_SUBMITTED
     */
    public function record(int $contextId, int $userId, int $submissionId, string $eventType, ?int $now = null): void
    {
        $this->ensureTable();
        $now ??= time();

        DB::table(Constants::LOG_TABLE)->insert([
            'context_id' => $contextId,
            'user_id' => $userId,
            'submission_id' => $submissionId,
            'event_type' => $eventType,
            'date_submitted' => date('Y-m-d H:i:s', $now),
        ]);

        DB::table(Constants::LOG_TABLE)
            ->where('date_submitted', '<', date('Y-m-d H:i:s', $now - Constants::MAX_WINDOW_SECONDS))
            ->delete();
    }

    /**
     * Create or update the log table if needed, e.g. when the plugin was copied
     * into the plugins directory without running the plugin installer.
     */
    private function ensureTable(): void
    {
        if (self::$tableChecked) {
            return;
        }
        if (!Schema::hasTable(Constants::LOG_TABLE) || !Schema::hasColumn(Constants::LOG_TABLE, 'event_type')) {
            (new SubmissionRateLimitSchemaMigration())->up();
        }
        self::$tableChecked = true;
    }
}
