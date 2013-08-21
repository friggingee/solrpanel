<?php
if (!defined('TYPO3_MODE')) {
	die ('Access denied.');
}



if (TYPO3_MODE === 'BE') {

	/**
	* Registers a Backend Module
	*/
	Tx_Extbase_Utility_Extension::registerModule(
		$_EXTKEY,	// Extension-Key
		'tools',	 // Make module a submodule of 'tools'
		'solrpanel',	// Submodule key
		'',						// Position
		array(
			'Indexerrors' => 'index',
			'Comparison' => 'index',
			'Querylog' => 'index'
		),
		array(
			'access' => 'user,group',
			'icon'   => 'EXT:' . $_EXTKEY . '/ext_icon.gif',
			'labels' => 'LLL:EXT:' . $_EXTKEY . '/Resources/Private/Language/locallang_solrpanel.xml',
		)
	);

}


t3lib_extMgm::addStaticFile($_EXTKEY, 'Configuration/TypoScript', 'SolrPanel');

t3lib_extMgm::addLLrefForTCAdescr('tx_solrpanel_domain_model_indexerrors', 'EXT:solrpanel/Resources/Private/Language/locallang_csh_tx_solrpanel_domain_model_indexerros.xml');
t3lib_extMgm::allowTableOnStandardPages('tx_solrpanel_domain_model_indexerrors');
$TCA['tx_solrpanel_domain_model_indexerrors'] = array(
	'ctrl' => array(
		'title'	=> 'LLL:EXT:solrpanel/Resources/Private/Language/locallang_db.xml:tx_solrpanel_domain_model_indexerrors',
		'label' => 'company',
		'tstamp' => 'tstamp',
		'crdate' => 'crdate',
		'cruser_id' => 'cruser_id',
		'dividers2tabs' => TRUE,
		'versioningWS' => 2,
		'versioning_followPages' => TRUE,
		'origUid' => 't3_origuid',
		'languageField' => 'sys_language_uid',
		'transOrigPointerField' => 'l10n_parent',
		'transOrigDiffSourceField' => 'l10n_diffsource',
		'delete' => 'deleted',
		'enablecolumns' => array(
			'disabled' => 'hidden',
			'starttime' => 'starttime',
			'endtime' => 'endtime',
		),
		'dynamicConfigFile' => t3lib_extMgm::extPath($_EXTKEY) . 'Configuration/TCA/Indexerrors.php',
		'iconfile' => t3lib_extMgm::extRelPath($_EXTKEY) . 'Resources/Public/Icons/tx_solrpanel_domain_model_indexerrors.gif'
	),
);

t3lib_extMgm::addLLrefForTCAdescr('tx_solrpanel_domain_model_querylogdata', 'EXT:solrpanel/Resources/Private/Language/locallang_csh_tx_solrpanel_domain_model_querylogdata.xml');
t3lib_extMgm::allowTableOnStandardPages('tx_solrpanel_domain_model_querylogdata');
$TCA['tx_solrpanel_domain_model_querylogdata'] = array(
	'ctrl' => array(
		'title'	=> 'LLL:EXT:solrpanel/Resources/Private/Language/locallang_db.xml:tx_solrpanel_domain_model_querylogdata',
		'label' => 'company',
		'tstamp' => 'tstamp',
		'crdate' => 'crdate',
		'cruser_id' => 'cruser_id',
		'dividers2tabs' => TRUE,
		'versioningWS' => 2,
		'versioning_followPages' => TRUE,
		'origUid' => 't3_origuid',
		'languageField' => 'sys_language_uid',
		'transOrigPointerField' => 'l10n_parent',
		'transOrigDiffSourceField' => 'l10n_diffsource',
		'delete' => 'deleted',
		'enablecolumns' => array(
			'disabled' => 'hidden',
			'starttime' => 'starttime',
			'endtime' => 'endtime',
		),
		'dynamicConfigFile' => t3lib_extMgm::extPath($_EXTKEY) . 'Configuration/TCA/Querylogdata.php',
		'iconfile' => t3lib_extMgm::extRelPath($_EXTKEY) . 'Resources/Public/Icons/tx_solrpanel_domain_model_querylogdata.gif'
	),
);
?>