<?php

declare( strict_types = 1 );

namespace WikidataOrg\Tests\QueryServiceLag;

use MediaWiki\Http\HttpRequestFactory;
use MediaWiki\Status\Status;
use MWHttpRequest;
use Psr\Log\NullLogger;
use WikidataOrg\QueryServiceLag\WikimediaPrometheusQueryServiceLagProvider;

/**
 * @covers \WikidataOrg\QueryServiceLag\WikimediaPrometheusSparqlEndpointReplicationStatus
 *
 * @group Wikibase
 *
 * @license GPL-2.0-or-later
 * @author Marius Hoch
 */
class WikimediaPrometheusQueryServiceLagProviderTest extends \PHPUnit\Framework\TestCase {

	/**
	 * @dataProvider getLagsProvider
	 */
	public function testGetLags(
		?array $expectedLags,
		array $responses,
		array $prometheusUrls
	) {
		$lagProvider = new WikimediaPrometheusQueryServiceLagProvider(
			$this->newHttpRequestFactoryMock( ...$responses ),
			new NullLogger(),
			$prometheusUrls,
			0.5
		);

		$this->assertSame( $expectedLags, $lagProvider->getLag() );
	}

	private function newMWHttpRequestMock( $response ) {
		$request = $this->createMock( MWHttpRequest::class );
		$request->method( 'execute' )
			->willReturn( $response ? Status::newGood() : Status::newFatal( '' ) );
		$request->method( 'getContent' )
			->willReturn( $response );
		return $request;
	}

	private function newHttpRequestFactoryMock( ...$responses ) {
		$requestFactory = $this->createMock( HttpRequestFactory::class );
		$requestFactory->method( 'create' )
			->willReturnOnConsecutiveCalls( ...array_map( [ $this, 'newMWHttpRequestMock' ], $responses ) );
		return $requestFactory;
	}

	public static function getLagsProvider() {
		$json = '{
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
		}';
		$jsonCodfw = '{
			"status": "success",
			"data": {
				"resultType": "vector",
				"result": [{
					"metric": {
						"cluster": "wdqs",
						"host": "wdqs2011",
						"instance": "wdqs2011:9193",
						"job": "blazegraph",
						"site": "codfw"
					},
					"value": [1688654714.502, "167.50200009346008"]
				}]
			}
		}';

		return [
			'empty prometheus URL array' => [
				null,
				[],
				[],
			],
			'failing request' => [
				null,
				[ false ],
				[ 'http://prometheus.svc.eqiad.wmnet/ops/api/v1/query' ],
			],
			'multiple successful requests' => [
				[
					'lag' => 168,
					'host' => 'wdqs2011',
				],
				[ $json, $jsonCodfw ],
				[
					'http://prometheus.svc.eqiad.wmnet/ops/api/v1/query',
					'http://prometheus.svc.codfw.wmnet/ops/api/v1/query',
				],
			],
		];
	}

	public static function getLagsInvalidJSONProvider() {
		return [
			[
				[],
				[ 'http://prometheus.svc.eqiad.wmnet/ops/api/v1/query' ],
			],
			[
				[ 'cookie' => [ 'monster' => [] ] ],
				[ 'http://prometheus.svc.eqiad.wmnet/ops/api/v1/query' ],
			],
		];
	}

	/**
	 * @dataProvider getLagsInvalidJSONProvider
	 */
	public function testGetLags_invalidJson(
		array $content,
		array $prometheusUrls
	) {
		$lagProvider = new WikimediaPrometheusQueryServiceLagProvider(
			$this->newHttpRequestFactoryMock( json_encode( $content ) ),
			new NullLogger(),
			$prometheusUrls,
			0.5
		);

		$actualLag = $lagProvider->getLag();

		$this->assertSame( null, $actualLag );
	}

}
