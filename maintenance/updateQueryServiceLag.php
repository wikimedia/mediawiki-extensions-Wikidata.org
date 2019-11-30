<?php

namespace WikidataOrg;

use Maintenance;
use MediaWiki\Logger\LoggerFactory;
use MediaWiki\MediaWikiServices;
use WikidataOrg\QueryServiceLag\CacheQueryServiceLagStore;
use WikidataOrg\QueryServiceLag\WikimediaPrometheusQueryServiceLagProvider;

$basePath = getenv( 'MW_INSTALL_PATH' ) !== false ?
	getenv( 'MW_INSTALL_PATH' ) :
	__DIR__ . '/../../..';

require_once $basePath . '/maintenance/Maintenance.php';

/**
 * @license GPL-2.0-or-later
 * @author Addshore
 */
class UpdateQueryServiceLag extends Maintenance {

	/**
	 * 70 as the primary use case of this script is to be run by a cron job every 60 seconds
	 * 70 should mean that a cached value is always set
	 * If the script dies or something stops running we will not stop Wikidata from being edited
	 * while the script is broken.
	 */
	private const TTL_DEFAULT = 70;

	public function __construct() {
		parent::__construct();

		$this->addDescription(
			'Update the cache maxlag for the query service from Prometheus'
		);

		$this->addOption( 'dry-run', 'Do not cache lag, only output values.', false, false );
		$this->addOption(
			'cluster',
			'One or more QS clusters to act on. e.g. "wdqs"',
			true,
			true,
			false,
			true
		);
		$this->addOption(
			'prometheus',
			'One or more Prometheus services to query. e.g. "prometheus.svc.eqiad.wmnet"',
			true,
			true,
			false,
			true
		);

		$this->addOption(
			'ttl',
			"The TTL for the cached lag value. Defaults to " . self::TTL_DEFAULT . " seconds",
			false,
			true
		);

		$this->requireExtension( 'Wikidata.org' );
	}

	public function execute() {
		$clusterNames = $this->getOption( 'cluster' );
		$prometheusUrls = [];
		foreach ( $this->getOption( 'prometheus' ) as $host ) {
			$prometheusUrls[] = 'http://' . $host . '/ops/api/v1/query?query=blazegraph_lastupdated';
		}
		$ttl = (int)$this->getOption( 'ttl', self::TTL_DEFAULT );

		$mw = MediaWikiServices::getInstance();

		$provider = new WikimediaPrometheusQueryServiceLagProvider(
			$mw->getHttpRequestFactory(),
			// For now just use the Wikibase log channel
			LoggerFactory::getInstance( 'Wikibase' ),
			$prometheusUrls,
			$clusterNames
		);

		$lag = $provider->getLag();

		if ( $lag === null ) {
			$this->fatalError(
				'Failed to get lag from prometheus'
			);
		}

		if ( $this->getOption( 'dry-run' ) ) {
			$this->output( "Got lag of: " . $lag . ".\n" );
		} else {
			$store = new CacheQueryServiceLagStore(
				$mw->getMainWANObjectCache(),
				$ttl
			);
			$store->updateLag( $lag );
		}
		$this->output( "Done.\n" );
	}
}

$maintClass = UpdateQueryServiceLag::class;
require_once RUN_MAINTENANCE_IF_MAIN;
