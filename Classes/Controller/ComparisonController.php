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
class Tx_Solrpanel_Controller_ComparisonController extends Tx_Extbase_MVC_Controller_ActionController {
	
	/**
	 * Arguments for this request
	 *
	 * @var array
	 */
	protected $requestArguments;

	/**
	 * The complete TypoScript configuration
	 *
	 * @var array
	 */
	protected $tsSetup;

	/**
	 * Solr connection data
	 *
	 * @var array
	 */
	protected $solrConnectionData;

	/**
	 * Solr query runtime modificators defined in TS
	 *
	 * @var array
	 */
	protected $solrQueryMods;

	/**
	 * Solr query options for the comparison queries
	 *
	 * @var array
	 */
	protected $solrQueryOpts;

	/**
	 * Documents to be traced
	 *
	 * @var array
	 */
	protected $traceDoc;

	/**
	 * Score statistics for the used query modifications
	 *
	 * @var array
	 */
	protected $modScore;

	/**
	 * is called before every action call
	 *
	 * @return void
	 */
	public function initializeAction() {
		$this->requestArguments = $this->request->getArguments();

		$this->loadTypoScriptForBEModule();
		$this->getSolrConnectionData();
		$this->getSolrQueryModificators();

			// TODO: make configurable
		$this->solrQueryOpts = array(
			'rows' => '10',
			'fl' => 'title,url,changed,type,id,score'
		);

		$this->createDocTraceColorScheme();
	}

	public function indexAction() {
		$this->getSearchwords();

		if (!empty($this->searchwords)) {
			foreach ($this->searchwords as $searchword) {
				$comparisonChart[$searchword] = $this->getComparisonData($searchword);
			}
		}

		$minModScore = $this->getMinModScore();

		$this->view
			->assign('comparisonChart', $comparisonChart)
			->assign('searchwords', $this->requestArguments['searchwords'])
			->assign('addMods', $this->requestArguments['addMods'])
			->assign('baseURL', $this->tsSetup['config.']['baseURL'])
			->assign('solrQueryMods', $this->solrQueryMods)
			->assign('modScore', $this->modScore)
			->assign('minModScore', $minModScore)
			->assign('traceDoc', $this->traceDoc);
	}

	/**
	 * Loads the TypoScript
	 *
	 * @return void
	 */
	protected function loadTypoScriptForBEModule() {
		require_once(PATH_t3lib . 'class.t3lib_page.php');
		require_once(PATH_t3lib . 'class.t3lib_tstemplate.php');
		require_once(PATH_t3lib . 'class.t3lib_tsparser_ext.php');
		list($page) = t3lib_BEfunc::getRecordsByField('pages', 'pid', 0);
		$pageUid = intval($page['uid']);
		$sysPageObj = t3lib_div::makeInstance('t3lib_pageSelect');
		$rootLine = $sysPageObj->getRootLine($pageUid);
		$tsObj = t3lib_div::makeInstance('t3lib_tsparser_ext');
		$tsObj->tt_track = 0;
		$tsObj->init();
		$tsObj->runThroughTemplates($rootLine);
		$tsObj->generateConfig();

		$this->tsSetup = $tsObj->setup;
	}

	protected function getSolrConnectionData() {
		$this->solrConnectionData = $this->tsSetup['plugin.']['tx_solr.']['solr.'];

		$this->solrConnectionData['command'] = 'select';
	}

	protected function getSolrQueryModificators() {
		if (is_array($this->requestArguments['addMods'])) {

			foreach ($this->requestArguments['addMods'] as $modData) {

				if (!empty($modData['modKey']) && !empty($modData['modValue'])) {
					$userSolrQueryMods[$modData['modKey']] = $modData['modValue'];

					$currentRequestArguments[$modData['modKey']]['modKey'] = $modData['modKey'];
					$currentRequestArguments[$modData['modKey']]['modValue'] = $modData['modValue'];
				}
			}

			$this->requestArguments['addMods'] = $currentRequestArguments;
		}

		$this->solrQueryMods = $this->tsSetup['plugin.']['tx_solr.']['search.']['query.'];

		if (is_array($userSolrQueryMods)) {
			$this->solrQueryMods += $userSolrQueryMods;
		}

		foreach ($this->solrQueryMods as $modKey => $mod) {
			if (empty($mod)) {
				unset($this->solrQueryMods[$modKey]);
			} else {
				switch ($modKey) {
					case 'fields':
						$this->solrQueryMods['qf'] = str_replace(array(' ',','), array('+',''), $mod);
						unset($this->solrQueryMods[$modKey]);
					break;
					case 'boostFunction':
					case 'bf':
						$this->solrQueryMods['bf'] = $mod;
					break;
					case 'sort':
						$this->solrQueryMods['sort'] = urlencode($mod);
					break;
					default:
					break;
				}
			}
		}
	}

	protected function buildSolrRequestUrl($searchword) {
		$requestUrl = $this->solrConnectionData['scheme'] . '://';
		$requestUrl .= $this->solrConnectionData['host'];
		$requestUrl .= ':' . $this->solrConnectionData['port'];
		$requestUrl .= $this->solrConnectionData['path'];
		$requestUrl .= $this->solrConnectionData['command'];
		$requestUrl .= '?q=' . $searchword;

		if (count($this->solrQueryOpts) > 0) {
			foreach ($this->solrQueryOpts as $option => $value) {
				$requestUrl .= '&' . $option . '=' . urlencode($value);
			}
		}

		$requestUrl .= '&wt=json';

		return $requestUrl;
	}
	
	protected function getSearchwords() {
		$searchwords = $this->requestArguments['searchwords'];
		
		if (!empty($searchwords)) {
			$this->searchwords = explode(',', $this->requestArguments['searchwords']);

			foreach ($this->searchwords as $key => $word) {
				$this->searchwords[$key] = urlencode(trim($word));
			}
		}
	}
	
	protected function getComparisonData($word) {
		$requestUrl = $this->buildSolrRequestUrl($word);

		$wordChart = array();

		$plainResults = file_get_contents($requestUrl);
		$wordChart['plain'] = $this->formatResults($plainResults);

		if (count($this->solrQueryMods) > 0) {
			$queryModCombs = $this->getAllElemCombs($this->solrQueryMods);

			foreach ($queryModCombs as $key => $comb) {
				$paramString = '';
				$queryModString = '';

				foreach ($comb as $param => $value) {
					$paramString .= '+' . $param;
					$queryModString .= '&' . $param . '=' . $value;
				}

				if (!empty($queryModString)) {
					$queryModRequestUrl = $requestUrl . $queryModString;
					$wordChart[$paramString] = $this->formatResults(file_get_contents($queryModRequestUrl), $paramString);
				}
			}
		}
		
		return $wordChart;
	}

	protected function formatResults($jsonString, $modType = 'plain') {
		$dataArray = json_decode($jsonString, true);

		$solrStats = array(
			'resultTypes' => array(),
			'dateRange' => array()
		);

		foreach ($dataArray['response']['docs'] as $id => $docData) {
			$changed = $docData['changed'];
			$changed = preg_replace('/[a-z]/iS', ' ', $changed);
			$changed = rtrim($changed);
			$changed = explode(' ', $changed);
			$changedDate = explode('-', $changed[0]);
			$changedTime = explode(':', $changed[1]);
			$changed = $changedDate[2] . '.' . $changedDate[1] . '.' . $changedDate[0] . ' ' . $changedTime[0] . ':' . $changedTime[1];
			$changedTs = mktime($changedTime[0], $changedTime[1], $changedTime[2], $changedDate[1], $changedDate[2], $changedDate[0]);

			$dataArray['response']['docs'][$id]['traceCol'] = $this->traceDoc[urlencode($dataArray['responseHeader']['params']['q'])][$docData['id']];
			$dataArray['response']['docs'][$id]['changed'] = $changed;

				// create Stats
			$dataArray['stats']['resultTypes'][$docData['type']] += 1;
			$dataArray['stats']['dates'][$changedTs] = $changed;

			if (intval($dataArray['response']['docs'][$id]['traceCol']) > 0) {
				$this->tracedInResults[$modType]['points'] += intval($id + 1);
				$this->tracedInResults[$modType]['hits']++;
			}
		}

		krsort($dataArray['stats']['dates']);

		$dataArray['stats']['dateRange']['from'] = end($dataArray['stats']['dates']);
		$dataArray['stats']['dateRange']['to'] = reset($dataArray['stats']['dates']);

		unset($dataArray['stats']['dates']);

		return $dataArray;
	}

	protected function createDocTraceColorScheme() {
		$traceDocs = $this->requestArguments['traceDoc'];

		foreach ($traceDocs as $word => $traceTriggerData) {
			foreach ($traceTriggerData as $id => $trace) {
				if ($trace) {
					$this->traceDoc[$word][$id] = mt_rand(150,200) . ',' . mt_rand(150,200) . ',' . mt_rand(150,200);
				}
			}
		}
	}

	protected function getMinModScore() {
		$minModScore = $this->solrQueryOpts['rows'] * count($this->searchwords);
		foreach ($this->tracedInResults as $mod => $modData) {
			$this->modScore[$mod]['points'] = $modData['points'];
			$this->modScore[$mod]['score'] = $score = round( ($modData['points'] / $modData['hits']), 2);
			$this->modScore[$mod]['hits'] = $modData['hits'];

			if ($score < $minModScore) {
				$minModScore = $score;
			}
		}

		return $minModScore;
	}

	/**
	 * Finds all element combinations of an array
	 *
	 * @see http://commons.oreilly.com/wiki/index.php/PHP_Cookbook/Arrays#Finding_All_Element_Combinations_of_an_Array
	 *
	 * @param array $array The array to be analyzed
	 * @return array $results A two-dimensional array containing the results
	 */
	protected static function getAllElemCombs(array $array) {
			// initialize by adding the empty set
		$results = array(array( ));

		foreach ($array as $key => $element)
			foreach ($results as $combination)
				array_push($results, array_merge(array($key => $element), $combination));

		return $results;
	}
}