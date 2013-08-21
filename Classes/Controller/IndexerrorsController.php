<?php

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2012 Grigori Prokhorov <grigori.prokhorov@dkd.de>
 *
 *  All rights reserved
 *
 *  This script is part of the TYPO3 project. The TYPO3 project is
 *  free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 3 of the License, or
 *  (at your option) any later version.
 *
 *  The GNU General Public License can be found at
 *  http://www.gnu.org/copyleft/gpl.html.
 *
 *  This script is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  This copyright notice MUST APPEAR in all copies of the script!
 ***************************************************************/


/**
 *
 *
 * @package solrpanel
 * @license http://www.gnu.org/licenses/lgpl.html GNU Lesser General Public License, version 3 or later
 *
 */
class Tx_Solrpanel_Controller_IndexerrorsController extends Tx_Extbase_MVC_Controller_ActionController {
	/**
	 * indexerrorsRepository
	 *
	 * @var Tx_Solrpanel_Domain_Repository_IndexerrorsRepository
	 */
	protected $indexerrorsRepository;

	/**
	 * The indexing errors array
	 *
	 * @var array
	 */
	protected $indexingErrorsArray;

	/**
	 * is called before every action call
	 *
	 * @return void
	 */
	public function initializeAction() {
		$this->requestArguments = $this->request->getArguments();
		if (count($this->requestArguments['callAction']) > 1) {
			throw new Exception('You have provided more than one callAction. This is not allowed.', 1342952836);
		}

		$this->indexerrorsRepository = t3lib_div::makeInstance('Tx_Solrpanel_Domain_Repository_IndexerrorsRepository');
	}

	/**
	 * Main action
	 *
	 * @return void
	 */
	protected function indexAction() {
		$reloadIndexingFailures = FALSE;

		switch (reset(array_keys($this->requestArguments['callAction']))) {
			case 'getIndexingFailures':
				$reloadIndexingFailures = TRUE;
			default:
				$this->getIndexingFailures($reloadIndexingFailures);
			break;
		}

		$this->view->assign('indexingFailures', $this->indexingErrorsArray);
	}

	/**
	 * (Re-)loads current indexqueue status:
	 *
	 *
	 * ==PROCESS DESCRIPTION begin
	 *
	 * IF reloading is required
	 *   get the current status from the indexqueue table
	 * ELSE if the class property with the indexing errors is empty
	 *   try to fetch the index status from the repository
	 *
	 *   IF the repository has any data for us
	 *     extract the current status from the indexerrors object
	 *     create the class property from the encoded data in the indexerrors object
	 *   ELSE
	 *     As we don't have any data available - get the current status from the indexqueue table
	 *
	 * set the current index status
	 *
	 * ==PROCESS DESCRIPTION end
	 *
	 * @param bool $reloadIndexingFailures If true forces the index status data to be updated
	 * @return void
	 */
	protected function getIndexingFailures($reloadIndexingFailures = FALSE) {
			//If reloading of indexqueue items is forced, fetch items disregarding
			// the possible presence of an item-set in the database or class property.
		if (TRUE === $reloadIndexingFailures) {
			$this->getCurrentIndexStatus();

			// If there is already some data in the class property, assume it's the
			// current data and do nothing... but if not...
		} elseif (empty($this->indexingErrorsArray)) {
				// try to get the current index status from the repository
			$currentIndexStatusObject = reset($this->indexerrorsRepository->findAll());

				// if the repository returns anything, assume this to be the current
				// data and extract it from the indexerrors object
			if (!empty($currentIndexStatusObject)) {
				$currentIndexStatus = $currentIndexStatusObject->getIndexstatus();
				$this->indexingErrorsArray = json_decode($currentIndexStatus, TRUE);

				// if the repository doesn't return anything, fetch the items directry from
				// the database
			} else {
				$this->getCurrentIndexStatus();
			}
		}

			// set the found data to be the current
		$this->setCurrentIndexStatus();
	}

	/**
	 * Invokes the collection of indexqueue-data from the database and assigns the found
	 * data to the class property $indexingErrorsArray
	 *
	 * @return void
	 */
	protected function getCurrentIndexStatus() {
		$this->indexingErrorsArray = $this->getIndexqueueData();
	}

	/**
	 * Writes the current index status to the database
	 *
	 * ==PROCESS DESCRIPTION begin
	 *
	 * make a new instance of the indexerrors object
	 * set the indexstatus property of the indexerrors object to the json-encoded-version of the
	 *   class property $indexingErrorsArray
	 * add the indexerrors object to the repository
	 *
	 * ==PROCESS DESCRIPTION end
	 *
	 * @return void
	 */
	protected function setCurrentIndexStatus() {
		$currentIndexStatusObject = t3lib_div::makeInstance('Tx_Solrpanel_Domain_Model_Indexerrors');
		$currentIndexStatusObject->setIndexstatus(json_encode($this->indexingErrorsArray));
		$this->indexerrorsRepository->add($currentIndexStatusObject);
	}

	/**
	 * Get table rows from the solr indexqueue_item-table which have indexing errors.
	 *
	 * ==PROCESS DESCRIPTION begin
	 *
	 * Get the item_uid field from the indexqueue-table for all items with errors
	 *   join the title field from the pages-table on all found items
	 *
	 * traverse through the found rows and construct a better maintainable erray
	 *
	 * cleanup the database-result variable
	 *
	 * return the array of errorneous indexqueue items
	 *
	 * ==PROCESS DESCRIPTION end
	 *
	 * @return array $indexingErros The array of errorneous indexqueue items
	 */
	protected function getIndexqueueData() {
		$indexqueueData = $GLOBALS['TYPO3_DB']->exec_SELECTgetRows(
				// fields
			'tx_solr_indexqueue_item.item_uid,
			pages.title'
				// FROM
			, 'tx_solr_indexqueue_item
				JOIN pages
					ON tx_solr_indexqueue_item.item_uid = pages.uid'
				// WHERE
			, 'tx_solr_indexqueue_item.errors != \'\''
				// GROUP BY
			, ''
				// ORDER BY
			, 'tx_solr_indexqueue_item.item_uid ASC'
				// LIMIT
			, '11'
		);

			// Traverse trough the found rows and construct a better maintainable
			// array of erroneous indexqueue-items. (This structure comes in handy later on.)
		foreach ($indexqueueData as $rowId => $page) {
			$indexingErrors[$rowId] = array(
				'uid' => $page['item_uid']
				, 'title' => $page['title']
			);
		}

			// Cleanup $indexqueueData
		unset($indexqueueData);

		return $indexingErrors;
	}
}