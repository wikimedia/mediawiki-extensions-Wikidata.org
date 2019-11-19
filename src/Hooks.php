<?php

namespace WikidataOrg;

use Exception;
use Html;
use MediaWiki\MediaWikiServices;
use OutputPage;
use QuickTemplate;
use Skin;
use SkinTemplate;
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
		if ( !class_exists( WikibaseRepo::class ) ) {
			throw new Exception( 'The Wikidata.org extension requires Wikibase to be installed' );
		}
		$entityNamespaceLookup = WikibaseRepo::getDefaultInstance()->getEntityNamespaceLookup();

		$ns = $out->getTitle()->getNamespace();
		if ( $entityNamespaceLookup->isEntityNamespace( $ns ) || $ns === NS_SPECIAL ) {
			$out->addModuleStyles( 'ext.wikidata-org.badges' );
		}
	}

	/**
	 * Add a "Data access" link to the footer
	 *
	 * @param SkinTemplate $skin
	 * @param QuickTemplate $template
	 */
	public static function onSkinTemplateOutputPageBeforeExec(
		SkinTemplate $skin,
		QuickTemplate $template
	) {
		$destination = Skin::makeInternalOrExternalUrl( "Special:MyLanguage/Wikidata:Data_access" );
		$link = Html::element(
			'a',
			[ 'href' => $destination ],
			$skin->msg( 'data-access' )->text()
		);
		$template->set( 'data-access', $link );
		$template->data['footerlinks']['places'][] = 'data-access';
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
			0,
			''
		);

		$lag = $store->getLag();

		if ( $lag ) {
			$maxDispatchLag = $lag / (float)$factor;
			if ( $maxDispatchLag > $lagInfo['lag'] ) {
				$lagInfo = [
					// Host set to 'all' to indicate all of the public cluster.
					// A future change might want to pass a real host down to this level via the cache
					'host' => 'all',
					'lag' => $lag,
					'type' => 'wikibase-queryservice',
					'queryserviceLag' => $lag,
				];
			}
		}
	}

}
