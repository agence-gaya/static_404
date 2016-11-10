.. ==================================================
.. FOR YOUR INFORMATION
.. --------------------------------------------------
.. -*- coding: utf-8 -*- with BOM.

.. include:: ../Includes.txt


.. _users-manual:

Users manual
============

Installation
------------

1. Download, install and enable the extension in Extension manager.

2. In the extension manager, in the configuration tab of the extension, specify the page ID of your 404 page. If you have more than one 404 page (on a multi-domain installation), you can put several IDs separated by a comma. The first ID will be used as default 404 (see below)

3. Clear TYPO3 page cache. This will create the static 404 page in typo3temp/tx_static404/.

4. In your .htaccess file or in your virtualhost (preferable for a multi-domain installation), configure the ErrorDocument directive to point to the new 404 file. Below an excerpt of the .htaccess file with the needed RewriteRule

::

	ErrorDocument 404 /typo3temp/tx_static404/[YOU FULL DOMAIN NAME HERE].html

Replace `[YOU FULL DOMAIN NAME HERE]` by the domain of your installation, ie. :

::

	ErrorDocument 404 /typo3temp/tx_static404/fr.example.com.html

You can also use the default 404, which corresponds to the first page ID defined :

::

	ErrorDocument 404 /typo3temp/tx_static404/default.html


Additionnal configuration
-------------------------

- Disable pageNotFound handling : if you have an other plugin which needs to handle the pageNotfound hook, you can enable this option to delegate it. In your plugin, you will have to manage the 404 by adding this lines :

::

	if (t3lib_extMgm::isLoaded('static_404')) {
		$static404 = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance('tx_static404');
		$static404->render404AndExit();
	}

- Do not generate static 404 pages for domain records with a redirect

In a website with multiple sys_domain records with redirects, you can generate 404 pages for all domains by disabling this option

Troubleshootings
----------------

If the clearCache command takes too long or never ends, check the TYPO3's log and/or the webserver's error log.

Maybe the extension is misconfigured or maybe your server can't reach itself (check your /etc/hosts file).