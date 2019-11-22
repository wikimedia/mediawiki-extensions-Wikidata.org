<?php

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
		$expectedLags,
		HttpRequestFactory $httpRequestFactory,
		array $prometheusUrls,
		array $relevantClusters
	) {
		$lagProvider = new WikimediaPrometheusQueryServiceLagProvider(
			$httpRequestFactory,
			new NullLogger(),
			$prometheusUrls,
			$relevantClusters
		);

		$lags = $lagProvider->getLags();
		$this->assertEquals( $expectedLags, $lags );
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
		$json = file_get_contents( __DIR__ . '/PrometheusQueryBlazegraphLastupdated.json' );
		$jsonCodfw = file_get_contents( __DIR__ . '/PrometheusQueryBlazegraphLastupdated-codfw.json' );

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
				[],
				$this->createMock( HttpRequestFactory::class ),
				[],
				[ 'foo' ]
			],
			'failing request' => [
				[],
				$this->newHttpRequestFactoryMock( [ $failingRequest ] ),
				[ 'http://prometheus.svc.eqiad.wmnet/ops/api/v1/query?query=blazegraph_lastupdated' ],
				[ 'wdqs' ]
			],
			'multiple successful requests' => [
				[
					'wdqs1006' => [
						'cluster' => 'wdqs',
						'instance' => 'wdqs1006',
						'lag' => 1,
					],
					'wdqs1005' => [
						'cluster' => 'wdqs',
						'instance' => 'wdqs1005',
						'lag' => 1,
					],
					'wdqs1003' => [
						'cluster' => 'wdqs-internal',
						'instance' => 'wdqs1003',
						'lag' => 2,
					],
					'wdqs2004' => [
						'cluster' => 'wdqs',
						'instance' => 'wdqs2004',
						'lag' => 11,
					],
				],
				$this->newHttpRequestFactoryMock( [ $normalRequest, $normalRequestCodfw ] ),
				[
					'http://prometheus.svc.eqiad.wmnet/ops/api/v1/query?query=blazegraph_lastupdated',
					'http://prometheus.svc.codfw.wmnet/ops/api/v1/query?query=blazegraph_lastupdated',
				],
				[ 'wdqs', 'wdqs-internal' ]
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
				[ 'http://prometheus.svc.eqiad.wmnet/ops/api/v1/query?query=blazegraph_lastupdated' ],
				[ 'wdqs' ],
			],
			[
				$this->newHttpRequestFactoryMock( [ $randomStuff ] ),
				[ 'http://prometheus.svc.eqiad.wmnet/ops/api/v1/query?query=blazegraph_lastupdated' ],
				[ 'wdqs' ],
			],
		];
	}

	/**
	 * @dataProvider getLagsInvalidJSONProvider
	 */
	public function testGetLags_invalidJson(
		HttpRequestFactory $httpRequestFactory,
		array $prometheusUrls,
		array $relevantClusters
	) {
		$lagProvider = new WikimediaPrometheusQueryServiceLagProvider(
			$httpRequestFactory,
			new NullLogger(),
			$prometheusUrls,
			$relevantClusters
		);

		$actualLag = $lagProvider->getLags();

		$this->assertSame( [], $actualLag );
	}

}
