<?php

declare( strict_types = 1 );

namespace WikidataOrg\Tests\Maintenance;

use MediaWiki\Tests\Maintenance\MaintenanceBaseTestCase;
use MockHttpTrait;
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

		$this->installMockHttp( function ( string $url ) {
			if ( str_starts_with( $url, 'http://prometheus.tld/ops/api/v1/query?query=topk' ) ) {
				return $this->makeFakeHttpRequest( '{
					"status": "success",
					"data": {
						"resultType": "vector",
						"result": [{
							"metric": {
								"cluster": "wdqs-internal",
								"host": "wdqs1008",
								"instance": "wdqs1008:9193",
								"job": "blazegraph",
								"site": "eqiad"
							},
							"value": [1688651698.608, "82.60800004005432"]
						}]
					}
				}' );
			}
			$this->fail( "Unexpected request for '$url'" );
		} );
	}

	public function testExecute() {
		$this->maintenance->loadWithArgv( [
			'--prometheus', 'prometheus.tld',
		] );
		$this->maintenance->execute();

		$lagStore = new CacheQueryServiceLagStore(
			$this->getServiceContainer()->getMainWANObjectCache(),
			123
		);
		$this->assertSame(
			[ 'wdqs1008', 83 ],
			$lagStore->getLag()
		);
	}

}
