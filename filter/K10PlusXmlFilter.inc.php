<?php

/**
 * @file plugins/importexport/k10Plus/filter/K10PlusXmlFilter.inc.php
 *
 * @brief Class that converts an Article to a MARC 21 XML document.
 */

import('lib.pkp.plugins.importexport.native.filter.NativeExportFilter');

class K10PlusXmlFilter extends NativeExportFilter
{
	/**
	 * Constructor
	 * @param $filterGroup FilterGroup
	 */
	function __construct($filterGroup)
	{
		// Import PPN DAO.
		import('plugins.generic.ppnForm.classes.PPNDAO');
		$ppnDao = new PPNDAO();
		DAORegistry::registerDAO('PPNDAO', $ppnDao);
		
		$this->setDisplayName('K10Plus XML export');
		parent::__construct($filterGroup);
	}

	//
	// Implement template methods from PersistableFilter
	//
	/**
	 * @copydoc PersistableFilter::getClassName()
	 */
	function getClassName()
	{
		return 'plugins.importexport.k10Plus.filter.K10PlusXmlFilter';
	}

	//
	// Implement template methods from Filter
	//
	/**
	 * @see Filter::process()
	 * @param $pubObjects array Array of Submissions.
	 * @return DOMDocument
	 */
	function &process(&$pubObjects)
	{

		// Create the XML document
		$doc = new DOMDocument('1.0', 'utf-8');
		$doc->preserveWhiteSpace = false;
		$doc->formatOutput = true;
		$deployment = $this->getDeployment();
		$plugin = $deployment->getPlugin();
		$cache = $plugin->getCache();

		// Get date time of transaction for control fields: yyyymmddhhmmss.f
		date_default_timezone_set('Europe/Berlin');
		$dateTime = new DateTime();
		$dateTransaction = substr($dateTime->format('YmdHis.u'), 0, -5);

		// Create the root node.
		$rootNode = $this->createRootNode($doc);
		$doc->appendChild($rootNode);

		// Iterate over articles.
		foreach ($pubObjects as $pubObject) {
			if (is_a($pubObject, 'Submission')) {

				// Article.
				$article = $pubObject;

				// Consider cache.
				if (!$cache->isCached('articles', $article->getId())) {
					$cache->add($article, null);
				}

				// Get article information.
				$publication = $article->getCurrentPublication();
				$pubId = $publication->getId();
				$articleId = $article->getId();
				$datePublishedArticle =  date("Y", strtotime($publication->getData('datePublished')));
				$articleGalleyDao = DAORegistry::getDAO('ArticleGalleyDAO');
				$galleyLocale = '';
				$articleGalleys = $articleGalleyDao->getByPublicationId($pubId)->toArray();
				foreach ($articleGalleys as $articleGalley) {
					if ($articleGalley->getLabel() === 'PDF') {
						$galleyLocale = $articleGalley->getLocale();
					}
				}

				// Get language code of PDF.
				switch ($galleyLocale) {
					case 'fr_FR':
						$languageCode = 'fre';
					case 'en_US':
						$languageCode = 'eng';
					default:
						$languageCode = 'ger';
				}

				// Create leader element.
				$rootNode->appendChild($this->createLeaderNode($doc, $deployment, $article));

				// Create controlfield nodes.
				$controlFieldCodes = ['001', '003', '005', '007', '008'];
				foreach ($controlFieldCodes as $code) {
					$rootNode->appendChild($this->createControlFieldNode($doc, $deployment, $code, $datePublishedArticle, $dateTransaction, $languageCode, $articleId));
				}

				// Create all datafields needed for article.
				$this->createArticleDatafields($doc, $deployment, $article, $rootNode, $languageCode, $plugin);
			}
		}
		return $doc;
	}

	/**
	 * Create and return the root node.
	 * @param $doc DOMDocument
	 * @return DOMElement
	 */
	function createRootNode($doc)
	{
		$deployment = $this->getDeployment();
		$rootNode = $doc->createElementNS($deployment->getNamespace(), $deployment->getRootElementName());
		$rootNode->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:xsi', $deployment->getXmlSchemaInstance());
		$rootNode->setAttribute('xsi:schemaLocation', $deployment->getNamespace() . ' ' . $deployment->getSchemaFilename());
		return $rootNode;
	}
	/**
	 * Creates leader element of the MARC 21 XML.
	 * @param $doc DOMDocument.
	 * @param $deployment NativeImportExportDeployment.
	 * @param $objectType Submission.
	 * @return DOMElement
	 */
	function createLeaderNode($doc, $deployment, $objectType)
	{
		$leader = "00000naa a2200000uc 4500";
		$leaderNode = $doc->createElementNS($deployment->getNamespace(), 'leader', $leader);
		return $leaderNode;
	}

	/**
	 * Creates controlfield of the MARC 21 XML.
	 * @param $doc DOMDocument.
	 * @param $deployment NativeImportExportDeployment.
	 * @param $code Type of controlfield code.
	 * @param $datePublished Publishing date of issue/article.
	 * @param $dateTransaction Date of XML-file transmission.
	 * @param $languageCode Language of the PDF.
	 * @param $id Either issue or article ID.
	 * @return DOMElement
	 */
	function createControlFieldNode($doc, $deployment, $code, $datePublished, $dateTransaction, $languageCode, $id)
	{
		// Get extra info for 008 field.
		$year = date("y");
		$month = date("m");
		$day = date("d");

		switch ($code) {
			case '001':
				$controlField = $id;
				break;
			case '003':
				$controlField = 'DE-24';
				break;
			case '005':
				$controlField = $dateTransaction;
				break;
			case '007':
				$controlField = 'cr||||||||||||';
				break;
			case '008':
				$controlField = $year . $month . $day . 's' . $datePublished . '||||gw |||| ||||| ||||| ' . $languageCode . '||';
				break;
		}

		// Set the node.
		$controlFieldNode = $doc->createElementNS($deployment->getNamespace(), 'controlfield', $controlField);
		$controlFieldNode->setAttribute('tag', $code);
		return $controlFieldNode;
	}
	/**
	 * Create all datafields and subfields needed for an article.
	 * @param $doc DOMDocument.
	 * @param $deployment NativeImportExportDeployment.
	 * @param $issue Submission object.
	 * @param $rootNode Root node to append to.
	 * @return DOMElement
	 */
	function createArticleDatafields($doc, $deployment, $article, $rootNode, $languageCode)
	{
		// Get base info.
		$context = $deployment->getContext();
		$publication = $article->getCurrentPublication();
		$pubId = $publication->getId();
		$subId = $publication->getData('submissionId');

		// Create datafield node 024: doi.
		$doi = $article->getStoredPubId('doi');
		if (!$doi) {
			throw new ErrorException(__('plugins.importexport.k10Plus.error.noDoi', array('param' => $subId)));
		} else {
			// Create.
			$datafieldNode024 = $this->createDatafieldNode($deployment, $doc, "024", "7", " ");
			$subfieldNode024_2 = $this->createSubfieldNode($doc, $deployment, "2", 'doi');
			$subfieldNode024_a = $this->createSubfieldNode($doc, $deployment, "a", $doi);

			// Append.
			$datafieldNode024->appendChild($subfieldNode024_2);
			$datafieldNode024->appendChild($subfieldNode024_a);
			$rootNode->appendChild($datafieldNode024);
		}

		// Create datafield node 041: lang code.
		$datafieldNode041 = $this->createDatafieldNode($deployment, $doc, "041", " ", " ");
		$subfieldNode041_a = $this->createSubfieldNode($doc, $deployment, "a", $languageCode);

		// Append.
		$datafieldNode041->appendChild($subfieldNode041_a);
		$rootNode->appendChild($datafieldNode041);

		// Create datafield node 100 or 110: main entry - personal name or corporation.
		$allContributors = $this->getContributorArray($article);
		foreach ($allContributors as $authorId => $abbrev) {

			// Special case: in an interview, the interviewed is the main author.
			if ($abbrev === 'ive') {
				$this->createAuthorDatafield($doc, $deployment, $rootNode, $authorId, '100');

				// Remove from all contributors array.
				unset($allContributors[$authorId]);
				break;
			} else if (($abbrev === 'au') && !in_array('ive', $allContributors)) {
				$this->createAuthorDatafield($doc, $deployment, $rootNode, $authorId, '100');

				// Remove from all contributors array.
				unset($allContributors[$authorId]);
				break;
			} else if (($abbrev === 'kor')) {
				$this->createAuthorDatafield($doc, $deployment, $rootNode, $authorId, '110');

				// Remove from all contributors array.
				unset($allContributors[$authorId]);
				break;
			}
		}

		// Create datafield node 245: title statement.
		$this->createTitleStatementDatafield($doc, $deployment, $rootNode, $article, $pubId);

		// Create datafield node 264: production statement.
		$copyrightYear = $article->getCopyrightYear();

		if (!$copyrightYear) {
			throw new ErrorException(__('plugins.importexport.k10Plus.error.noCopyrightyear', array('param' => $subId)));
		} else {

			// Create and append.
			$datafieldNode264 = $this->createDatafieldNode($deployment, $doc, "264", " ", "1");
			$subfieldNode264_c = $this->createSubfieldNode($doc, $deployment, "c", $copyrightYear);
			$datafieldNode264->appendChild($subfieldNode264_c);
			$rootNode->appendChild($datafieldNode264);
		}

		// Use extra function for fixed datafields.
		$this->createFixedDatafields($doc, $deployment, $rootNode, $publication);

		// Special case review or obituary: create datafield nodes 655 and text 787.
		$keywordDao = DAORegistry::getDAO('SubmissionKeywordDAO');
		$keywords = $keywordDao->getKeywords($pubId, ['de_DE']);
		$keywordsString = implode(",", $keywords['de_DE']);

		// Create review-specific fields and append.
		if ($keywordsString && str_contains($keywordsString, '/gnd/4049712-4')) {
			$this->createGenreDatafield($doc, $deployment, $rootNode, $publication, $subId, 'review');
		}

		// Create obituary-specific fields and append.
		if ($keywordsString && str_contains($keywordsString, '/gnd/4128540-2')) {
			$this->createGenreDatafield($doc, $deployment, $rootNode, $publication, $subId, 'obituary');
		}

		// Create interview-specific fields and append.
		if ($keywordsString && str_contains($keywordsString, '/gnd/4027503-6')) {
			$this->createGenreDatafield($doc, $deployment, $rootNode, $publication, $subId, 'interview');
		}

		// Create datafield node 650: metadata/keywords.
		// Only normed keywords are allowed.
		foreach ($keywords as $locale => $localeKeywords) {
			foreach ($localeKeywords as $keyword) {
				if ($keyword && str_contains($keyword, 'gnd')) {
					$keywordGndId = explode('/gnd/', $keyword)[1];

					// Create only when keyword != "Rezension" as requested by BSZ.
					if (!str_contains($keyword, '/gnd/4049712-4')) {
						$datafieldNode650 = $this->createDatafieldNode($deployment, $doc, "650", "0", "7");
						$subfieldNode650_0 = $this->createSubfieldNode($doc, $deployment, "0", '(DE-588)' . $keywordGndId);

						// Append.
						$datafieldNode650->appendChild($subfieldNode650_0);
						$rootNode->appendChild($datafieldNode650);
					}
				} else {
					throw new ErrorException(__('plugins.importexport.k10Plus.error.noKeywordGnd', array('param1' => $keyword, 'param2' => $subId)));
				}
			}
		}

		// Create datafield node 700 and 710: added entry - personal name or corporation.
		foreach ($allContributors as $authorId => $abbrev) {
			if ($abbrev === 'koradbw') {
				$this->createAuthorDatafield($doc, $deployment, $rootNode, $authorId, '710');
			} else {
				$this->createAuthorDatafield($doc, $deployment, $rootNode, $authorId, '700');
			}
		}

		// Create datafield node 773: Host item entry.
		$zdbId = $context->getData('zdbId');
		if (!$zdbId) {
			throw new ErrorException(__('plugins.importexport.k10Plus.error.noZDBId', array('param' => $subId)));
		} else {

			// Create.
			$datafieldNode773 = $this->createDatafieldNode($deployment, $doc, "773", "0", "8");
			$subfieldNode773_i = $this->createSubfieldNode($doc, $deployment, "i", 'Enthalten in');
			$subfieldNode773_w = $this->createSubfieldNode($doc, $deployment, "w", '(DE-600)' . $zdbId);

			// Append.
			$datafieldNode773->appendChild($subfieldNode773_i);
			$datafieldNode773->appendChild($subfieldNode773_w);
			$rootNode->appendChild($datafieldNode773);
		}

		// Create second datafield node 773: Host item entry.
		$issueId = $publication->getData('issueId');
		$issueDao = DAORegistry::getDAO('IssueDAO');
		$issue = $issueDao->getById($issueId, $context->getId());
		$volume = $issue->getVolume();
		$number = $issue->getNumber();
		$pages = $article->getPages();

		// Create.
		if (!$copyrightYear && !$pages) {
			throw new ErrorException(__('plugins.importexport.k10Plus.error.noPagesOrCopyrightyear', array('param' => $subId)));
		} else {
			$secondDatafieldNode773 = $this->createDatafieldNode($deployment, $doc, "773", "1", "8");
			$subfieldNode773_g1 = $this->createSubfieldNode($doc, $deployment, "g", 'volume:' . $volume);
			$subfieldNode773_g2 = $this->createSubfieldNode($doc, $deployment, "g", 'year:' . $copyrightYear);
			$subfieldNode773_g3 = $this->createSubfieldNode($doc, $deployment, "g", 'number:' . $number);
			$subfieldNode773_g4 = $this->createSubfieldNode($doc, $deployment, "g", 'pages:' . $pages);

			// Append.
			$secondDatafieldNode773->appendChild($subfieldNode773_g1);
			$secondDatafieldNode773->appendChild($subfieldNode773_g2);
			$secondDatafieldNode773->appendChild($subfieldNode773_g3);
			$secondDatafieldNode773->appendChild($subfieldNode773_g4);
			$rootNode->appendChild($secondDatafieldNode773);
		}

		// Create datafield node 856: electronic location and access.
		$request = Application::get()->getRequest();
		$url = $request->url($context->getPath(), 'article', 'view', array($article->getBestId()));

		// Create.
		if (!$url) {
			throw new ErrorException(__('plugins.importexport.k10Plus.error.noUrl', array('param' => $subId)));
		} else {
			$datafieldNode856 = $this->createDatafieldNode($deployment, $doc, "856", "4", "0");
			$subfieldNode856_u = $this->createSubfieldNode($doc, $deployment, "u", $url);
			$subfieldNode856_x = $this->createSubfieldNode($doc, $deployment, "x", 'Verlag');
			$subfieldNode856_z = $this->createSubfieldNode($doc, $deployment, "z", 'kostenfrei');
			$subfieldNode856_3 = $this->createSubfieldNode($doc, $deployment, "3", 'Volltext');

			// Append.
			$datafieldNode856->appendChild($subfieldNode856_u);
			$datafieldNode856->appendChild($subfieldNode856_x);
			$datafieldNode856->appendChild($subfieldNode856_z);
			$datafieldNode856->appendChild($subfieldNode856_3);
			$rootNode->appendChild($datafieldNode856);
		}

		// Create second datafield node 856: electronic location and access.
		$secondDatafieldNode856 = $this->createDatafieldNode($deployment, $doc, "856", "4", "0");
		$secondSubfield856_u = $this->createSubfieldNode($doc, $deployment, "u", 'https://doi.org/' . $doi);
		$secondSubfield856_x = $this->createSubfieldNode($doc, $deployment, "x", 'Resolving-System');
		$secondSubfield856_z = $this->createSubfieldNode($doc, $deployment, "z", 'kostenfrei');
		$secondSubfield856_3 = $this->createSubfieldNode($doc, $deployment, "3", 'Volltext');

		// Append.
		$secondDatafieldNode856->appendChild($secondSubfield856_u);
		$secondDatafieldNode856->appendChild($secondSubfield856_x);
		$secondDatafieldNode856->appendChild($secondSubfield856_z);
		$secondDatafieldNode856->appendChild($secondSubfield856_3);
		$rootNode->appendChild($secondDatafieldNode856);

		return $rootNode;
	}

	/**
	 * Generate the datafield node.
	 * @param $deployment NativeImportExportDeployment.
	 * @param $doc DOMElement.
	 * @param $tag string 'tag' attribute.
	 * @param $ind1 string 'ind1' attribute.
	 * @param $ind2 string 'ind2' attribute.
	 * @return DOMElement
	 */
	function createDatafieldNode($deployment, $doc, $tag, $ind1, $ind2)
	{
		$datafieldNode = $doc->createElementNS($deployment->getNamespace(), 'datafield');
		$datafieldNode->setAttribute('tag', $tag);
		$datafieldNode->setAttribute('ind1', $ind1);
		$datafieldNode->setAttribute('ind2', $ind2);
		return $datafieldNode;
	}

	/**
	 * Generate the subfield node.
	 * @param $doc DOMElement.
	 * @param $deployment NativeImportExportDeployment.
	 * @param $code string 'code' attribute.
	 * @param $value string Element text value.
	 * 
	 */
	function createSubfieldNode($doc, $deployment, $code, $value)
	{
		$subfieldNode = $doc->createElementNS($deployment->getNamespace(), 'subfield');
		$subfieldNode->appendChild($doc->createTextNode($value));
		$subfieldNode->setAttribute('code', $code);
		return $subfieldNode;
	}

	/**
	 * Generate mostly fixed datafields.
	 * @param $doc DOMElement.
	 * @param $deployment NativeImportExportDeployment.
	 * @param $rootNode Root node to append to.
	 * @param $publication Pub object.
	 * @return DOMElement
	 * 
	 */
	public function createFixedDatafields($doc, $deployment, $rootNode, $publication)
	{
		// Create datafield node 336: content type.
		$datafieldNode336 = $this->createDatafieldNode($deployment, $doc, "336", " ", " ");
		$subfieldNode336_a = $this->createSubfieldNode($doc, $deployment, "a", 'Text');
		$subfieldNode336_b = $this->createSubfieldNode($doc, $deployment, "b", 'txt');
		$subfieldNode336_2 = $this->createSubfieldNode($doc, $deployment, "2", 'rdacontent');
		$datafieldNode336->appendChild($subfieldNode336_a);
		$datafieldNode336->appendChild($subfieldNode336_b);
		$datafieldNode336->appendChild($subfieldNode336_2);
		$rootNode->appendChild($datafieldNode336);

		// Create datafield node 337: media type.
		$datafieldNode337 = $this->createDatafieldNode($deployment, $doc, "337", " ", " ");
		$subfieldNode337_a = $this->createSubfieldNode($doc, $deployment, "a", 'Computermedien');
		$subfieldNode337_b = $this->createSubfieldNode($doc, $deployment, "b", 'c');
		$subfieldNode337_2 = $this->createSubfieldNode($doc, $deployment, "2", 'rdamedia');
		$datafieldNode337->appendChild($subfieldNode337_a);
		$datafieldNode337->appendChild($subfieldNode337_b);
		$datafieldNode337->appendChild($subfieldNode337_2);
		$rootNode->appendChild($datafieldNode337);

		// Create datafield node 338: carrier type.
		$datafieldNode338 = $this->createDatafieldNode($deployment, $doc, "338", " ", " ");
		$subfieldNode338_a = $this->createSubfieldNode($doc, $deployment, "a", 'Online-Ressource');
		$subfieldNode338_b = $this->createSubfieldNode($doc, $deployment, "b", 'cr');
		$subfieldNode338_2 = $this->createSubfieldNode($doc, $deployment, "2", 'rdacarrier');
		$datafieldNode338->appendChild($subfieldNode338_a);
		$datafieldNode338->appendChild($subfieldNode338_b);
		$datafieldNode338->appendChild($subfieldNode338_2);
		$rootNode->appendChild($datafieldNode338);

		// Create datafield node 500: digitalised/scanned resource.
		$digitalPub = $publication->getData('digitalPub');
		if ($digitalPub && $digitalPub == '1') {
			$datafieldNode500 = $this->createDatafieldNode($deployment, $doc, "500", " ", " ");
			$subfieldNode500_a = $this->createSubfieldNode($doc, $deployment, "a", 'Elektronische Reproduktion der Druckausgabe');
			$datafieldNode500->appendChild($subfieldNode500_a);
			$rootNode->appendChild($datafieldNode500);
		}
		// Create second datafield node 500: extra comment.
		$customComment = $publication->getData('customComment');
		if (!empty($customComment)) {
			$datafieldNode500_2 = $this->createDatafieldNode($deployment, $doc, "500", " ", " ");
			$subfieldNode500_a_2 = $this->createSubfieldNode($doc, $deployment, "a", $customComment);
			$datafieldNode500_2->appendChild($subfieldNode500_a_2);
			$rootNode->appendChild($datafieldNode500_2);
		}

		// Create datafield node 506: restrictions on access note.
		$datafieldNode506 = $this->createDatafieldNode($deployment, $doc, "506", "0", " ");
		$subfieldNode506_a = $this->createSubfieldNode($doc, $deployment, "a", 'Open Access');
		$subfieldNode506_e = $this->createSubfieldNode($doc, $deployment, "e", 'Controlled Vocabulary for Access Rights');
		$subfieldNode506_q = $this->createSubfieldNode($doc, $deployment, "q", 'DE-24');
		$subfieldNode506_u = $this->createSubfieldNode($doc, $deployment, "u", 'http://purl.org/coar/access_right/c_abf2');
		$datafieldNode506->appendChild($subfieldNode506_a);
		$datafieldNode506->appendChild($subfieldNode506_e);
		$datafieldNode506->appendChild($subfieldNode506_q);
		$datafieldNode506->appendChild($subfieldNode506_u);
		$rootNode->appendChild($datafieldNode506);

		// Create datafield node 540: terms governing use and reproduction note .
		$licenceURL = $publication->getData('licenseUrl') ? $publication->getData('licenseUrl') : 'https://creativecommons.org/licenses/by/4.0/';
		$context = $deployment->getContext();
		if ($context->getData('licenseUrl')) {
			$licenseOptions = \Application::getCCLicenseOptions();
			if (array_key_exists($context->getData('licenseUrl'), $licenseOptions)) {
				$licenseName = __($licenseOptions[$context->getData('licenseUrl')]);
			} else {
				$licenseName = $context->getData('licenseUrl');
			}
		}

		// Create.
		$datafieldNode540 = $this->createDatafieldNode($deployment, $doc, "540", " ", " ");
		$subfieldNode540_q = $this->createSubfieldNode($doc, $deployment, "q", 'DE-24');
		if ($licenseName) {
			$subfieldNode540_a = $this->createSubfieldNode($doc, $deployment, "a", $licenseName);
		}
		$subfieldNode540_u = $this->createSubfieldNode($doc, $deployment, "u", $licenceURL);

		// Append.
		$datafieldNode540->appendChild($subfieldNode540_q);
		if ($subfieldNode540_a) {
			$datafieldNode540->appendChild($subfieldNode540_a);
		}
		$datafieldNode540->appendChild($subfieldNode540_u);
		$rootNode->appendChild($datafieldNode540);

		return $rootNode;
	}

	/**
	 * Get all contributors and return an array with author ID : abbrev.
	 * @param $article The article object.
	 * @return Array
	 */
	public function getContributorArray($article)
	{
		$contributorArray = [];
		$allContributors = $article->getAuthors();
		foreach ($allContributors as $contributor) {
			$contributorId = $contributor->getId();
			$contributorUserGroup = $contributor->getUserGroup();
			$contributorAbbrev =  strtolower($contributorUserGroup->getData('abbrev', 'de_DE'));
			$contributorArray[$contributorId] = $contributorAbbrev;
		}
		return $contributorArray;
	}

	/**
	 * Create 700 field depending on role of contributor.
	 * @param $doc DOMElement.
	 * @param $deployment NativeImportExportDeployment.
	 * @param $rootNode Root node to append to.
	 * @param $authorId Author ID to be evaluated.
	 * @param $authorDatafieldId string Which datafield should be created: 100, 110, 700, 710.
	 * @return DOMElement
	 * 
	 */
	public function createAuthorDatafield($doc, $deployment, $rootNode, $authorId, $authorDatafieldId)
	{
		// Create datafield: either main author or additional authors/contributors.
		$datafieldNodeAuthor = $this->createDatafieldNode($deployment, $doc, $authorDatafieldId, "1", " ");

		// Get contributor by Id.
		$authorDao = DAORegistry::getDAO('AuthorDAO');
		$contributor = $authorDao->getById($authorId);
		if ($contributor) {

			// Get Infos and create subfields.
			$contributorGivenName = $contributor->getData('givenName')['de_DE'];
			$contributorLastName = $contributor->getData('familyName')['de_DE'];
			$contributorGndId = $contributor->getData('authorGndId');
			$userGroup = $contributor->getUserGroup();

			// Deal with no author: 'autorenlos' in given name field.
			if (trim($contributorGivenName) === 'autorenlos') {
				return;
			}

			// Names.
			if ($contributorLastName) {
				$subfieldNode_a = $this->createSubfieldNode($doc, $deployment, "a", $contributorLastName . ', ' . $contributorGivenName);
			} else {
				$subfieldNode_a = $this->createSubfieldNode($doc, $deployment, "a", $contributorGivenName);
			}
			$datafieldNodeAuthor->appendChild($subfieldNode_a);

			// Add subfield 4 and e depending on contributor role.
			switch (strtolower($userGroup->getData('abbrev', 'de_DE'))) {
				case 'trans':
					$subfieldNode_e = $this->createSubfieldNode($doc, $deployment, "e", 'VerfasserIn');
					$subfieldNode_4 = $this->createSubfieldNode($doc, $deployment, "4", 'trl');
					break;
				case 'ive':
					$subfieldNode_e = $this->createSubfieldNode($doc, $deployment, "e", 'InterviewteR');
					$subfieldNode_4 = $this->createSubfieldNode($doc, $deployment, "4", 'ive');
					break;
				case 'ivr':
					$subfieldNode_e = $this->createSubfieldNode($doc, $deployment, "e", 'InterviewerIn');
					$subfieldNode_4 = $this->createSubfieldNode($doc, $deployment, "4", 'ivr');
					break;
				case 'adbw':
				case 'koradbw':
					$subfieldNode_e = $this->createSubfieldNode($doc, $deployment, "e", 'VerfasserIn des Bezugswerks');
					$subfieldNode_4 = $this->createSubfieldNode($doc, $deployment, "4", 'ant');
					break;
				case 'gef':
					$subfieldNode_e = $this->createSubfieldNode($doc, $deployment, "e", 'GefeierteR');
					$subfieldNode_4 = $this->createSubfieldNode($doc, $deployment, "4", 'hnr');
					break;
				default:
					$subfieldNode_e = $this->createSubfieldNode($doc, $deployment, "e", 'VerfasserIn');
					$subfieldNode_4 = $this->createSubfieldNode($doc, $deployment, "4", 'aut');
					break;
			}
			$datafieldNodeAuthor->appendChild($subfieldNode_e);
			$datafieldNodeAuthor->appendChild($subfieldNode_4);

			// GND Id of author.
			if ($contributorGndId) {
				$contributorGndId = explode('gnd/', $contributorGndId)[1];
				$subfieldNode_0 = $this->createSubfieldNode($doc, $deployment, "0", "(DE-588)" . $contributorGndId);
				$datafieldNodeAuthor->appendChild($subfieldNode_0);
			}

			// Append.
			$rootNode->appendChild($datafieldNodeAuthor);
			return $rootNode;
		}
	}

	/**
	 * Create 245 title statement datafield.
	 * @param $doc DOMElement.
	 * @param $deployment NativeImportExportDeployment.
	 * @param $rootNode Root node to append to.
	 * @param $article Article object   
	 * @param $pubId Publication ID.
	 * @return DOMElement
	 * 
	 */
	public function createTitleStatementDatafield($doc, $deployment, $rootNode, $article, $pubId)
	{

		// Check if it is a review (usually has 'Rezension' as keyword.)
		$keywordDao = DAORegistry::getDAO('SubmissionKeywordDAO');
		$keywords = $keywordDao->getKeywords($pubId, ['de_DE']);
		$keywordsString = implode(",", $keywords['de_DE']);

		// Get title.
		if ($keywordsString && str_contains($keywordsString, '/gnd/4049712-4')) {

			// Change form of title if it is a review.
			$title = '[' . $article->getLocalizedTitle('de_DE') . ']';
		} else {
			$title = $article->getLocalizedTitle('de_DE');
		}

		// Get subtitle.
		$subtitle = $article->getLocalizedSubtitle('de_DE');

		// Get all contributor names, filter, and convert them to right format.
		$allContributors = $article->getAuthors();
		$authorsNamesArray = [];
		foreach ($allContributors as $authorName) {
			if ($authorName) {
				$contributorAbbrev = strtolower($authorName->getUserGroup()->getData('abbrev', 'de_DE'));

				// Include only actual contributors.
				if ($contributorAbbrev === 'au' || $contributorAbbrev === 'trans' || $contributorAbbrev === 'kor'|| $contributorAbbrev === 'ivr') {
					$authorGivenName = $authorName->getData('givenName')['de_DE'];
					$authorLastName = $authorName->getData('familyName')['de_DE'];
					if ($authorGivenName && $authorLastName && $authorGivenName != 'autorenlos') {
						$authorsFullName =  $authorGivenName . ' ' . $authorLastName;
						$authorsNamesArray[] = $authorsFullName;
					} else if ($authorGivenName && $authorGivenName != 'autorenlos') {
						$authorsNamesArray[] = $authorGivenName;
					} else {

						// When no author is given ('autorenlos').
						$authorsNamesArray = '';
					}
				}
			}
		}

		// Create.
		$datafieldNode245 = $this->createDatafieldNode($deployment, $doc, "245", "1", "0");
		$subfieldNode245_a = $this->createSubfieldNode($doc, $deployment, "a", $title);

		// Construct field for $c.
		if (!empty($authorsNamesArray)) {
			$authorsNamesString = count($authorsNamesArray) > 1 ? implode(', ', $authorsNamesArray) : array_shift($authorsNamesArray);
			$subfieldNode245_c = $this->createSubfieldNode($doc, $deployment, "c", $authorsNamesString);
		}

		// Append.
		$datafieldNode245->appendChild($subfieldNode245_a);

		// Create an append of there is a subtitle.
		if ($subtitle) {
			$subfieldNode245_b = $this->createSubfieldNode($doc, $deployment, "b", $subtitle);
			$datafieldNode245->appendChild($subfieldNode245_b);
		}

		// Append if there is an author.
		if ($subfieldNode245_c) {
			$datafieldNode245->appendChild($subfieldNode245_c);
		}
		$rootNode->appendChild($datafieldNode245);
		return $rootNode;
	}


	/**
	 * Generate genre-specific datafields: obituary or review.
	 * @param $doc DOMElement.
	 * @param $deployment NativeImportExportDeployment.
	 * @param $rootNode Root node to append to.
	 * @param $publication Pub object.
	 * @param $subId Submission ID.
	 * @param $genre Obituary or review.
	 * @return DOMElement
	 * 
	 */
	public function createGenreDatafield($doc, $deployment, $rootNode, $publication, $subId, $genre)
	{
		if ($genre == 'obituary') {

			// Create datafield node 655 and its subfield nodes.
			$datafieldNode655 = $this->createDatafieldNode($deployment, $doc, "655", " ", "7");
			$subfieldNode655_a = $this->createSubfieldNode($doc, $deployment, "a", 'Nachruf');
			$subfieldNode655_0 = $this->createSubfieldNode($doc, $deployment, "0", '(DE-588)4128540-2');
			$subfieldNode655_2 = $this->createSubfieldNode($doc, $deployment, "2", 'gnd-content');

			// Append.
			$datafieldNode655->appendChild($subfieldNode655_a);
			$datafieldNode655->appendChild($subfieldNode655_0);
			$datafieldNode655->appendChild($subfieldNode655_2);
			$rootNode->appendChild($datafieldNode655);

		} else if ($genre == 'interview') {

			// Create datafield node 655 and its subfield nodes.
			$datafieldNode655 = $this->createDatafieldNode($deployment, $doc, "655", " ", "7");
			$subfieldNode655_a = $this->createSubfieldNode($doc, $deployment, "a", 'Interview');
			$subfieldNode655_0 = $this->createSubfieldNode($doc, $deployment, "0", '(DE-588)4027503-6');
			$subfieldNode655_2 = $this->createSubfieldNode($doc, $deployment, "2", 'gnd-content');

			// Append.
			$datafieldNode655->appendChild($subfieldNode655_a);
			$datafieldNode655->appendChild($subfieldNode655_0);
			$datafieldNode655->appendChild($subfieldNode655_2);
			$rootNode->appendChild($datafieldNode655);

		} else if ($genre === 'review') {

			// Get PPNs/title information in PPN field.
			$contextId = $deployment->getContext()->getId();
			$ppnDao = DAORegistry::getDAO('PPNDAO');
			$ppnIterator = $ppnDao->getBySubmissionId($subId, $contextId);
			$ppnData = [];
			while ($ppn = $ppnIterator->next()) {
				$ppnData[] = $ppn->getPPN();
			}

			if (!$ppnData) {
				throw new ErrorException(__('plugins.importexport.k10Plus.error.noPPN', array('param' => $subId)));
			} else {

				// Create datafield node 655 and its subfield nodes.
				$datafieldNode655 = $this->createDatafieldNode($deployment, $doc, "655", " ", "7");
				$subfieldNode655_a = $this->createSubfieldNode($doc, $deployment, "a", 'Rezension');
				$subfieldNode655_0 = $this->createSubfieldNode($doc, $deployment, "0", '(DE-588)4049712-4');
				$subfieldNode655_2 = $this->createSubfieldNode($doc, $deployment, "2", 'gnd-content');

				// Append.
				$datafieldNode655->appendChild($subfieldNode655_a);
				$datafieldNode655->appendChild($subfieldNode655_0);
				$datafieldNode655->appendChild($subfieldNode655_2);
				$rootNode->appendChild($datafieldNode655);

				// Create datafield node 787 for each review data that is included. 
				foreach ($ppnData as $singlePPN) {

					// Create and append.
					$datafieldNode787 = $this->createDatafieldNode($deployment, $doc, "787", "0", "8");
					$subfieldNode787_i = $this->createSubfieldNode($doc, $deployment, "i", 'Rezension von');
					$datafieldNode787->appendChild($subfieldNode787_i);

					// Distinguish between title information and ppn.
					if (str_contains($singlePPN, '$')) {
						$explodedPPNInfo = explode('$', $singlePPN);
						foreach ($explodedPPNInfo as $key => $ppnInfo) {

							// Extract $ code.
							$subfieldCode = $ppnInfo[0];

							// Extract PPN information.
							$subfieldPPNInfo = trim(substr($ppnInfo, 1));

							// Create and append.
							if (!empty($subfieldCode)) {
								$subfieldNode787 = $this->createSubfieldNode($doc, $deployment, $subfieldCode, $subfieldPPNInfo);
								$datafieldNode787->appendChild($subfieldNode787);
							}
						}
					} else {
						$subfieldNode787_w = $this->createSubfieldNode($doc, $deployment, "w", '(DE-627)' . trim($singlePPN));
						$datafieldNode787->appendChild($subfieldNode787_w);
					}

				// Append to root node.
				$rootNode->appendChild($datafieldNode787);
				}
			}
		}
		return $rootNode;
	}
}
