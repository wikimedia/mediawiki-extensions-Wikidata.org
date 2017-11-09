<?php

if ( function_exists( 'wfLoadExtension' ) ) {
	wfLoadExtension( 'Wikidata.org', __DIR__ . '/extension.json' );
	// Keep i18n globals so mergeMessageFileList.php doesn't break
	$wgMessagesDirs['Wikidata.org'] = __DIR__ . '/i18n';
	/* wfWarn(
		'Deprecated PHP entry point used for Wikidata.org extension. Please use wfLoadExtension ' .
		'instead, see https://www.mediawiki.org/wiki/Extension_registration for more details.'
	); */
	return true;
} else {
	die( 'This version of the Wikidata.org extension requires MediaWiki 1.29+' );
}
