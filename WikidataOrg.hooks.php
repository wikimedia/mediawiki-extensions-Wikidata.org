<?php

namespace WikidataOrg;

use OutputPage;
use Skin;
use Wikibase\NamespaceUtils;

/**
 * File defining the hook handlers for the Wikidata.org extension.
 *
 * @since 0.1
 *
 * @license GNU GPL v2+
 * @author Bene* <benestar.wikimedia@gmail.com>
 */
final class WikidataOrgHooks {

	/**
	 * Handler for the BeforePageDisplay hook, adds wikidata-org.badges module
	 * for all entity pages.
	 *
	 * @since 0.4
	 *
	 * @param OutputPage $out
	 * @param Skin $skin
	 *
	 * @return bool
	 */
	public static function onBeforePageDisplay( OutputPage &$out, Skin &$skin ) {
		if ( !NamespaceUtils::isEntityNamespace( $out->getTitle()->getNamespace() ) ) {
			$out->addModules( 'wikidata-org.badges' );
		}
		return true;
	}
}
