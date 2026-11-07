{**
 * plugins/generic/submissionRateLimit/templates/startBlocked.tpl
 *
 * Copyright (c) 2026 Atilla Wohllebe
 * Distributed under the GNU GPL v3. For full terms see the file LICENSE.
 *
 * Shown instead of the "Start a new submission" page when the user has
 * reached a submission limit.
 *}
{extends file="layouts/backend.tpl"}

{block name="page"}
	<h1 class="app__pageHeading app__pageHeading--center app__pageHeading--spacious">
		{translate key="submission.wizard.title"}
	</h1>
	<notification type="warning">
		{$submissionRateLimitMessage|escape}
	</notification>
{/block}
