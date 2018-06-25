<?php

namespace WikidataOrg;

use Exception;
use Html;
use OutputPage;
use QuickTemplate;
use Skin;
use SkinTemplate;
use Wikibase\Repo\WikibaseRepo;

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

}
