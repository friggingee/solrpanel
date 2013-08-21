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
class Tx_Solrpanel_Controller_QuerylogController extends Tx_Extbase_MVC_Controller_ActionController {
	protected $logDataArray;
	protected $goodLogDataArray;
	protected $maxCount;
	protected $dateRange = array(
		'from' => 0,
		'to' => 0
	);
	protected $hostBlacklist = array(
		'googlebot.com',
		'search.msn.com',
		'wowrack.com',
		'yasni.de',
	);

	/**
	 * querylogdataRepository
	 *
	 * @var Tx_Solrpanel_Domain_Repository_QuerylogdataRepository
	 */
	protected $querylogdataRepository;

	/**
	 * is called before every action call
	 *
	 * @return void
	 */
	public function initializeAction() {
		$this->requestArguments = $this->request->getArguments();
	}

	/**
	 * Main action
	 *
	 * @return void
	 */
	protected function indexAction() {
		$this->getMaxDateRange();
		if (!empty($this->requestArguments['loadGraph'])) {
			$this->querylogdataRepository = t3lib_div::makeInstance('Tx_Solrpanel_Domain_Repository_QuerylogdataRepository');
			$this->getCurrentDateRange();
			$this->getQueryLog();
		}

		$this->view
			->assign('dateRange', $this->dateRange)
			->assign('maxCount', $this->maxCount)
			->assign('goodLogData', $this->goodLogDataArray)
			->assign('logData', $this->logDataArray);
	}

	protected function getQueryLog() {
		$tsFrom = $this->dateRange['tsFrom'];
		$tsTo = $this->dateRange['tsTo'];

 		$logDataArray = $GLOBALS['TYPO3_DB']->exec_SELECTgetRows(
				// fields
			'tx_solr_statistics.keywords AS keyword,
			count(tx_solr_statistics.uid) AS wordcount'
				// FROM
			, 'tx_solr_statistics'
				// WHERE
			, 'tx_solr_statistics.tstamp BETWEEN ' . $tsFrom . ' AND ' . $tsTo
				// GROUP BY
			, 'tx_solr_statistics.keywords'
				// ORDER BY
			, 'wordcount DESC'
				// LIMIT
			, ''
		);

		$this->querylogData = t3lib_div::makeInstance('Tx_Solrpanel_Domain_Model_Querylogdata');
		$this->querylogData->setQuerylogdata(json_encode($logDataArray));
		$this->querylogData->setTstampFrom($tsFrom);
		$this->querylogData->setTstampTo($tsTo);
		$this->querylogdataRepository->add($this->querylogData);

		$highestRankingItem = reset($logDataArray);
		$this->maxCount = $highestRankingItem['wordcount'];
		$relevancyThreshold = (intval($this->maxCount) * 0.2);
		if ($relevancyThreshold < 10) {
			$relevancyThreshold = 10;
		}
		foreach ($logDataArray as $id => $logData) {
			if ($logData['wordcount'] >= $relevancyThreshold) {
				$keywordRequestIPs = $this->getSourceForRequest($logData['keyword'], $tsFrom, $tsTo);
				$badRequestCount = $this->matchIPsAgainstBlacklist($keywordRequestIPs);

				$logData['height'] = round((intval($logData['wordcount']) * 100) / $this->maxCount, 0);
				$logData['leftHeight'] = 100 - $logData['height'];
				$logData['badHeight'] = round((intval($badRequestCount) * 100) / $this->maxCount, 0);
				$logData['badLeftHeight'] = 100 - $logData['badHeight'];
				$logData['badRequestCount'] = $badRequestCount;
				$logData['goodRequestCount'] = $logData['wordcount'] - $badRequestCount;

				if (array_key_exists(strtolower($logData['keyword']), $this->requestArguments['getWordStats']) ) {
					$logData['wordstats'] = $this->getWordStats($logData['keyword'], $tsFrom, $tsTo);
				}

				$this->logDataArray[$id+1] = $logData;

				$goodLogData[$logData['goodRequestCount']]['goodRequestCount'] = $logData['goodRequestCount'];
				$goodLogData[$logData['goodRequestCount']]['goodHeight'] = round((intval($logData['goodRequestCount']) * 100) / $this->maxCount, 0);
				$goodLogData[$logData['goodRequestCount']]['goodLeftHeight'] = 100 - $goodLogData[$logData['goodRequestCount']]['goodHeight'];
				$goodLogData[$logData['goodRequestCount']]['keyword'] = $logData['keyword'];
				$goodLogData[$logData['goodRequestCount']]['prevPos'] = $id + 1;
				krsort($goodLogData);

				unset($logData);
			}
		}

		$this->goodLogDataArray = $goodLogData;

		unset($goodLogData);
		unset($logDataArray);
	}

	protected function getSourceForRequest($keyword, $tsFrom, $tsTo) {
		$sourceIPs = $GLOBALS['TYPO3_DB']->exec_SELECTgetRows(
			'tx_solr_statistics.ip,
			count(tx_solr_statistics.uid) AS count'
			, 'tx_solr_statistics'
			, 'tx_solr_statistics.tstamp BETWEEN ' . $tsFrom . ' AND ' . $tsTo . '
				AND tx_solr_statistics.keywords = "' . $keyword . '"'
			, 'tx_solr_statistics.ip'
			, 'count DESC'
		);

		return $sourceIPs;
	}

	protected function matchIPsAgainstBlacklist(array $IParray) {
		$blacklistedIPs = 0;

		foreach ($IParray as $key => $IPdata) {
			$host = gethostbyaddr($IPdata['ip']);
			preg_match('/\.(.*?)$/', $host, $tld);

			if (in_array($tld[1], $this->hostBlacklist)) {
				$blacklistedIPs++;
			}
		}

		return $blacklistedIPs;
	}

	protected function getMaxDateRange() {
		$minMaxDate = reset($GLOBALS['TYPO3_DB']->exec_SELECTgetRows('min(tstamp) AS mintstamp, max(tstamp) AS maxtstamp', 'tx_solr_statistics'));

		$yearRange = range(date('Y', $minMaxDate['mintstamp']), date('Y', $minMaxDate['maxtstamp']));
		$this->dateRange['yearRange'] = array_combine($yearRange, $yearRange);
		$this->dateRange['min'] = date('d.m.Y', $minMaxDate['mintstamp']);
		$this->dateRange['max'] = date('d.m.Y', $minMaxDate['maxtstamp']);
	}

	protected function getCurrentDateRange() {
		$userDateFrom = $this->requestArguments['dateRange']['from'];
		$userDateTo = $this->requestArguments['dateRange']['to'];

		if (!checkdate($userDateTo['month'], $userDateTo['day'], $userDateTo['year'])) {
			list($userDateTo['day'], $userDateTo['month'], $userDateTo['year']) = explode('.', date('d.m.Y'));
		}
		if (!checkdate($userDateFrom['month'], $userDateFrom['day'], $userDateFrom['year'])) {
			list($userDateFrom['day'], $userDateFrom['month'], $userDateFrom['year']) = explode('.', date('d.m.Y', mktime(0, 0, 0, intval($userDateTo['month'] - 1), $userDateTo['day'], $userDateTo['year'])));
		}

		$this->dateRange['from'] = $userDateFrom;
		$this->dateRange['to'] = $userDateTo;
		$this->dateRange['tsFrom'] = mktime(0, 0, 0, $userDateFrom['month'], $userDateFrom['day'], $userDateFrom['year']);
		$this->dateRange['tsTo'] = mktime(0, 0, 0, $userDateTo['month'], $userDateTo['day'], $userDateTo['year']);
	}

	protected function getWordStats($word, $tsFrom, $tsTo) {
		$maxCount = 0;
		for ($cur = $tsFrom; $cur <= $tsTo; $cur = $cur+86400) {
			$wordCount = reset($GLOBALS['TYPO3_DB']->exec_SELECTgetRows(
				'count(uid) AS wordcount'
				, 'tx_solr_statistics'
				, 'tstamp BETWEEN ' . $cur . ' AND ' . ($cur+86400) . '
					AND keywords = "' . $word . '"'
			));
			$wordCount = $wordCount['wordcount'];
			if ($maxCount < intval($wordCount)) {
				$maxCount = intval($wordCount);
			}
			$wordStats[date('d.m.Y', $cur)]['wordcount'] = $wordCount;
		}

		foreach($wordStats as $key => $dayData) {
			$wordStats[$key]['height'] = round( (intval($dayData['wordcount']) * 100) / $maxCount, 0);
			$wordStats[$key]['leftHeight'] = 100-$wordStats[$key]['height'];
		}

		return $wordStats;
	}
}