{**
 * @file plugins/importexport/k10Plus/index.tpl
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * List of operations this plugin can perform
 *}
{extends file="layouts/backend.tpl"}

{block name="page"}
	<h1 class="app__pageHeading">
		{$pageTitle}
	</h1>

	{* Show export tabs only if settings are configured. *}
	{if !empty($configurationErrors)}
		{assign var="allowExport" value=false}
	{else}
		{assign var="allowExport" value=true}
	{/if}

	<script type="text/javascript">
		// Attach the JS file tab handler.
		$(function() {ldelim}
			$('#importExportTabs').pkpHandler('$.pkp.controllers.TabHandler');
		{rdelim});
	</script>

	{* Tabs. *}
	<div id="importExportTabs">
		<ul>
			<li><a href="#settings-tab">{translate key="plugins.importexport.common.settings"}</a></li>
			{if $allowExport}
				<li><a href="#exportSubmissions-tab">{translate key="plugins.importexport.common.export.articles"}</a></li>
			{/if}
		</ul>
		
		{* Settings tab. *}
		<div id="settings-tab">
			{if !$allowExport}
				<div class="pkp_notification" id="k10PlusConfigurationErrors">

				{* Make sure settings form is filled out. *}
					{foreach from=$configurationErrors item=configurationError}
						{if $configurationError == $smarty.const.EXPORT_CONFIG_ERROR_SETTINGS}
							{include file="controllers/notification/inPlaceNotificationContent.tpl" notificationId=k10PlusConfigurationErrors notificationStyleClass="notifyWarning" notificationTitle="plugins.importexport.common.missingRequirements"|translate notificationContents="plugins.importexport.common.error.pluginNotConfigured"|translate}
						{/if}
					{/foreach}
				</div>
			{/if}

			{* Settings Grid. *}
			{capture assign=k10PlusSettingsGridUrl}{url router=$smarty.const.ROUTE_COMPONENT component="grid.settings.plugins.settingsPluginGridHandler" op="manage" plugin="K10PlusExportPlugin" category="importexport" verb="index" escape=false}{/capture}
			{load_url_in_div id="k10PlusSettingsGridContainer" url=$k10PlusSettingsGridUrl}
		</div>
		
			{* Select all function. *}	
			<script type="text/javascript">
			$(document).ready(function() {ldelim}
			   var checkboxSubmissions = '#exportSubmissions-tab input[id^="select-"]';
			   
					// Select-all button for submissions.
					$("#toggle_all_subs_action").click(function () {ldelim}
						if($(checkboxSubmissions).prop('checked') === false){ldelim}
							$(checkboxSubmissions).prop('checked', true);
							{rdelim} else {ldelim}
							$(checkboxSubmissions).prop('checked', false);
							{rdelim}
					{rdelim});
			{rdelim});
			</script>	

		{* Export Submissions Tab. *}
		{if $allowExport}
			<div id="exportSubmissions-tab">
				<script type="text/javascript">
					$(function() {ldelim}
						// Attach the form handler.
						$('#exportSubmissionXmlForm').pkpHandler('$.pkp.controllers.form.FormHandler');
					{rdelim});
				</script>
				<form id="exportSubmissionXmlForm" class="pkp_form" action="{plugin_url path="exportSubmissions"}" method="post">
					{csrf}
					<input type="hidden" name="tab" value="exportSubmissions-tab" />
					{fbvFormArea id="submissionsXmlForm"}
						{capture assign=submissionsListGridUrl}{url router=$smarty.const.ROUTE_COMPONENT component="grid.submissions.ExportPublishedSubmissionsListGridHandler" op="fetchGrid" plugin="k10Plus" category="importexport" escape=false}{/capture}
						{load_url_in_div id="submissionsListGridContainer" url=$submissionsListGridUrl}
						
						{if !empty($actionNames)}
							{fbvFormSection}
							<ul class="export_actions">
							
								{foreach from=$actionNames key=action item=actionName}
									<li class="export_action">
										{fbvElement type="submit" label="$actionName" id="$action" name="$action" value="1" class="$action" translate=false inline=true}
									</li>
								{/foreach}

								{* Select-all button. *}
								<li class="toggle_all_subs_action">
									{fbvElement type="button" label="plugins.importexport.k10Plus.action.selectAll" id="toggle_all_subs_action" name="toggle_all_subs_action" class="toggle_all_subs_action" inline=true}
								</li>
							</ul>
							{/fbvFormSection}
						{/if}
					{/fbvFormArea}
				</form>
				<div class="statusLegend">
				<p>{translate key="plugins.importexport.k10Plus.statusLegend"}</p>
				</div>
			</div>
		{/if}
			
	</div>
{/block}
