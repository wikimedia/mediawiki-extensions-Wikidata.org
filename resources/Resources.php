<?php

/**
 * Wikidata.org ResourceLoader modules
 *
 * @since 0.1
 *
 * @license GNU GPL v2+
 * @author Bene* < benestar.wikimedia@gmail.com >
 */
return call_user_func( function() {
	$remoteExtPathParts = explode(
		DIRECTORY_SEPARATOR . 'extensions' . DIRECTORY_SEPARATOR, __DIR__, 2
	);
	$moduleTemplate = [
		'localBasePath' => __DIR__,
		'remoteExtPath' => $remoteExtPathParts[1]
	];

	$modules = [
		'ext.wikidata-org.badges' => $moduleTemplate + [
			'position' => 'bottom',
			'styles' => [
				'themes/default/wikidata-org.badges.css',
			]
		]
	];

	return $modules;
} );
