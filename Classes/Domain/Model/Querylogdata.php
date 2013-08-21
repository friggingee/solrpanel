<?php

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2012
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
class Tx_Solrpanel_Domain_Model_Querylogdata extends Tx_Extbase_DomainObject_AbstractEntity {

	/**
	 * Querylogdata
	 *
	 * @var string
	 */
	protected $querylogdata;

	/**
	 * TstampFrom
	 *
	 * @var int
	 */
	protected $tstampFrom;
	
	/**
	 * TstampTo
	 *
	 * @var int
	 */
	protected $tstampTo;

	/**
	 * __construct
	 *
	 * @return void
	 */
	public function __construct() {

	}

	/**
	 * Returns the Querylogdata
	 *
	 * @return string $querylogdata
	 */
	public function getQuerylogdata() {
		return $this->querylogdata;
	}

	/**
	 * Sets the Querylogdata
	 *
	 * @param string $querylogdata
	 * @return void
	 */
	public function setQuerylogdata($querylogdata) {
		$this->querylogdata = $querylogdata;
	}

	/**
	 * Returns the TstampFrom
	 *
	 * @return int $tstampFrom
	 */
	public function getTstampFrom() {
		return $this->tstampFrom;
	}

	/**
	 * Sets the TstampFrom
	 *
	 * @param int $tstampFrom
	 * @return void
	 */
	public function setTstampFrom($tstampFrom) {
		$this->tstampFrom = $tstampFrom;
	}

	/**
	 * Returns the TstampTo
	 *
	 * @return int $tstampTo
	 */
	public function getTstampTo() {
		return $this->tstampTo;
	}

	/**
	 * Sets the TstampTo
	 *
	 * @param int $tstampTo
	 * @return void
	 */
	public function setTstampTo($tstampTo) {
		$this->tstampTo = $tstampTo;
	}
}
?>