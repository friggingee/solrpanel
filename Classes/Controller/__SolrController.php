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
class Tx_Solrpanel_Controller_SolrController extends Tx_Extbase_MVC_Controller_ActionController {
	/**
	 * indexfailsRepository
	 *
	 * @var Tx_Solrpanel_Domain_Repository_IndexfailsRepository
	 */
	protected $indexfailsRepository;

	/**
	 * Container for indexing failures array
	 *
	 * @var array
	 */
	protected $indexingFailures;

	/**
	 * The current record object
	 *
	 * @var Tx_Solrpanel_Domain_Model_Indexfails
	 */
	protected $currentFailRecordObject;

	/**
	 * Arguments for this request
	 *
	 * @var array
	 */
	protected $requestArguments;

	/**
	 * is called before every action call
	 *
	 * @return void
	 */
	public function initializeAction() {
		$this->requestArguments = $this->request->getArguments();

		$this->indexfailsRepository = t3lib_div::makeInstance('Tx_Solrpanel_Domain_Repository_IndexfailsRepository');
		
		$indexFails = $this->indexfailsRepository->findAll();

			// if there are records stored in the database, pick the one there is
			// and set it as the currentFailRecordObjectObject
		if ($indexFails) {
			foreach ($indexFails as $failRecord) {
				$this->currentFailRecordObject = $failRecord;
				$this->indexingFailures = json_decode($failRecord->getIndexstatus(), true);
			}
		}

		if (empty($this->indexingFailures)) {
			$this->getIndexingFailures();
		}

		t3lib_div::debug($this->requestArguments,'$this->requestArguments');
	}

	public function indexAction() {
		$this->view->assign('indexingFailures', $this->indexingFailures);
	}

	public function checkForContentAction() {
		foreach ($this->indexingFailures as $key => $page) {
			$pageUrl = 'http://spielwarenmesse:internet@test.spielwarenmesse.de/' . 'index.php?id=' . $page['uid'];
			$html = file_get_contents($pageUrl);
			preg_match_all('/<!--TYPO3SEARCH_begin-->(.*?)<!--TYPO3SEARCH_end-->/msx', $html, $matches);

			unset($matches[0]);
			$matches = $matches[1];
			if (empty($matches[0])) {
				$this->indexingFailures[$key]['content'] = 'Page has no content.';
			} else {
				$firstMatch = $matches[0];
				unset($matches[0]);

				$pageContentFromFirstMatch = strip_tags(html_entity_decode(preg_replace('/(\n|\t|  )/', '', str_replace('|', '/', $firstMatch))));
				if (!empty($pageContentFromFirstMatch)) {
					$this->indexingFailures[$key]['content'] = 'Recheck page';
				} else {
					$this->indexingFailures[$key]['content'] = 'Page has no content.';
				}
			}
		}

		$this->currentFailRecordObject->setIndexstatus(json_encode($this->indexingFailures));
		$this->indexfailsRepository->update($this->currentFailRecordObject);

		$this->redirect('index');
	}

	public function reloadIndexStatusAction() {
		$this->redirect('index');
	}

	public function workOnRecordsAction() {
		call_user_func(array($this, reset(array_keys($this->requestArguments['callAction']))));

#		$this->redirect('index');
	}

	public function forceReindexingAction() {
		foreach ($this->indexingFailures as $key => $failRecord) {
			if (empty($failRecord['content'])) {
				$getContentFor[$key] = $failRecord;
			}
		}

#		$this->checkForContentAction
		# else {
		#		if ($failRecord['content'] === 'Recheck page') {
		#			$this->requestArguments['reindex'][$failRecord['uid']] = 1;
		#		}
		#	}
		#}

		#$this->forceReindexing();
	}

	protected function forceReindexing() {
		foreach ($this->requestArguments['reindex'] as $id => $status) {
			if ($status) {
				$pageIds[] = $id;
			}
		}

		$pageIds = implode(',', $pageIds);

	$q=	$GLOBALS['TYPO3_DB']->UPDATEquery(
			'tx_solr_indexqueue_item',
			'item_uid IN ' . $pageIds,
			array(
				'errors' => '', 
				'indexed' => 0
			)
		);

		t3lib_div::debug($q,'$q');

#		$this->view->render('index');
#		$this->redirect('index');
	}

	protected function getIndexingFailures() {
		$pages = $GLOBALS['TYPO3_DB']->exec_SELECTgetRows(
			'tx_solr_indexqueue_item.item_uid,
			pages.title',
			'tx_solr_indexqueue_item
				JOIN pages 
					ON tx_solr_indexqueue_item.item_uid = pages.uid',
			'tx_solr_indexqueue_item.indexed = 0',
			'',
			'tx_solr_indexqueue_item.item_uid ASC',
			'10'
		);

		foreach ($pages as $id => $page) {
			$this->indexingFailures[$id]['uid'] = $page['item_uid'];
			$this->indexingFailures[$id]['title'] = $page['title'];
		}

		$indexfails = t3lib_div::makeInstance('Tx_Solrpanel_Domain_Model_Indexfails');
		$indexfails->setIndexstatus(json_encode($this->indexingFailures));
		$this->currentFailRecordObject = $indexfails;
		$this->indexfailsRepository->add($indexfails);
	}
}