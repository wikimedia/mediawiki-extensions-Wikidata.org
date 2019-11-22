<?php

namespace WikidataOrg;

use Exception;
use ExtensionRegistry;
use Html;
use MediaWiki\MediaWikiServices;
use OutputPage;
use Skin;
use Wikibase\Repo\WikibaseRepo;
use WikidataOrg\QueryServiceLag\CacheQueryServiceLagStore;

/**
 * File defining the hook handlers for the Wikidata.org extension.
 *
 * @since 0.1
 *
 * @license GPL-2.0-or-later
 * @author Bene* < benestar.wikimedia@gmail.com >
 * @author Lucie-Aimée Kaffee < lucie.kaffee@wikimedia.org >
 */
final class Hooks {

	/**
	 * Handler for the BeforePageDisplay hook, adds the
	 * wikidata-org.badges module to all entity pages.
	 *
	 * @since 0.1
	 *
	 * @param OutputPage $out
	 * @param Skin $skin
	 */
	public static function onBeforePageDisplay( OutputPage $out, Skin $skin ) {
		if ( !ExtensionRegistry::getInstance()->isLoaded( 'WikibaseRepository' ) ) {
			throw new Exception( 'The Wikidata.org extension requires Wikibase to be installed' );
		}
		$entityNamespaceLookup = WikibaseRepo::getEntityNamespaceLookup();

		$ns = $out->getTitle()->getNamespace();
		if ( $entityNamespaceLookup->isEntityNamespace( $ns ) || $ns === NS_SPECIAL ) {
			$out->addModuleStyles( 'ext.wikidata-org.badges' );
		}
	}

	/**
	 * Add a "Data access" link to the footer
	 *
	 * @param Skin $skin
	 * @param string $key
	 * @param array &$footerItems
	 */
	public static function onSkinAddFooterLinks(
		Skin $skin,
		string $key,
		array &$footerItems
	) {
		if ( $key === 'places' ) {
			$href = Skin::makeInternalOrExternalUrl( 'Special:MyLanguage/Wikidata:Data_access' );
			$footerItems['data-access'] = Html::element(
				'a',
				[ 'href' => $href ],
				$skin->msg( 'data-access' )->text()
			);
		}
	}

	/**
	 * Handler for the ApiMaxLagInfo to add queryservice lag stats
	 *
	 * @see https://www.mediawiki.org/wiki/Manual:Hooks/ApiMaxLagInfo
	 *
	 * @param array &$lagInfo
	 */
	public static function onApiMaxLagInfo( array &$lagInfo ) {
		$mw = MediaWikiServices::getInstance();
		$config = $mw->getMainConfig();

		$factor = (int)$config->get( 'WikidataOrgQueryServiceMaxLagFactor' );
		if ( $factor <= 0 ) {
			return;
		}

		$store = new CacheQueryServiceLagStore(
			$mw->getMainWANObjectCache(),
			// Need to pass TTL that is not used.. (>0)
			1
		);

		$storedLag = $store->getLag();

		if ( !$storedLag ) {
			return;
		}

		list( $server, $lag ) = $storedLag;

		$fakeDispatchLag = $lag / (float)$factor;
		if ( $fakeDispatchLag > $lagInfo['lag'] ) {
			$lagInfo = [
				'host' => $server,
				'lag' => $fakeDispatchLag,
				'type' => 'wikibase-queryservice',
				'queryserviceLag' => $lag,
			];
		}
	}

}
