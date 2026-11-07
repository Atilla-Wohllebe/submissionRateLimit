# Submission Rate Limit

A generic plugin for [Open Journal Systems (OJS)](https://pkp.sfu.ca/software/ojs/) that protects journals against spam and mass submissions. It limits how many submissions a single user account can start and complete in a journal within configurable time windows, for example:

* at most 1 submission per 15 minutes,
* at most 2 submissions per hour,
* at most 5 submissions per day.

All active rules apply at the same time, separately to:

* **starting new submissions.** Every new submission counts, even if it is never completed. When the limit is reached, the "Start a new submission" page shows a message instead of the form, telling the author how long to wait.
* **completing submissions.** When the limit is reached, the final "Submit" step of the submission wizard is blocked and the author sees a message telling them how long to wait. The submission stays saved and can be completed later.

## Features

* Up to 4 rules per journal, each with a maximum number of submissions and a period in minutes, hours or days (up to 366 days).
* Messages to authors only say when they can try again. The configured limits are not disclosed.
* Optional exemption for site administrators, journal managers, section editors and journal assistants (enabled by default).
* Counts started and completed submissions in a dedicated log table. Deleting a submission (including an unfinished one) does not reset the counter.
* Also applies to submissions completed through the REST API.
* No changes to OJS core files. Settings and log are stored in the database and survive OJS upgrades.

Until the settings are saved for the first time, the defaults are 1 per 15 minutes, 2 per hour and 5 per day.

## Compatibility

* OJS 3.5.0-4 or newer (3.5.x). The limits themselves work from 3.5.0-0, the settings form requires 3.5.0-4.
* PHP 8.2 or newer.

## Installation

1. Upload `submissionRateLimit-x.y.z.z.tar.gz` under *Settings > Website > Plugins > Upload a New Plugin*,
   or extract it to `plugins/generic/submissionRateLimit` and run
   `php lib/pkp/tools/installPluginVersion.php plugins/generic/submissionRateLimit/version.xml`.
2. Enable "Submission Rate Limit" under *Settings > Website > Plugins > Generic Plugins*.
3. Click the arrow next to the plugin and then *Settings* to configure the rules.

If the log table is missing (e.g. the plugin was copied manually without running the installer), it is created automatically on first use.

## Upgrading OJS

Plugins that are not part of the OJS distribution must be kept when you replace the OJS code base during an upgrade: copy `plugins/generic/submissionRateLimit` from the old installation into the new one (or reinstall the package) before running the upgrade. Settings and the submission log remain in the database.

## How the limits are counted

* A new submission counts as "started" the moment it is created, for the user account that created it, per journal.
* A submission counts as "completed" the moment the author clicks "Submit", for the user account that completed it, per journal.
* Started and completed submissions are counted separately against the same rules. Completing a submission you started earlier is therefore not blocked by the "started" count.
* Submissions started or completed before the plugin (or version 1.1.0) was installed are not counted.
* Log entries older than 366 days are removed automatically.

## License

GNU General Public License v3. See [LICENSE](LICENSE).
