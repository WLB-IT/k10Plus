<?php

/**
 * @file plugins/importexport/k10Plus/K10PlusExportPlugin.inc.php
 *
 * @brief K10Plus export plugin
 */

import('classes.plugins.PubObjectsExportPlugin');
define('EXPORT_STATUS_FAILED', 'failed');
define('EXPORT_ACTION_MARKUNREGISTERED', 'markUnregistered');
define('EXPORT_STATUS_MARKEDUNREGISTERED', 'markedUnregistered');

class K10PlusExportPlugin extends PubObjectsExportPlugin
{
	/**
	 * @copydoc Plugin::getName()
	 */
	function getName()
	{
		return 'K10PlusExportPlugin';
	}

	/**
	 * @copydoc Plugin::getDisplayName()
	 */
	function getDisplayName()
	{
		return __('plugins.importexport.k10Plus.displayName');
	}

	/**
	 * @copydoc Plugin::getDescription()
	 */
	function getDescription()
	{
		return __('plugins.importexport.k10Plus.description');
	}

	/**
	 * @copydoc ImportExportPlugin::getPluginSettingsPrefix()
	 */
	function getPluginSettingsPrefix()
	{
		return 'k10Plus';
	}

	/**
	 * @copydoc PubObjectsExportPlugin::getSubmissionFilter()
	 */
	function getSubmissionFilter()
	{
		return 'article=>k10Plus-xml';
	}

	/**
	 * @copydoc PubObjectsExportPlugin::getExportDeploymentClassName()
	 */
	function getExportDeploymentClassName()
	{
		return 'K10PlusExportDeployment';
	}

	/**
	 * @copydoc PubObjectsExportPlugin::getSettingsFormClassName()
	 */
	function getSettingsFormClassName()
	{
		return 'K10PlusSettingsForm';
	}

	/**
	 * @copydoc ImportExportPlugin::display()
	 */
	function display($args, $request)
	{
		parent::display($args, $request);
		switch (array_shift($args)) {
			case 'index':
			case '':
				$templateMgr = TemplateManager::getManager($request);
				$templateMgr->display($this->getTemplateResource('index.tpl'));
				break;
		}
	}

	/**
	 * @copydoc PubObjectsExportPlugin::getExportActionNames()
	 */
	function getExportActionNames()
	{
		return array(
			EXPORT_ACTION_DEPOSIT => __('plugins.importexport.k10Plus.action.register'),
			EXPORT_ACTION_EXPORT => __('plugins.importexport.k10Plus.action.export'),
			EXPORT_ACTION_MARKREGISTERED => __('plugins.importexport.k10Plus.action.markRegistered'),
			EXPORT_ACTION_MARKUNREGISTERED => __('plugins.importexport.k10Plus.action.markUnregistered'),
		);
	}

	/**
	 * Get status names for the filter search option.
	 * @copydoc PubObjectsExportPlugin::getStatusNames()
	 */
	function getStatusNames()
	{
		return array_merge(parent::getStatusNames(), array(
			EXPORT_STATUS_REGISTERED => __('plugins.importexport.k10Plus.status.registered'),
			EXPORT_STATUS_NOT_DEPOSITED => __('plugins.importexport.k10Plus.status.notdeposited'),
			EXPORT_STATUS_FAILED => __('plugins.importexport.k10Plus.status.failed'),
			EXPORT_STATUS_MARKEDREGISTERED => __('plugins.importexport.k10Plus.status.markedRegistered'),
			EXPORT_STATUS_MARKEDUNREGISTERED => __('plugins.importexport.k10Plus.status.markedUnregistered'),
		));
	}

	/**
	 * Get actions: only show deposit button if all settings are configured.
	 * @param $context Context
	 * @return array
	 */
	function getExportActions($context)
	{
		$actions = array(EXPORT_ACTION_EXPORT, EXPORT_ACTION_MARKREGISTERED, EXPORT_ACTION_MARKUNREGISTERED);
		if (
			$this->getSetting($context->getId(), 'username') &&
			$this->getSetting($context->getId(), 'password') &&
			$this->getSetting($context->getId(), 'serverAddress') &&
			$this->getSetting($context->getId(), 'folderId')
		) {
			array_unshift($actions, EXPORT_ACTION_DEPOSIT);
		}
		return $actions;
	}

	/**
	 * @copydoc PubObjectsExportPlugin::executeExportAction()
	 */
	function executeExportAction($request, $objects, $filter, $tab, $objectsFileNamePart, $noValidation = null)
	{

		// Get context and path.
		$context = $request->getContext();
		$path = array('plugin', $this->getName());

		// Get file manager.
		import('lib.pkp.classes.file.FileManager');
		$fileManager = new FileManager();

		// Export files: prepare tar/xml files upon export.
		if ($request->getUserVar(EXPORT_ACTION_EXPORT)) {

			// Check if tar binary is available (only for export action not for deposit.)
			$checkForTar = $this->checkForTar();
			if ($checkForTar === true) {

				// Iterate through articles to collect files to be exported.
				$exportedFiles = array();
				foreach ($objects as $object) {

					// Make sure filter is set.
					assert($filter != null);

					// Error messages when data is missing.
					try {

						// Get the XML.
						$exportXml = $this->exportXML(array($object), $filter, $context, $noValidation);

						// Write to file and collect in array.
						$objectsFileName = $objectsFileNamePart . '-' . $object->getId();
						$exportFileName = $this->getExportFileName($this->getExportPath(), $objectsFileName, $context, '.xml');
						$fileManager->writeFile($exportFileName, $exportXml);
						$exportedFiles[] = $exportFileName;

				    // Get message and notify user, redirect to tab.
					} catch (ErrorException $e) {
						$error = [$e->getMessage()];
						$this->_sendNotification(
							$request->getUser(),
							iconv("utf-8", "ascii//TRANSLIT", $error[0]),
							NOTIFICATION_TYPE_ERROR,
							(isset($error[1]) ? $error[1] : null)
						);

						// If one file throws an error, stop.
						$request->redirect(null, null, null, $path, null, $tab);
						return $error;
					}
				}

				// Make sure there is a file to export.
				assert(count($exportedFiles) >= 1);

				// Package in tar.gz if there are several xml files.
				if (count($exportedFiles) > 1) {

					// Tar file name: e.g. k10Plus-20160723-160036-articles-1.tar.gz
					$finalExportFileName = $this->getExportFileName($this->getExportPath(), $objectsFileNamePart, $context, '.tar.gz');
					$this->tarFiles($this->getExportPath(), $finalExportFileName, $exportedFiles);

					// Delete temp xml files from the tar package.
					foreach ($exportedFiles as $exportedFile) {
						$fileManager->deleteByPath($exportedFile);
					}

				// Treat single file to export as xml.
				} else {
					$finalExportFileName = array_shift($exportedFiles);
				}

				// Download files and delete temp files.
				$fileManager->downloadByPath($finalExportFileName);
				$fileManager->deleteByPath($finalExportFileName);

			// Handle tar-archive errors.
			} else {
				if (is_array($checkForTar)) {
					foreach ($checkForTar as $error) {
						assert(is_array($error) && count($error) >= 1);
						$this->_sendNotification(
							$request->getUser(),
							$error[0],
							NOTIFICATION_TYPE_ERROR,
							(isset($error[1]) ? $error[1] : null)
						);
					}
				}
				// Redirect back to the right tab.
				$request->redirect(null, null, null, $path, null, $tab);
				return $error;
			}

	    // Deposit files: do not use tar-packaging as requested by BSZ.
		} elseif ($request->getUserVar(EXPORT_ACTION_DEPOSIT)) {

			// Iterate through articles, export as xml, then deposit.
			foreach ($objects as $object) {

				// Make sure filter is set.
				assert($filter != null);

				// Try construction and depositing each object.
				try {

					// Construct the XML.
					$exportXml = $this->exportXML(array($object), $filter, $context, $noValidation);

					// Write to file and deposit.
					$objectsFileName = $objectsFileNamePart . '-' . $object->getId();
					$exportFileName = $this->getExportFileName($this->getExportPath(), $objectsFileName, $context, '.xml');
					$fileManager->writeFile($exportFileName, $exportXml);

					// Status and msg are updated within depositXML function.
					$result = $this->depositXML($object, $context, $exportFileName);
					$fileManager->deleteByPath($exportFileName);

					// Notify user of success if xml was deposited.
					$this->_sendNotification(
						$request->getUser(),
						$this->getDepositSuccessNotificationMessageKey(),
						NOTIFICATION_TYPE_SUCCESS
					);

				// Get error message, update status and msg and notify user, redirect to tab.
				} catch (ErrorException $e) {
					$error = [$e->getMessage()];

					// Update object status and error message.
					$this->updateDepositStatus($object, EXPORT_STATUS_FAILED, $error[0]);

					// Send notification.
					$this->_sendNotification(
						$request->getUser(),
						iconv("utf-8", "ascii//TRANSLIT", $error[0]),
						NOTIFICATION_TYPE_ERROR,
						(isset($error[1]) ? $error[1] : null)
					);
				}
			}

			// Redirect back to the right tab.
			$request->redirect(null, null, null, $path, null, $tab);
		
		// Mark unregistered. 
		} elseif ($request->getUserVar(EXPORT_ACTION_MARKUNREGISTERED)) {
			$this->markUnregistered($context, $objects);

			// Redirect back to the right tab.
			$request->redirect(null, null, null, $path, null, $tab);
			
		} else {
			return parent::executeExportAction($request, $objects, $filter, $tab, $objectsFileNamePart, $noValidation);
		}
	}

	/**
	 * Test whether the tar binary is available.
	 * @return boolean|array Boolean true if available otherwise
	 *  an array with an error message.
	 */
	function checkForTar()
	{
		$tarBinary = Config::getVar('cli', 'tar');
		if (empty($tarBinary) || !is_executable($tarBinary)) {
			$result = array(
				array('manager.plugins.tarCommandNotFound')
			);
		} else {
			$result = true;
		}
		return $result;
	}

	/**
	 * Create a tar archive.
	 * @param $targetPath string
	 * @param $targetFile string
	 * @param $sourceFiles array
	 */
	function tarFiles($targetPath, $targetFile, $sourceFiles)
	{
		assert((bool) $this->checkForTar());

		// GZip compressed result file.
		$tarCommand = Config::getVar('cli', 'tar') . ' -czf ' . escapeshellarg($targetFile);

		// Do not reveal our internal export path by exporting only relative filenames.
		$tarCommand .= ' -C ' . escapeshellarg($targetPath);

		// Do not reveal our webserver user by forcing root as owner.
		$tarCommand .= ' --owner 0 --group 0 --';

		// Add each file individually so that other files in the directory will not be included.
		foreach ($sourceFiles as $sourceFile) {
			assert(dirname($sourceFile) . '/' === $targetPath);
			if (dirname($sourceFile) . '/' !== $targetPath) continue;
			$tarCommand .= ' ' . escapeshellarg(basename($sourceFile));
		}
		// Execute the command.
		exec($tarCommand);
	}

	/**
	 * @see PubObjectsExportPlugin::depositXML()
	 *
	 * @param $object Submission.
	 * @param $context Context
	 * @param $filename string Export XML filename
	 */
	function depositXML($object, $context, $filename)
	{
		// Open file.
		assert(is_readable($filename));
		$fh = fopen($filename, 'rb');

		// Catch file-opening errors.
		if (!$fh) {
			throw new ErrorException(__("plugins.importexport.k10Plus.error.noFile"));
		} else {

			// Get settings.
			$username = $this->getSetting($context->getId(), 'username');
			$password = $this->getSetting($context->getId(), 'password');
			$folderId = $this->getSetting($context->getId(), 'folderId');
			$ftpServer = $this->getSetting($context->getId(), 'serverAddress');
			$ftpPort = $this->getSetting($context->getId(), 'port') === "" ? "" : $this->getSetting($context->getId(), 'port');

			// We use the client url tool to transfer the XML via SFTP.
			$ch = curl_init("sftp://@" . $ftpServer . $folderId . "/" . basename($filename));
			curl_setopt($ch, CURLOPT_USERPWD, $username . ":" . $password);

			// Set port if it exists.
			if (!empty($ftpPort)) {
				curl_setopt($ch, CURLOPT_PORT, $ftpPort);
			}

			// Set options: prepare upload, include header, return transfer as a string, use SFTP.
			curl_setopt($ch, CURLOPT_UPLOAD, true);
			curl_setopt($ch, CURLOPT_HEADER, true);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
			curl_setopt($ch, CURLOPT_PROTOCOLS, CURLPROTO_SFTP);

			// Set options: filesize, XML file.
			curl_setopt($ch, CURLOPT_INFILE, $fh);
			curl_setopt($ch, CURLOPT_INFILESIZE, filesize($filename));
			curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
			curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

			// Execute.
			$response = curl_exec($ch);

			// Get information.
			$curlInfo = curl_getInfo($ch);
			$uploadContentLength = $curlInfo['upload_content_length'];

			// Handle response and data transmission errors.
			if ($response === false) {
				$curlError = curl_error($ch);
				throw new ErrorException($curlError);
			}

			if ($uploadContentLength != filesize($filename) || $uploadContentLength == 0 || filesize($filename) == 0) {
				$curlError = curl_error($ch);
				throw new ErrorException($curlError);
			}
	
			// Close.
			curl_close($ch);
			fclose($fh);
		}

		// If no error was thrown, update object.
		$this->updateDepositStatus($object, EXPORT_STATUS_REGISTERED, '');
		return true;
	}	
	
	/**
	 * Update deposit status in DB, update object and set new failed message.
	 * @param $object The object getting deposited.
	 * @param $status Deposit status of the object.
	 * @param $errors String.
	 */
	function updateDepositStatus($object, $status, $error) {

		// Remove the old failure message, if it exists.
		$object->setData($this->getFailedMsgSettingName(), null);
		$object->setData($this->getDepositStatusSettingName(), $status);
		if (!empty($error)) {
			$object->setData($this->getFailedMsgSettingName(), $error);
		}
		$this->updateObject($object);
	}


	/**
	 * Get request failed message setting name.
	 * @return string
	 */
	function getFailedMsgSettingName() {
		return $this->getPluginSettingsPrefix().'::failedMsg';
	}

	/**
	 * Fetch saved status message for failed deposits to show.
	 * @copydoc PubObjectsExportPlugin::getStatusMessage()
	 */
	function getStatusMessage($request) {

		// If the failure occured on request and the message was saved return that message.
		$articleId = $request->getUserVar('articleId');
		$submissionDao = DAORegistry::getDAO('SubmissionDAO');
		$article = $submissionDao->getByid($articleId);
		$failedMsg = $article->getData($this->getFailedMsgSettingName());
		if (!empty($failedMsg)) {
			return $failedMsg;
		}
	}

	/**
	 * Get a list of additional setting names that should be stored with the objects.
	 * In this case: the failed message.
	 * @return array
	 */
	protected function _getObjectAdditionalSettings() {
		return array_merge(parent::_getObjectAdditionalSettings(), array(
			$this->getFailedMsgSettingName(),
		));
	}

	/**
	 * Necessary to open modal to read failed-messages.
	 * @copydoc PubObjectsExportPlugin::getStatusActions()
	 * @param $pubObject The published submission.
	 */
	function getStatusActions($pubObject) {
		$request = Application::get()->getRequest();
		$dispatcher = $request->getDispatcher();
		return array(
			EXPORT_STATUS_FAILED =>
				new LinkAction(
					'failureMessage',
					new AjaxModal(
						$dispatcher->url(
							$request, ROUTE_COMPONENT, null,
							'grid.settings.plugins.settingsPluginGridHandler',
							'manage', null, array('plugin' => 'K10PlusExportPlugin', 'category' => 'importexport', 'verb' => 'statusMessage',
							'articleId' => $pubObject->getId())
						),
						__('plugins.importexport.k10Plus.status.failed'),
						'failureMessage'
					),
					__('plugins.importexport.k10Plus.status.failed')
				)
		);
	}

	/**
	 * Mark selected submissions as registered.
	 * Update failed message name and status.
	 * @param $context Context
	 * @param $objects array Array of published submissions.
	 */
	function markRegistered($context, $objects) {
		foreach ($objects as $object) {

			// Remove the old failure message, if it exists.
			$object->setData($this->getFailedMsgSettingName(), null);
			$object->setData($this->getDepositStatusSettingName(), EXPORT_STATUS_MARKEDREGISTERED);
			$this->updateObject($object);
		}
	}

	/**
	 * Mark selected submissions as unregistered.
	 * @param $context Context
	 * @param $objects array Array of published submissions.
	 */
	function markUnregistered($context, $objects) {
		foreach ($objects as $object) {

			// Remove the old failure message, if it exists.
			$object->setData($this->getFailedMsgSettingName(), null);
			$object->setData($this->getDepositStatusSettingName(), EXPORT_STATUS_MARKEDUNREGISTERED);
			$this->updateObject($object);
		}
	}

	/**
	 * Retrieve all unregistered submissions.
	 * @param $context The Journal.
	 * @return array
	 */
	function getUnregisteredArticles($context)
	{
		// Retrieve all published submissions that have not yet been registered.
		$submissionDao = DAORegistry::getDAO('SubmissionDAO');
		$articlesFactoryUnregistered = $submissionDao->getExportable(
			$context->getId(),
			null,
			null,
			null,
			null,
			$this->getPluginSettingsPrefix() . '::status',
			EXPORT_STATUS_MARKEDUNREGISTERED,
			null
		);

		$articlesFactoryFailed = $submissionDao->getExportable(
			$context->getId(),
			null,
			null,
			null,
			null,
			$this->getPluginSettingsPrefix() . '::status',
			EXPORT_STATUS_FAILED,
			null
		);

		$articlesFactoryNotDeposited = $submissionDao->getExportable(
			$context->getId(),
			null,
			null,
			null,
			null,
			$this->getPluginSettingsPrefix() . '::status',
			EXPORT_STATUS_NOT_DEPOSITED,
			null
		);

		// Get all articles that are not deposited or marked unregistered.
		$articlesUnregistered = $articlesFactoryUnregistered->toArray();
		$articlesNotDeposited = $articlesFactoryNotDeposited->toArray();
		$articlesFailed = $articlesFactoryFailed->toArray();
		$articles = array_merge($articlesUnregistered, $articlesNotDeposited, $articlesFailed);
		return $articles;
	}
}
