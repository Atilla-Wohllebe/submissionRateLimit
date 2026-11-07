<?php

/**
 * @file plugins/generic/submissionRateLimit/SubmissionRateLimitSchemaMigration.php
 *
 * Copyright (c) 2026 Atilla Wohllebe
 * Distributed under the GNU GPL v3. For full terms see the file LICENSE.
 *
 * @class SubmissionRateLimitSchemaMigration
 *
 * @brief Creates the table that logs started and completed submissions per user and journal.
 *   No foreign keys on purpose: entries must survive when a submission is deleted,
 *   so that deleting submissions cannot be used to bypass the limits.
 */

namespace APP\plugins\generic\submissionRateLimit;

use APP\plugins\generic\submissionRateLimit\classes\Constants;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class SubmissionRateLimitSchemaMigration extends Migration
{
    /**
     * Run the migration.
     */
    public function up(): void
    {
        if (!Schema::hasTable(Constants::LOG_TABLE)) {
            Schema::create(Constants::LOG_TABLE, function (Blueprint $table) {
                $table->bigIncrements('log_id');
                $table->bigInteger('context_id');
                $table->bigInteger('user_id');
                $table->bigInteger('submission_id')->nullable();
                $table->string('event_type', 16)->default(Constants::EVENT_SUBMITTED);
                $table->dateTime('date_submitted');
                $table->index(['context_id', 'user_id', 'date_submitted'], 'submission_rate_limit_log_lookup');
                $table->index(['date_submitted'], 'submission_rate_limit_log_date');
            });
            return;
        }

        // Upgrade from 1.0.x: entries logged so far were completed submissions
        if (!Schema::hasColumn(Constants::LOG_TABLE, 'event_type')) {
            Schema::table(Constants::LOG_TABLE, function (Blueprint $table) {
                $table->string('event_type', 16)->default(Constants::EVENT_SUBMITTED);
            });
        }
    }

    /**
     * Reverse the migration.
     */
    public function down(): void
    {
        Schema::dropIfExists(Constants::LOG_TABLE);
    }
}
