# Static 404

## Introduction

This extension caches your 404 page in a static HTML file, in order to save some server load.

Typo3 generates the 404 page with a loopback call. In FastCGI/php-fpm configurations where processes are limited we could face to an easy DDOS attack. Ex :
- php-fpm with pm.max_children = 50
- send 50 http concurrent request on a 404
- all processes will be locked for 30 sec. cause they loopback on the server which will wait for an available process

## What does it do?

The static 404 file is automatically generated when the page cache is cleared from the BE, then cached in typo3temp/.
When a 404 occurs, the generated file is directly send to the client without loopback call.

The plugin handles multidomain installation, and multilingual websites (if they are on separate domains).

## Installation

1.  Download, install and enable the extension in Extension manager.

2.  In the extension manager, in the configuration tab of the extension, specify the page ID of your 404 page. If you have more than one 404 page (on a multi-domain installation), you can put several IDs separated by a comma. The first ID will be used as default 404 (see below)

3.  Clear TYPO3 page cache. This will create the static 404 page in typo3temp/.

4.  In your _.htaccess file or in your virtualhost (preferable for a multi-domain installation),
    configure the ErrorDocument directive to point to the new 404 file.
    Below an excerpt of the _.htaccess file with the needed RewriteRule
    
    ```
    ErrorDocument 404 /typo3temp/tx_static404/[YOU FULL DOMAIN NAME HERE].html
    ```
    Replace `[YOU FULL DOMAIN NAME HERE]` by the domain of your installation, ie. :
    
    ```
    ErrorDocument 404 /typo3temp/tx_static404/fr.example.com.html
    ```
    
    You can also use the default 404, which corresponds to the first page ID defined :
    
    ```
    ErrorDocument 404 /typo3temp/tx_static404/default.html

## Additionnal configuration

1.  Disable pageNotFound handling : if you have an other plugin which needs to handle the pageNotfound hook, you can enable this option to delegate it. In your plugin, you will have to manage the 404 by adding this lines :

    ```
    if (t3lib_extMgm::isLoaded('static_404')) {
        $static404 = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance('tx_static404');
        $static404->render404AndExit();
    }
    ```

2. Do not generate static 404 pages for domain records with a redirect

In a website with multiple sys_domain records with redirects, you can generate 404 pages for all domains by disabling this option

## Troubleshootings

If the clearCache command takes too long or never ends, check the TYPO3's log and/or the webserver's error log.
Maybe the extension is misconfigured or maybe your server can't reach itself (check your /etc/hosts file).

## Credits
&copy; 2016 GAYA La Nouvelle Agence [http://www.gaya.fr/]