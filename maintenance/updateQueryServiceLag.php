<?php

namespace WikidataOrg;

use Maintenance;
use MediaWiki\Logger\LoggerFactory;
use MediaWiki\MediaWikiServices;
use WikidataOrg\QueryServiceLag\CacheQueryServiceLagStore;
use WikidataOrg\QueryServiceLag\MostLaggedPooledServerProvider;
use WikidataOrg\QueryServiceLag\WikimediaLoadBalancerQueryServicePoolStatusProvider;
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
			'lb',
			'One or more LoadBalancers to query. e.g. "lvs1015:9090"',
			true,
			true,
			false,
			true
		);
		$this->addOption(
			'lb-pool',
			'One LoadBalancer pool to check. e.g. "wdqs_80"',
			true,
			true,
			false,
			false
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
		$lbUrls = [];
		foreach ( $this->getOption( 'lb' ) as $host ) {
			$lbUrls[] = 'http://' . $host;
		}
		$lbPool = $this->getOption( 'lb-pool' );

		$mw = MediaWikiServices::getInstance();
		// For now just use the Wikibase log channel
		$logger = LoggerFactory::getInstance( 'Wikibase' );

		$pooledLaggedProvider = new MostLaggedPooledServerProvider(
			new WikimediaLoadBalancerQueryServicePoolStatusProvider(
				$mw->getHttpRequestFactory(),
				$logger,
				$lbUrls,
				$lbPool
			),
			new WikimediaPrometheusQueryServiceLagProvider(
				$mw->getHttpRequestFactory(),
				$logger,
				$prometheusUrls,
				$clusterNames
			),
			$logger
		);

		$mostLaggedPooledServer = $pooledLaggedProvider->getMostLaggedPooledServer();

		if ( $mostLaggedPooledServer === null ) {
			$this->fatalError(
				'Failed to get lagged pooled server from prometheus and loadbalancers'
			);
		}

		$lag = $mostLaggedPooledServer['lag'];
		$server = $mostLaggedPooledServer['instance'];

		$this->output( "Got lag of: " . $lag . " for instance: " . $server . ".\n" );

		if ( !$this->getOption( 'dry-run' ) ) {
			$store = new CacheQueryServiceLagStore(
				$mw->getMainWANObjectCache(),
				$ttl
			);
			$store->updateLag( $server, $lag );
			$this->output( "Stored in cache with TTL of: " . $ttl . ".\n" );
		}
	}
}

$maintClass = UpdateQueryServiceLag::class;
require_once RUN_MAINTENANCE_IF_MAIN;
