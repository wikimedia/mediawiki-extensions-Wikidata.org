<?php

declare( strict_types = 1 );

namespace WikidataOrg\Tests\Maintenance;

use HashBagOStuff;
use MediaWiki\Tests\Maintenance\MaintenanceBaseTestCase;
use MockHttpTrait;
use WANObjectCache;
use WikidataOrg\QueryServiceLag\CacheQueryServiceLagStore;
use WikidataOrg\UpdateQueryServiceLag;

// files in maintenance/ are not autoloaded, so load explicitly
require_once __DIR__ . '/../../../maintenance/updateQueryServiceLag.php';

/**
 * Integration test for the updateQueryServiceLag maintenance script.
 *
 * @covers \WikidataOrg\UpdateQueryServiceLag
 *
 * @license GPL-2.0-or-later
 * @author Marius Hoch <mail@mariushoch.de>
 */
class UpdateQueryServiceLagTest extends MaintenanceBaseTestCase {
	use MockHttpTrait;

	protected function getMaintenanceClass() {
		return UpdateQueryServiceLag::class;
	}

	protected function setUp(): void {
		parent::setUp();

		$this->overrideMwServices(
			null,
			[
				'MainWANObjectCache' => static function () {
					return new WANObjectCache(
						[ 'cache' => new HashBagOStuff() ]
					);
				}
			]
		);

		$this->installMockHttp( function ( string $url ) {
			if ( $url === 'http://prometheus.tld/ops/api/v1/query?query=blazegraph_lastupdated' ) {
				return $this->makeFakeHttpRequest( '{
					"status": "success",
					"data": {
						"resultType": "vector",
						"result": [{
							"metric": {
								"__name__": "blazegraph_lastupdated",
								"cluster": "wdqs",
								"instance": "wdqs1013:9193",
								"job": "blazegraph",
								"site": "eqiad"
							},
							"value": [1666274950.771, "1666274815"]
						}]
					}
				}' );
			} elseif ( $url === 'http://lvs1234:4321/pools/wdqs_80' ) {
				return $this->makeFakeHttpRequest( '{"wdqs1013.eqiad.wmnet": {
					"pooled": true,
					"enabled": true,
					"up": true,
					"weight": 10
				}}' );
			}
			$this->fail( "Unexpected request for '$url'" );
		} );
	}

	public function testExecute() {
		$this->maintenance->loadWithArgv( [
			'--cluster', 'wdqs',
			'--prometheus', 'prometheus.tld',
			'--lb', 'lvs1234:4321',
			'--lb-pool', 'wdqs_80'
		] );
		$this->maintenance->execute();

		$lagStore = new CacheQueryServiceLagStore(
			$this->getServiceContainer()->getMainWANObjectCache(),
			123
		);
		$this->assertSame(
			[ 'wdqs1013', 135 ],
			$lagStore->getLag()
		);
	}

}
