<?php

/**
 * @file plugins/importexport/k10Plus/K10PlusInfoSender.inc.php
 * @brief Scheduled task to send deposits to the BSZ.
 */

import('lib.pkp.classes.scheduledTask.ScheduledTask');
define('EXPORT_STATUS_NOT_DEPOSITED', 'notDeposited');
define('EXPORT_STATUS_MARKEDUNREGISTERED', 'markedUnregistered');
define('EXPORT_STATUS_FAILED', 'failed');

class K10PlusInfoSender extends ScheduledTask
{

	/** @var $_plugin K10PlusExportPlugin */
	var $_plugin;

	/**
	 * Constructor.
	 * @param $argv array task arguments
	 */
	function __construct($args)
	{
		PluginRegistry::loadCategory('importexport');
		$plugin = PluginRegistry::getPlugin('importexport', 'K10PlusExportPlugin'); /* @var $plugin K10PlusExportPlugin */
		$this->_plugin = $plugin;

		if (is_a($plugin, 'K10PlusExportPlugin')) {
			$plugin->addLocaleData();
		}
		parent::__construct($args);
	}

	/**
	 * @copydoc ScheduledTask::getName()
	 */
	function getName()
	{
		return __('plugins.importexport.k10Plus.senderTask.name');
	}

	/**
	 * @copydoc ScheduledTask::executeActions()
	 */
	function executeActions()
	{
		if (!$this->_plugin) return false;

		// Get plugin and journals.
		$plugin = $this->_plugin;
		$journals = $this->_getJournals();

		// Iterate through journals.
		foreach ($journals as $journal) {

			// Get unregistered articles.
			$unregisteredArticles = $plugin->getUnregisteredArticles($journal);

			// Check if there are articles to be deposited.
			if (count($unregisteredArticles) != '0') {
				$this->_registerObjects($unregisteredArticles, 'article=>k10Plus-xml', $journal, 'articles');
			}
		}
		return true;
	}



	/**
	 * Get all journals that meet the requirements to have
	 * their articles automatically sent to the BSZ.
	 * @return array
	 */
	function _getJournals()
	{
		// Fetch necessary vars.
		$plugin = $this->_plugin;
		$contextDao = Application::getContextDAO(); /* @var $contextDao JournalDAO */
		$journalFactory = $contextDao->getAll(true);

		$journals = array();
		while ($journal = $journalFactory->next()) {
			$journalId = $journal->getId();

			// Check if settings are complete.
			if (
				!$plugin->getSetting($journalId, 'username') ||
				!$plugin->getSetting($journalId, 'password') ||
				!$plugin->getSetting($journalId, 'serverAddress') ||
				!$plugin->getSetting($journalId, 'folderId') ||
				!$plugin->getSetting($journalId, 'automaticRegistration')
			) continue;
			$journals[] = $journal;
		}
		return $journals;
	}

	/**
	 * Register objects.
	 * @param $objects array
	 * @param $filter string
	 * @param $journal Journal
	 * @param $objectsFileNamePart string
	 */
	function _registerObjects($objects, $filter, $journal, $objectsFileNamePart)
	{
		// Fetch plugin and file manager.
		$plugin = $this->_plugin;
		import('lib.pkp.classes.file.FileManager');
		$fileManager = new FileManager();

		// Do not validate XML to skip errors during automatic registration. 
		$noValidation = 1;

		// Iterate over articles, collect exportable articles.
		foreach ($objects as $object) {

			// Journal name for logging.
			$journalName = $journal->getLocalizedName();

			// Log submission.
			$this->addExecutionLogEntry("Depositing: [Submission ID: " . $object->getData('id') . "]" . "[Journal: " . $journalName . "]", SCHEDULED_TASK_MESSAGE_TYPE_NOTICE);
			
			// Try constructing and depositing XML.
			try {
				$exportXml = $plugin->exportXML(array($object), $filter, $journal, $noValidation);

				// Write to file and collect in array.
				$objectsFileNamePartId = $objectsFileNamePart . '-' . $object->getId();
				$exportFileName = $plugin->getExportFileName($plugin->getExportPath(), $objectsFileNamePartId, $journal, '.xml');
				$fileManager->writeFile($exportFileName, $exportXml);
				$result = $plugin->depositXML($object, $journal, $exportFileName);
				$fileManager->deleteByPath($exportFileName);
				$plugin->updateDepositStatus($object, EXPORT_STATUS_REGISTERED, '');
				$this->addExecutionLogEntry("Success depositing: [Submission ID: "  . $object->getData('id') . "]" . "[Journal: " . $journalName . "]", SCHEDULED_TASK_MESSAGE_TYPE_NOTICE);

			// Keep track of errors.
			}  catch (ErrorException $e) {
				$error = [$e->getMessage()];
				$plugin->updateDepositStatus($object, EXPORT_STATUS_FAILED, $error[0]);
				$this->addExecutionLogEntry("Error depositing: [Submission ID: " . $object->getData('id') . "]" . "[Journal: " . $journalName . "]" . "[Error message: " . $error[0]. "]", SCHEDULED_TASK_MESSAGE_TYPE_WARNING);
			} 
		}
		return true;
	}	
}
