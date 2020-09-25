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
	 * @dataProvider getLagProvider
	 */
	public function testGetLag(
		$expectedLag,
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

		$actualLag = $lagProvider->getLag();
		if ( is_int( $expectedLag ) && is_int( $actualLag ) ) {
			// Due to the time it takes to run this after the creation of the fake responses,
			// allow for some difference
			$this->assertTrue(
				abs( $expectedLag - $actualLag ) < 3,
				"abs( $expectedLag - $actualLag ) < 3"
			);
		} else {
			$this->assertSame( $expectedLag, $actualLag );
		}
	}

	private function newMWHttpRequestMock( $getContentCallback ) {
		$request = $this->createMock( MWHttpRequest::class );

		$request->expects( $this->any() )
			->method( 'execute' )
			->will( $this->returnValue( Status::newGood() ) );
		$request->expects( $this->any() )
			->method( 'getContent' )
			->will( $this->returnCallback( $getContentCallback ) );

		return $request;
	}

	private function newHttpRequestFactoryMock( $httpRequestMocks ) {
		$requestFactory = $this->createMock( HttpRequestFactory::class );
		$requestFactory->method( 'create' )
			->will( call_user_func_array( [ $this, 'onConsecutiveCalls' ], $httpRequestMocks ) );

		return $requestFactory;
	}

	public function getLagProvider() {
		$json = file_get_contents( __DIR__ . '/PrometheusQueryBlazegraphLastupdated.json' );
		$laggedJson = file_get_contents( __DIR__ . '/PrometheusQueryBlazegraphLastupdated-lag.json' );

		$timeDummyReplace = $this->getTimeDummyReplaceClosure();

		$failingRequest = $this->getMockBuilder( MWHttpRequest::class )
						->disableOriginalConstructor()
						->getMock();
		$failingRequest->expects( $this->any() )
			->method( 'execute' )
			->will( $this->returnValue( Status::newFatal( 'foo' ) ) );
		$noLagRequest = $this->newMWHttpRequestMock( function () use ( $timeDummyReplace, $json ) {
			return $timeDummyReplace( $json );
		} );
		$laggedRequest = $this->newMWHttpRequestMock( function () use ( $timeDummyReplace, $laggedJson ) {
			return $timeDummyReplace( $laggedJson );
		} );
		$heavilyLaggedRequest = $this->newMWHttpRequestMock(
			function () use ( $timeDummyReplace, $laggedJson ) {
				return $timeDummyReplace( $laggedJson, 2 );
			}
		);

		return [
			'empty prometheus URL array' => [
				null,
				$this->createMock( HttpRequestFactory::class ),
				[],
				[ 'foo' ]
			],
			'failing request' => [
				null,
				$this->newHttpRequestFactoryMock( [ $failingRequest ] ),
				[ 'http://prometheus.svc.eqiad.wmnet/ops/api/v1/query?query=blazegraph_lastupdated' ],
				[ 'wdqs' ]
			],
			'good request, no lag' => [
				0,
				$this->newHttpRequestFactoryMock( [ $noLagRequest ] ),
				[ 'http://prometheus.svc.eqiad.wmnet/ops/api/v1/query?query=blazegraph_lastupdated' ],
				[ 'wdqs' ]
			],
			'good request, some lag' => [
				90,
				$this->newHttpRequestFactoryMock( [ $laggedRequest ] ),
				[ 'http://prometheus.svc.eqiad.wmnet/ops/api/v1/query?query=blazegraph_lastupdated' ],
				[ 'wdqs', 'wdqs-internal' ]
			],
			'good request, bad lag' => [
				500,
				$this->newHttpRequestFactoryMock( [ $laggedRequest ] ),
				[ 'http://prometheus.svc.eqiad.wmnet/ops/api/v1/query?query=blazegraph_lastupdated' ],
				[ 'wdqs', 'wdqs-internal', 'test' ]
			],
			'good request, nothing in group' => [
				null,
				$this->newHttpRequestFactoryMock( [ $laggedRequest ] ),
				[ 'http://prometheus.svc.eqiad.wmnet/ops/api/v1/query?query=blazegraph_lastupdated' ],
				[ 'blah' ]
			],
			'multiple requests' => [
				132,
				$this->newHttpRequestFactoryMock( [ $laggedRequest, $heavilyLaggedRequest ] ),
				[
					'http://prometheus.svc.eqiad.wmnet/ops/api/v1/query?query=blazegraph_lastupdated',
					'http://prometheus.svc.codfw.wmnet/ops/api/v1/query?query=blazegraph_lastupdated',
				],
				[ 'wdqs', 'wdqs-internal' ]
			],
		];
	}

	public function getLagInvalidJSONProvider() {
		$emptyMock = $this->newMWHttpRequestMock( function () {
			return json_encode( [] );
		} );

		$randomStuff = $this->newMWHttpRequestMock( function () {
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
	 * @dataProvider getLagInvalidJSONProvider
	 */
	public function testGetLag_invalidJson(
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

		$actualLag = $lagProvider->getLag();

		$this->assertNull( $actualLag );
	}

	private function getTimeDummyReplaceClosure(): \Closure {
		// Replace all @time-n@ in a given string with the value of (time() - n)
		return function ( $str, $multiplier = 1 ) {
			return preg_replace_callback(
				'/@time(-(\d+.?\d?))?@/',
				function ( $match ) use ( $multiplier ) {
					return time() - ( isset( $match[2] ) ? $match[2] * $multiplier : 0 );
				},
				$str
			);
		};
	}

}
