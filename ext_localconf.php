<?php
if (!defined ('TYPO3_MODE')) 	die ('Access denied.');

$extConf = unserialize($GLOBALS['TYPO3_CONF_VARS']['EXT']['extConf'][$_EXTKEY]);

// register the clearCachePostProc hook
require_once(t3lib_extMgm::extPath($_EXTKEY).'class.tx_static404.php');
$TYPO3_CONF_VARS['SC_OPTIONS']['t3lib/class.t3lib_tcemain.php']['clearCachePostProc'][]='tx_static404->clearCachePostProc';

if (empty($extConf['disablePageNotFound_handling'])) {
	// tell TYPO3 to serve our cached 404 when a page is not found
	$TYPO3_CONF_VARS["FE"]["pageNotFound_handling"] = 'READFILE:typo3temp/tx_static404-'.t3lib_div::getIndpEnv('TYPO3_HOST_ONLY').'.html';
	$TYPO3_CONF_VARS["FE"]["pageNotFound_handling_statheader"] = 'HTTP/1.0 404 Not Found';
}