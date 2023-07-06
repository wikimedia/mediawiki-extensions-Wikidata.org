<?php

declare( strict_types = 1 );

namespace WikidataOrg\Tests\QueryServiceLag;

use MediaWiki\Http\HttpRequestFactory;
use MWHttpRequest;
use Psr\Log\NullLogger;
use Status;
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
		HttpRequestFactory $httpRequestFactory,
		array $prometheusUrls
	) {
		$lagProvider = new WikimediaPrometheusQueryServiceLagProvider(
			$httpRequestFactory,
			new NullLogger(),
			$prometheusUrls
		);

		$this->assertSame( $expectedLags, $lagProvider->getLag() );
	}

	private function newMWHttpRequestMock( $getContentCallback ) {
		$request = $this->createMock( MWHttpRequest::class );

		$request->method( 'execute' )
			->willReturn( Status::newGood() );
		$request->method( 'getContent' )
			->willReturnCallback( $getContentCallback );

		return $request;
	}

	private function newHttpRequestFactoryMock( $httpRequestMocks ) {
		$requestFactory = $this->createMock( HttpRequestFactory::class );
		$requestFactory->method( 'create' )
			->will( call_user_func_array( [ $this, 'onConsecutiveCalls' ], $httpRequestMocks ) );

		return $requestFactory;
	}

	public function getLagsProvider() {
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

		$failingRequest = $this->createMock( MWHttpRequest::class );
		$failingRequest->method( 'execute' )
			->willReturn( Status::newFatal( 'foo' ) );

		$normalRequest = $this->newMWHttpRequestMock( static function () use ( $json ) {
			return $json;
		} );

		$normalRequestCodfw = $this->newMWHttpRequestMock( static function () use ( $jsonCodfw ) {
			return $jsonCodfw;
		} );

		return [
			'empty prometheus URL array' => [
				null,
				$this->createMock( HttpRequestFactory::class ),
				[],
			],
			'failing request' => [
				null,
				$this->newHttpRequestFactoryMock( [ $failingRequest ] ),
				[ 'http://prometheus.svc.eqiad.wmnet/ops/api/v1/query' ],
			],
			'multiple successful requests' => [
				[
					'lag' => 168,
					'host' => 'wdqs2011',
				],
				$this->newHttpRequestFactoryMock( [ $normalRequest, $normalRequestCodfw ] ),
				[
					'http://prometheus.svc.eqiad.wmnet/ops/api/v1/query',
					'http://prometheus.svc.codfw.wmnet/ops/api/v1/query',
				],
			],
		];
	}

	public function getLagsInvalidJSONProvider() {
		$emptyMock = $this->newMWHttpRequestMock( static function () {
			return json_encode( [] );
		} );

		$randomStuff = $this->newMWHttpRequestMock( static function () {
			return json_encode( [ 'cookie' => [ 'monster' => [] ] ] );
		} );

		return [
			[
				$this->newHttpRequestFactoryMock( [ $emptyMock ] ),
				[ 'http://prometheus.svc.eqiad.wmnet/ops/api/v1/query' ],
			],
			[
				$this->newHttpRequestFactoryMock( [ $randomStuff ] ),
				[ 'http://prometheus.svc.eqiad.wmnet/ops/api/v1/query' ],
			],
		];
	}

	/**
	 * @dataProvider getLagsInvalidJSONProvider
	 */
	public function testGetLags_invalidJson(
		HttpRequestFactory $httpRequestFactory,
		array $prometheusUrls
	) {
		$lagProvider = new WikimediaPrometheusQueryServiceLagProvider(
			$httpRequestFactory,
			new NullLogger(),
			$prometheusUrls
		);

		$actualLag = $lagProvider->getLag();

		$this->assertSame( null, $actualLag );
	}

}
