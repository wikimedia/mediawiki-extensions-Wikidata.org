<?php

namespace WikidataOrg;

use MediaWiki\Logger\LoggerFactory;
use MediaWiki\Maintenance\Maintenance;
use MediaWiki\MediaWikiServices;
use WikidataOrg\QueryServiceLag\CacheQueryServiceLagStore;
use WikidataOrg\QueryServiceLag\WikimediaPrometheusQueryServiceLagProvider;

// @codeCoverageIgnoreStart
$basePath = getenv( 'MW_INSTALL_PATH' ) !== false ?
	getenv( 'MW_INSTALL_PATH' ) :
	__DIR__ . '/../../..';

require_once $basePath . '/maintenance/Maintenance.php';
// @codeCoverageIgnoreEnd

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

	private const POOLED_SERVER_MIN_QUERY_RATE = 1.0;

	public function __construct() {
		parent::__construct();

		$this->addDescription(
			'Update the cache maxlag for the query service from Prometheus'
		);

		$this->addOption( 'dry-run', 'Do not cache lag, only output values.', false, false );
		$this->addOption(
			'cluster',
			'Supported for backwards compatibility. To be removed.',
			false,
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
			'Supported for backwards compatibility. To be removed.',
			false,
			true,
			false,
			true
		);
		$this->addOption(
			'lb-pool',
			'Supported for backwards compatibility. To be removed.',
			false,
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

		$this->addOption(
			'pooled-server-min-query-rate',
			"The minimal query rate that is expected to be served from a pooled server. " .
			"Defaults to " . self::POOLED_SERVER_MIN_QUERY_RATE,
			false,
			true
		);

		$this->requireExtension( 'Wikidata.org' );
	}

	public function execute() {
		$prometheusUrls = [];
		foreach ( $this->getOption( 'prometheus' ) as $host ) {
			$prometheusUrls[] = 'http://' . $host . '/ops/api/v1/query';
		}
		$ttl = (int)$this->getOption( 'ttl', self::TTL_DEFAULT );
		$minQueryRate = floatval( $this->getOption( "pooled-server-min-query-rate",
			self::POOLED_SERVER_MIN_QUERY_RATE ) );

		$mw = MediaWikiServices::getInstance();
		// For now just use the Wikibase log channel
		$logger = LoggerFactory::getInstance( 'Wikibase' );

		$lagProvider = new WikimediaPrometheusQueryServiceLagProvider(
			$mw->getHttpRequestFactory(),
			$logger,
			$prometheusUrls,
			$minQueryRate
		);
		[ 'lag' => $lag, 'host' => $server ] = $lagProvider->getLag();

		if ( $lag === null ) {
			$this->fatalError(
				'Failed to get lagged pooled server from prometheus.'
			);
		}

		$this->output( "Got lag of: " . $lag . " for host: " . $server . ".\n" );

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

// @codeCoverageIgnoreStart
$maintClass = UpdateQueryServiceLag::class;
require_once RUN_MAINTENANCE_IF_MAIN;
// @codeCoverageIgnoreEnd
