<?php
/*
Plugin Name: Vn Sync
Description: Export a json and reimport it, useful for webmapp plugin and origin site with pods/acf fields
Version: 0.2
Author: Marco Baroncini
*/


define('MB_IMPORT_EXPORT_FILE' , __FILE__ );
define('MB_IMPORT_EXPORT_DIR' , __DIR__ );
define('MB_EXPORT_JSONS_URL' , plugin_dir_url( MB_IMPORT_EXPORT_FILE ) . 'exports/'  );
define('MB_IMPORT_LOG_DIR_URL' , plugin_dir_url( MB_IMPORT_EXPORT_FILE ) . 'import_logs/'  );

/**
 * Core
 */
require('core/VT_UrlToMedia.php');
require('core/VT_UrlToMedia_FeaturedImage.php');
require('core/WebMapp_AdminOptionsPage.php');
require('core/VtAjaxHandler.php');

/**
 * WP CLI
 */

require( 'WP_CLI_loader.php' );


/**
 * Export
 */
require('MB_ExportPostsWithAcfAndPods.php');

/**
 * Import
 */
require('MB_ImportPostsWithAcfAndPods.php');

/**
 * Controllers
 */

require('WebMapp_Options.php');
require('VtCtl_Ajax.php');

