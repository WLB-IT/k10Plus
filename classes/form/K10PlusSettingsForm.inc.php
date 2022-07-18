<?php

/**
 * @file plugins/importexport/k10Plus/classes/form/K10PlusSettingsForm.inc.php
 *
 * @class K10PlusSettingsForm
 *
 * @brief Form for journal managers to setup K10+ plugin
 */


import('lib.pkp.classes.form.Form');

class K10PlusSettingsForm extends Form
{

	//
	// Private properties
	//
	/** @var integer */
	var $_contextId;

	/**
	 * Get the context ID.
	 * @return integer
	 */
	function _getContextId()
	{
		return $this->_contextId;
	}

	/** @var K10PlusExportPlugin */
	var $_plugin;

	/**
	 * Get the plugin.
	 * @return K10PlusExportPlugin
	 */
	function _getPlugin()
	{
		return $this->_plugin;
	}


	//
	// Constructor
	//
	/**
	 * Constructor
	 * @param $plugin K10PlusExportPlugin
	 * @param $contextId integer
	 */
	function __construct($plugin, $contextId)
	{
		$this->_contextId = $contextId;
		$this->_plugin = $plugin;

		parent::__construct($plugin->getTemplateResource('settingsForm.tpl'));

		// Add form validation checks: checks of username, make sure form is posted, CSRF token is correct.
		$this->addCheck(new FormValidatorRegExp($this, 'username', 'optional', 'plugins.importexport.k10Plus.settings.form.usernameRequired', '/^[^:]+$/'));
		$this->addCheck(new FormValidatorPost($this));
		$this->addCheck(new FormValidatorCSRF($this));
	}


	//
	// Implement template methods from Form
	//
	/**
	 * @copydoc Form::initData()
	 */
	function initData()
	{
		$contextId = $this->_getContextId();
		$plugin = $this->_getPlugin();
		foreach ($this->getFormFields() as $fieldName => $fieldType) {
			$this->setData($fieldName, $plugin->getSetting($contextId, $fieldName));
		}
	}

	/**
	 * @copydoc Form::readInputData()
	 */
	function readInputData()
	{
		$this->readUserVars(array_keys($this->getFormFields()));
	}

	/**
	 * @copydoc Form::execute()
	 */
	function execute(...$functionArgs)
	{
		$plugin = $this->_getPlugin();
		$contextId = $this->_getContextId();
		parent::execute(...$functionArgs);
		foreach ($this->getFormFields() as $fieldName => $fieldType) {
			$plugin->updateSetting($contextId, $fieldName, $this->getData($fieldName), $fieldType);
		}
	}


	//
	// Public helper methods
	//
	/**
	 * Get form fields
	 * @return array (field name => field type)
	 */
	function getFormFields()
	{
		return array(
			'username' => 'string',
			'automaticRegistration' => 'bool',
			'password' => 'string',
			'folderId' => 'string',
			'serverAddress' => 'string',
			'port' => 'string'
		);
	}

	/**
	 * Is the form field optional.
	 * @param $settingName string
	 * @return boolean
	 */
	function isOptional($settingName)
	{
		return in_array($settingName, array('automaticRegistration', 'port'));
	}
}
