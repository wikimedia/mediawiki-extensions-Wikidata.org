<?php

namespace WikidataOrg\Tests\QueryServiceLag;

use MediaWiki\Http\HttpRequestFactory;
use MWHttpRequest;
use Psr\Log\NullLogger;
use Status;
use WikidataOrg\QueryServiceLag\WikimediaLoadBalancerQueryServicePoolStatusProvider;

/**
 * @covers \WikidataOrg\QueryServiceLag\WikimediaLoadBalancerQueryServicePoolStatusProvider
 *
 * @group Wikibase
 *
 * @license GPL-2.0-or-later
 */
class WikimediaLoadBalancerQueryServicePoolStatusProviderTest extends \PHPUnit\Framework\TestCase {

	/**
	 * @dataProvider getStatusProvider
	 */
	public function testGetStatus(
		$expected,
		HttpRequestFactory $httpRequestFactory,
		array $loadbalancerUrls
	) {
		$provider = new WikimediaLoadBalancerQueryServicePoolStatusProvider(
			$httpRequestFactory,
			new NullLogger(),
			$loadbalancerUrls,
			'wdqs_80'
		);

		$lbstatus = $provider->getStatus();
		$this->assertEquals( $expected, $lbstatus );
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

	public function getStatusProvider() {
		$json = file_get_contents( __DIR__ . '/LoadBalancerStatus-wdqs1007depooled.json' );
		$jsonCodfw = file_get_contents( __DIR__ . '/LoadBalancerStatus-codfwNormal.json' );

		$failingRequest = $this->getMockBuilder( MWHttpRequest::class )
			->disableOriginalConstructor()
			->getMock();
		$failingRequest->expects( $this->any() )
			->method( 'execute' )
			->will( $this->returnValue( Status::newFatal( 'foo' ) ) );

		$normalRequest = $this->newMWHttpRequestMock( static function () use ( $json ) {
			return $json;
		} );
		$normalRequestCodfw = $this->newMWHttpRequestMock( static function () use ( $jsonCodfw ) {
			return $jsonCodfw;
		} );

		return [
			'empty loadbalancer URL array' => [
				[],
				$this->createMock( HttpRequestFactory::class ),
				[]
			],
			'failing request' => [
				[],
				$this->newHttpRequestFactoryMock( [ $failingRequest ] ),
				[ 'http://lvs123:456' ]
			],
			'multiple successful requests' => [
				[
					'wdqs1006' => [
						'pooled' => true,
						'enabled' => true,
						'up' => true,
						'weight' => 10,
					],
					'wdqs1007' => [
						'pooled' => false,
						'enabled' => false,
						'up' => true,
						'weight' => 10,
					],
					'wdqs1004' => [
						'pooled' => true,
						'enabled' => true,
						'up' => true,
						'weight' => 10,
					],
					'wdqs2002' => [
						'pooled' => true,
						'enabled' => true,
						'up' => true,
						'weight' => 10,
					],
				],
				$this->newHttpRequestFactoryMock( [ $normalRequest, $normalRequestCodfw ] ),
				[
					'http://lvs12:34',
					'http://lvs56:78',
				]
			],
		];
	}

	public function getStatusInvalidJSONProvider() {
		$emptyMock = $this->newMWHttpRequestMock( static function () {
			return json_encode( [] );
		} );

		$randomStuff = $this->newMWHttpRequestMock( static function () {
			return json_encode( "foo" );
		} );

		return [
			[ $this->newHttpRequestFactoryMock( [ $emptyMock ] ) ],
			[ $this->newHttpRequestFactoryMock( [ $randomStuff ] ) ],
		];
	}

	/**
	 * @dataProvider getStatusInvalidJSONProvider
	 */
	public function testGetStatus_invalidJson( HttpRequestFactory $httpRequestFactory ) {
		$provider = new WikimediaLoadBalancerQueryServicePoolStatusProvider(
			$httpRequestFactory,
			new NullLogger(),
			[ 'someUrl' ],
			'wdqs_80'
		);

		$actualLag = $provider->getStatus();

		$this->assertSame( [], $actualLag );
	}

}
