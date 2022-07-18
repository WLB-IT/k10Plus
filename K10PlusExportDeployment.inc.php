<?php

/**
 * @file plugins/importexport/k10Plus/K10PlusExportDeployment.inc.php
 *
 * @brief Base class configuring the k10Plus export process to an
 * application's specifics.
 */

// XML attributes
define('K10PLUS_XMLNS', 'http://www.loc.gov/MARC21/slim');
define('K10PLUS_XMLNS_XSI', 'http://www.w3.org/2001/XMLSchema-instance');
define('K10PLUS_XSI_SCHEMALOCATION', 'http://www.loc.gov/standards/marcxml/schema/MARC21slim.xsd');

class K10PlusExportDeployment
{
	/** @var Context The current import/export context */
	var $_context;

	/** @var Plugin The current import/export plugin */
	var $_plugin;

	/**
	 * Get the plugin cache
	 * @return PubObjectCache
	 */
	function getCache()
	{
		return $this->_plugin->getCache();
	}

	/**
	 * Constructor
	 * @param $context Context
	 * @param $plugin PubObjectsExportPlugin
	 */
	function __construct($context, $plugin)
	{
		$this->setContext($context);
		$this->setPlugin($plugin);
	}

	//
	// Deployment items for subclasses to override
	//
	/**
	 * Get the root element name
	 * @return string
	 */
	function getRootElementName()
	{
		return 'record';
	}

	/**
	 * Get the namespace URN
	 * @return string
	 */
	function getNamespace()
	{
		return K10PLUS_XMLNS;
	}

	/**
	 * Get the schema instance URN
	 * @return string
	 */
	function getXmlSchemaInstance()
	{
		return K10PLUS_XMLNS_XSI;
	}

	/**
	 * Get the schema location URL
	 * @return string
	 */
	function getXmlSchemaLocation()
	{
		return K10PLUS_XSI_SCHEMALOCATION;
	}

	/**
	 * Get the schema filename.
	 * @return string
	 */
	function getSchemaFilename()
	{
		return $this->getXmlSchemaLocation();
	}

	//
	// Getter/setters
	//
	/**
	 * Set the import/export context.
	 * @param $context Context
	 */
	function setContext($context)
	{
		$this->_context = $context;
	}

	/**
	 * Get the import/export context.
	 * @return Context
	 */
	function getContext()
	{
		return $this->_context;
	}

	/**
	 * Set the import/export plugin.
	 * @param $plugin ImportExportPlugin
	 */
	function setPlugin($plugin)
	{
		$this->_plugin = $plugin;
	}

	/**
	 * Get the import/export plugin.
	 * @return ImportExportPlugin
	 */
	function getPlugin()
	{
		return $this->_plugin;
	}
}
