<?php
/***************************************************************
*  Copyright notice
*
*  (c) 2012 Rémy Daniel <contact@gaya.fr>
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
	private $tempFilenamePrefix = 'tx_static404-';

	/**
	 * This function will be called by the clearCachePostProc hook
	 *
	 * @param array $params
	 * @param object $pObj
	 * @return  void
	 */
	public function clearCachePostProc($params, $pObj) {

		if ($params['cacheCmd'] === 'all' || $params['cacheCmd'] === 'pages') {
			try {
				$this->start();
			} catch (Exception $e) {
				t3lib_div::syslog($e->getMessage(), 'static_404', 3);
				$GLOBALS['BE_USER']->simplelog($e->getMessage(), 'static_404', 2);
			}
		}
	}

	/**
	 * This function will be called by the clearCachePostProc hook
	 *
	 * @throws t3lib_error_Exception
	 * @return  void
	 */
	private function start() {

		// load the extension configuration
		$extConf = unserialize($GLOBALS['TYPO3_CONF_VARS']['EXT']['extConf']['static_404']);

		if (!is_array($extConf) || empty($extConf['all404Pids'])) {
			throw new t3lib_error_Exception('The extension is not properly configured. Please visit the configuration tab in the extension manager');
		}

		// extract each page id
		$pageUids = t3lib_div::intExplode(',', $extConf['all404Pids'], true);
		if (empty($pageUids) || !$pageUids[0]) {
			throw new t3lib_error_Exception('Unable to find a valid 404 pid in the configuration. Please visit the configuration tab in the extension manager');
		}

		$defaultAlreadyGenerated = false;
		foreach ($pageUids as $pageUid) {

			// get all registered sys_domain for this page
			foreach ($this->allDomainRecords($pageUid) as $domain) {

				// build the url from which we will fetch the content
				$fetchUrl = $domain . '/index.php?id=' . $pageUid;

				// fetch the page content
				$getUrlReport = array(
					'error' => 0,
					'message' => ''
				);
				$content = t3lib_div::getUrl($fetchUrl, 0, false, $getUrlReport);

				if ($content !== false) {

					// write to cache file
					$urlParts = parse_url($domain);
					$tempFilename = 'tx_static404-'.$urlParts['host'].'.html';

					$error = t3lib_div::writeFileToTypo3tempDir(PATH_site.'typo3temp/'.$tempFilename, $content);
					if (!empty($error)) {
						throw new t3lib_error_Exception($error);
					}

					// The first pageUid is used as pid for the default 404
					if (!$defaultAlreadyGenerated) {
						$tempFilename = 'tx_static404-default.html';
						$error = t3lib_div::writeFileToTypo3tempDir(PATH_site.'typo3temp/'.$tempFilename, $content);
						if (!empty($error)) {
							throw new t3lib_error_Exception($error);
						}
						$defaultAlreadyGenerated = true;
					}

					$GLOBALS['BE_USER']->simplelog('Update the 404 cache for "'.$fetchUrl.'" to "'.$tempFilename.'"', 'static_404', 0);

				} elseif ($getUrlReport['error'] != 0) {
					$GLOBALS['BE_USER']->simplelog('Error while fetching 404 page ("'.$fetchUrl.'"): '.$getUrlReport['message'], 'static_404', 2);
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

		$rootLine = t3lib_befunc::BEgetRootLine($pageId);

			// checks alternate domains
		if (count($rootLine) > 0) {
			$urlParts = parse_url($domain);

			/** @var t3lib_pageSelect $sysPage */
			$sysPage = t3lib_div::makeInstance('t3lib_pageSelect');

			$page = (array)$sysPage->getPage($pageId);
			$protocol = 'http';
			if ($page['url_scheme'] == 2 || ($page['url_scheme'] == 0 && t3lib_div::getIndpEnv('TYPO3_SSL'))) {
				$protocol = 'https';
			}

			foreach ($rootLine as $row) {
				$dRec = t3lib_befunc::getRecordsByField('sys_domain', 'pid', $row['uid'], ' AND redirectTo=\'\' AND hidden=0', '', 'sorting');

				if (is_array($dRec)) {
					foreach ($dRec as $dRecord) {
						$domainName = rtrim($dRecord['domainName'], '/');

						if ($domainName) {
							$domain = $domainName;
						} else {
							$domainRecord = t3lib_befunc::getDomainStartPage($urlParts['host'], $urlParts['path']);
							$domain = $domainRecord['domainName'];
						}
						if ($domain) {
							$domain = $protocol . '://' . $domain;
						} else {
							$domain = rtrim(t3lib_div::getIndpEnv('TYPO3_SITE_URL'), '/');
						}

						$domains[] = $domain;
					}
				}
			}
		}

		if (count($domains) === 0) {
			$domains[] = rtrim(t3lib_div::getIndpEnv('TYPO3_SITE_URL'), '/');
		}

		return $domains;
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
		$readFile = t3lib_div::getFileAbsFileName('typo3temp/tx_static404-'.t3lib_div::getIndpEnv('TYPO3_HOST_ONLY').'.html');
		if (!@is_file($readFile)) {
			throw new t3lib_error_Exception('Enable to find static 404 file. Try to clear Typo3 cache.');
		}
		header('HTTP/1.0 404 Not Found', null, 404);
		$fileContent = t3lib_div::getUrl($readFile);
		$fileContent = str_replace('###CURRENT_URL###', t3lib_div::getIndpEnv('REQUEST_URI'), $fileContent);
		$fileContent = str_replace('###REASON###', htmlspecialchars($funcRef['reasonText']), $fileContent);
		echo $fileContent;
		exit;
	}
}

if (defined('TYPO3_MODE') && $TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/static_404/class.tx_static404.php'])	{
	include_once($TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/static_404/class.tx_static404.php']);
}
