{**
 * plugins/importexport/k10Plus/templates/settingsForm.tpl
 *
 * k10Plus plugin settings.
 *
 *}

 <script type="text/javascript">
 $(function() {ldelim}
 // Attach the form handler.
 $('#k10PlusSettingsForm').pkpHandler('$.pkp.controllers.form.AjaxFormHandler');
 {rdelim});
</script>
<form class="pkp_form" id="k10PlusSettingsForm" method="post" action="{url router=$smarty.const.ROUTE_COMPONENT op="manage" plugin="K10PlusExportPlugin" category="importexport" verb="save"}">
 {csrf}
 {fbvFormArea id="k10PlusSettingsFormArea"}
 <p class="pkp_help"><b>{translate key="plugins.importexport.k10Plus.intro"}</b></p>

 {* Form Section: username, password *}
 {fbvFormSection}
 {fbvElement type="text" id="username" value=$username label="plugins.importexport.k10Plus.settings.form.username" maxlength="50" size=$fbvStyles.size.MEDIUM required="true"}
 {fbvElement type="text" password="true" id="password" value=$password label="plugins.importexport.common.settings.form.password" maxLength="50" size=$fbvStyles.size.MEDIUM required="true"}
 <span class="instruct">{translate key="plugins.importexport.common.settings.form.password.description"}</span><br />
 {/fbvFormSection}

  {* Server Address. *}
  {fbvFormSection}
  {fbvElement type="text" id="serverAddress" value=$serverAddress label="plugins.importexport.k10Plus.settings.form.serverAddress" maxlength="50" size=$fbvStyles.size.MEDIUM required="true"}
  <span
	  class="instruct">{translate key="plugins.importexport.k10Plus.settings.form.serverAddress.description"}</span><br />
  {/fbvFormSection}

   {* Port. *}
 {fbvFormSection}
 {fbvElement type="text" id="port" value=$port label="plugins.importexport.k10Plus.settings.form.port" maxlength="50" size=$fbvStyles.size.MEDIUM }
 <span
	 class="instruct">{translate key="plugins.importexport.k10Plus.settings.form.port.description"}</span><br />
 {/fbvFormSection}

 {* Hotfolder ID. *}
 {fbvFormSection}
 {fbvElement type="text" id="folderId" value=$folderId label="plugins.importexport.k10Plus.settings.form.folderId" maxlength="50" size=$fbvStyles.size.MEDIUM required="true"}
 <span
	 class="instruct">{translate key="plugins.importexport.k10Plus.settings.form.folderId.description"}</span><br />
 {/fbvFormSection}


 {* Form section: automatic deposit.*}
 {fbvFormSection list="true"}
 <p class="pkp_help"><b>{translate key="plugins.importexport.k10Plus.settings.form.automaticDeposit.heading"}</b></p>
 {fbvElement type="checkbox" id="automaticRegistration" label="plugins.importexport.k10Plus.settings.form.automaticDeposit.description" checked=$automaticRegistration|compare:true}
 {/fbvFormSection}
 {/fbvFormArea}
 
 {fbvFormButtons submitText="common.save"}
 <p><span class="formRequired">{translate key="common.requiredField"}</span></p>
</form>