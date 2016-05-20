.. ==================================================
.. FOR YOUR INFORMATION
.. --------------------------------------------------
.. -*- coding: utf-8 -*- with BOM.

.. include:: ../Includes.txt


Introduction
============

Presentation
-------------

This extension caches your 404 page in a static HTML file, in order to save some server load.

Typo3 generates the 404 page with a loopback call. In FastCGI/php-fpm configurations where processes are limited we could face to an easy DDOS attack. Ie.:

- php-fpm with pm.max_children = 50
- send 50 http concurrent request on a 404
- all processes will be locked for 30 sec. cause they loopback on the server which will wait for an available process


What does it do?
----------------

The static 404 file is automatically generated when the page cache is cleared from the BE, then cached in typo3temp/tx_static404/. When a 404 occurs, the generated file is directly send to the client without loopback call.

The plugin handles multidomain installation, and multilingual websites (if they are on separate domains).
