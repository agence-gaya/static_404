<?php
use \TYPO3\CMS\Core\Utility\GeneralUtility;
/***************************************************************
*  Copyright notice
*
*  (c) 2015 GAYA La Nouvelle Agence <contact@gaya.fr>
*  All rights reserved
*
*  This script is part of the TYPO3 project. The TYPO3 project is
*  free software; you can redistribute it and/or modify
*  it under the terms of the GNU General Public License as published by
*  the Free Software Foundation; either version 2 of the License, or
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
 * Class used for clearCachePostProc hook
 *
 * @author	Rémy Daniel <contact@gaya.fr>
 * @package	TYPO3
 * @subpackage	tx_static404
 */
class tx_static404 {

	/**
	 * Name of the temporary file stored in typo3temp
	 * If you change this name, be sure to update the rewrite rule in htaccess
	 * @var string
	 */
	static public $TEMP_FILENAME_PREFIX = 'tx_static404';

	private $extConf = null;

	public function __construct() {
		// load the extension configuration
		$this->extConf = unserialize($GLOBALS['TYPO3_CONF_VARS']['EXT']['extConf']['static_404']);
	}

	/**
	 * This function will be called by the clearCachePostProc hook
	 *
	 * @param array $params
	 * @param object $pObj
	 * @return  void
	 */
	public function clearCachePostProc($params, $pObj) {

		/** @var \TYPO3\CMS\Core\Authentication\BackendUserAuthentication $beUser */
		$beUser =  $GLOBALS['BE_USER'];

		if ($params['cacheCmd'] === 'all' || $params['cacheCmd'] === 'pages') {
			try {
				$this->start();
			} catch (Exception $e) {
				GeneralUtility::sysLog($e->getMessage(), 'static_404', 3);
				$beUser->simplelog($e->getMessage(), 'static_404', 2);
			}
		}
	}

	/**
	 * This function will be called by the clearCachePostProc hook
	 *
	 * @throws \TYPO3\CMS\Core\Error\Exception
	 * @return  void
	 */
	private function start() {

		/** @var \TYPO3\CMS\Core\Authentication\BackendUserAuthentication $beUser */
		$beUser =  $GLOBALS['BE_USER'];

		if (!is_array($this->extConf) || empty($this->extConf['all404Pids'])) {
			throw new \TYPO3\CMS\Core\Error\Exception('The extension is not properly configured. Please visit the configuration tab in the extension manager');
		}

		// extract each page id
		$pageUids = GeneralUtility::intExplode(',', $this->extConf['all404Pids'], true);
		if (empty($pageUids) || !$pageUids[0]) {
			throw new \TYPO3\CMS\Core\Error\Exception('Unable to find a valid 404 pid in the configuration. Please visit the configuration tab in the extension manager');
		}

		$defaultAlreadyGenerated = false;
		foreach ($pageUids as $pageUid) {
			// get all registered sys_domain for this page
			foreach ($this->allDomainRecords($pageUid) as $domain) {

				// build the url from which we will fetch the content
				$fetchUrl = $domain.'/index.php?id='.$pageUid;

				// fetch the page content
				$getUrlReport = array(
					'error' => 0,
					'message' => ''
				);
				$content = GeneralUtility::getUrl($fetchUrl, 0, false, $getUrlReport);

				if ($content !== false) {

					// write to cache file
					$urlParts = parse_url($domain);
					$host = $this->tokenizeHost($urlParts['host']);
					$tempFilename = $host.'.html';

					$error = GeneralUtility::writeFileToTypo3tempDir(PATH_site.'typo3temp/'.self::$TEMP_FILENAME_PREFIX.'/'.$tempFilename, $content);
					if (!empty($error)) {
						throw new \TYPO3\CMS\Core\Error\Exception($error);
					}

					$beUser->simplelog('Update the 404 cache for "'.$fetchUrl.'" to "'.$tempFilename.'"', 'static_404', 0);

					// The first pageUid is used as pid for the default 404
					if (!$defaultAlreadyGenerated) {
						$tempFilename = 'default.html';
						$error = GeneralUtility::writeFileToTypo3tempDir(PATH_site.'typo3temp/'.self::$TEMP_FILENAME_PREFIX.'/'.$tempFilename, $content);
						if (!empty($error)) {
							throw new \TYPO3\CMS\Core\Error\Exception($error);
						}
						$defaultAlreadyGenerated = true;
						$beUser->simplelog('Update the 404 cache for "'.$fetchUrl.'" to "'.$tempFilename.'"', 'static_404', 0);
					}

				} elseif ($getUrlReport['error'] != 0) {
					$beUser->simplelog('Error while fetching 404 page ("'.$fetchUrl.'"): '.$getUrlReport['message'], 'static_404', 2);
				}
			}
		}
	}

	/**
	 * Get all domains for a given page id.
	 * It handles the case where a page have more than one domain,
	 * which is the case on some multilingual websites.
	 * HTTPS configuration is detected both at page level or site-wide.
	 *
	 * @param  integer $pageId
	 * @return array array of domains, with protocol
	 */
	private function allDomainRecords($pageId) {

		$domains = array();

		$rootLine = \TYPO3\CMS\Backend\Utility\BackendUtility::BEgetRootLine($pageId);

		// Checks alternate domains
		if (count($rootLine) > 0) {
			// Define protocol used for this page

			/** @var \TYPO3\CMS\Frontend\Page\PageRepository $sysPage */
			$sysPage = GeneralUtility::makeInstance('TYPO3\CMS\Frontend\Page\PageRepository');
			$page = (array)$sysPage->getPage($pageId);
			$protocol = 'http';
			if ($page['url_scheme'] == 2 || ($page['url_scheme'] == 0 && GeneralUtility::getIndpEnv('TYPO3_SSL'))) {
				$protocol = 'https';
			}

			// Find every sys_domain records configured in page rootline
			foreach ($rootLine as $row) {
				$constraint = ($this->extConf['excludeDomainsWithRedirect'] ? ' AND redirectTo=\'\' AND hidden=0' : ' AND hidden=0');
				$dRec = \TYPO3\CMS\Backend\Utility\BackendUtility::getRecordsByField('sys_domain', 'pid', $row['uid'], $constraint, '', 'sorting');

				if (is_array($dRec)) {
					foreach ($dRec as $dRecord) {
						$domain = rtrim($dRecord['domainName'], '/');
						if (!$domain) {
							continue;
						}
						$domains[] = $protocol . '://' . $domain;
					}
				}
			}
		}

		// If no sys_domain found, we use TYPO3_SITE_URL
		if (count($domains) === 0) {
			$domains[] = rtrim(GeneralUtility::getIndpEnv('TYPO3_SITE_URL'), '/');
		}

		return $domains;
	}

	/**
	 * Return a tokenized version of the host.
	 * Useful to shorten the hostname.
	 * @param $host
	 * @return string
	 */
	private static function tokenizeHost($host) {
		if (strlen($host) < 50) {
			return $host;
		}

		return substr($host, 0, 40) . GeneralUtility::shortMD5($host, 10);
	}

	/**
	 * Render the 404 page
	 *
	 * Can be used by another plugin to render the 404 page
	 *
	 * @throws \RuntimeException
	 * @see disablePageNotFound_handling
	 */
	public function render404AndExit() {
		$host = self::tokenizeHost(GeneralUtility::getIndpEnv('TYPO3_HOST_ONLY'));
		$readFile = GeneralUtility::getFileAbsFileName('typo3temp/'.self::$TEMP_FILENAME_PREFIX.'/'.$host.'.html');
		if (!@is_file($readFile)) {
			throw new \TYPO3\CMS\Core\Error\Exception('Enable to find static 404 file. Try to clear Typo3 cache.');
		}
		header('HTTP/1.0 404 Not Found', null, 404);
		$fileContent = GeneralUtility::getUrl($readFile);
		$fileContent = str_replace('###CURRENT_URL###', GeneralUtility::getIndpEnv('REQUEST_URI'), $fileContent);
		$fileContent = str_replace('###REASON###', '', $fileContent);
		echo $fileContent;
		exit;
	}

}

if (defined('TYPO3_MODE') && $GLOBALS['TYPO3_CONF_VARS'][TYPO3_MODE]['XCLASS']['ext/static_404/class.tx_static404.php'])	{
	include_once($GLOBALS['TYPO3_CONF_VARS'][TYPO3_MODE]['XCLASS']['ext/static_404/class.tx_static404.php']);
}