<?php

/**
 * Component with Wikidata specific modifications
 * and additions for Wikibase.
 *
 *  ## ##### ##### ## ## ##### ## ##### ## ##
 *  ## ##### ##### ## ## ##### ## ##### ## ##
 *  ## ##### ##### ## ## ##### ## ##### ## ##
 *  ## ##### ##### ## ## ##### ## ##### ## ##
 *  ## ##### ##### ## ## ##### ## ##### ## ##
 *  ## ##### ##### ## ## ##### ## ##### ## ##
 *  ## ##### ##### ## ## ##### ## ##### ## ##
 *  ## ##### ##### ## ## ##### ## ##### ## ##
 *  ## ##### ##### ## ## ##### ## ##### ## ##
 *  ## ##### ##### ## ## ##### ## ##### ## ##
 *  __      _____ _  _____ ___   _ _____ _
 *  \ \    / /_ _| |/ /_ _|   \ /_\_   _/_\
 *   \ \/\/ / | || ' < | || |) / _ \| |/ _ \
 *    \_/\_/ |___|_|\_\___|___/_/ \_\_/_/ \_\
 *
 */

/**
 * Entry point for for the Wikidata.org extension.
 *
 * @see README.md
 * @see https://www.wikidata.org/
 * @license GNU GPL v2+
 */

if ( !defined( 'MEDIAWIKI' ) ) {
	die( 'Not an entry point.' );
}

if ( defined( 'WIKIDATA_ORG_VERSION' ) ) {
	// Do not initialize more than once.
	return 1;
}

define( 'WIKIDATA_ORG_VERSION', '0.1 alpha' );

if ( !defined( 'WB_VERSION' ) ) {
	throw new Exception( 'Wikidata.org requires Wikibase to be installed.' );
}

call_user_func( function() {
	global $wgExtensionCredits, $wgResourceModules;

	$wgExtensionCredits['wikibase'][] = array((
		'path' => __DIR__,
		'name' => 'Wikidata.org',
		'version' => WIKIDATA_ORG_VERSION,
		'author' => array(
			'The Wikidata team', // TODO: link?
		),
		'url' => 'https://www.mediawiki.org/wiki/Extension:Wikidata.org',
		'descriptionmsg' => 'wikidata-org-desc'
	);

	// Resource Loader Modules:
	$wgResourceModules = array_merge( $wgResourceModules, include( __DIR__ . "/resources/Resources.php" ) );

} );