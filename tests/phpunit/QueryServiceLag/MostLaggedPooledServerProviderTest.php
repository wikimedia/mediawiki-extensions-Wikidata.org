<?php

namespace WikidataOrg\Tests\QueryServiceLag;

use Psr\Log\NullLogger;
use WikidataOrg\QueryServiceLag\MostLaggedPooledServerProvider;
use WikidataOrg\QueryServiceLag\WikimediaLoadBalancerQueryServicePoolStatusProvider;
use WikidataOrg\QueryServiceLag\WikimediaPrometheusQueryServiceLagProvider;

/**
 * @covers \WikidataOrg\QueryServiceLag\WikimediaLoadBalancerQueryServicePoolStatusProvider
 *
 * @group Wikibase
 *
 * @license GPL-2.0-or-later
 */
class MostLaggedPooledServerProviderTest extends \PHPUnit\Framework\TestCase {

	private function newLoadBalancerProvider( $returnValue ) {
		$mock = $this->getMockBuilder( WikimediaLoadBalancerQueryServicePoolStatusProvider::class )
			->disableOriginalConstructor()
			->getMock();
		$mock->method( 'getStatus' )
			->will( $this->returnValue( $returnValue ) );

		return $mock;
	}

	private function newLagProvider( $returnValue ) {
		$mock = $this->getMockBuilder( WikimediaPrometheusQueryServiceLagProvider::class )
			->disableOriginalConstructor()
			->getMock();
		$mock->method( 'getLags' )
			->will( $this->returnValue( $returnValue ) );

		return $mock;
	}

	public static function provideGetMostLaggedPooledServer() {
		return [
			'everything empty' => [ [], [], null ],
			'1 service, 1 pooled, max lag 1' => [
				[
					'qs1' => [ 'pooled' => true,  'enabled' => true,  'up' => true ]
				],
				[
					'qs1' => [ 'lag' => 1,  'instance' => 'qs1' ],
				],
				[
					'lag' => 1,
					'instance' => 'qs1'
				]
			],
			'2 services, 1 pooled (other not returned), max lag 1' => [
				[
					'qs1' => [ 'pooled' => true,  'enabled' => true,  'up' => true ]
				],
				[
					'qs1' => [ 'lag' => 1,  'instance' => 'qs1' ],
					'qs2' => [ 'lag' => 100,  'instance' => 'qs2' ],
				],
				[
					'lag' => 1,
					'instance' => 'qs1'
				]
			],
			'2 services, 1 pooled (other flagged as not pooled), max lag 1' => [
				[
					'qs1' => [ 'pooled' => true,  'enabled' => true,  'up' => true ],
					'qs2' => [ 'pooled' => false,  'enabled' => true,  'up' => true ],
				],
				[
					'qs1' => [ 'lag' => 1, 'instance' => 'qs1' ],
					'qs2' => [ 'lag' => 100,  'instance' => 'qs2' ],
				],
				[
					'lag' => 1,
					'instance' => 'qs1'
				]
			],
			'2 services, 1 pooled (other flagged as not enabled), max lag 1' => [
				[
					'qs1' => [ 'pooled' => true,  'enabled' => true,  'up' => true ],
					'qs2' => [ 'pooled' => true,  'enabled' => false,  'up' => true ],
				],
				[
					'qs1' => [ 'lag' => 1, 'instance' => 'qs1' ],
					'qs2' => [ 'lag' => 100,  'instance' => 'qs2' ],
				],
				[
					'lag' => 1,
					'instance' => 'qs1'
				]
			],
			'2 services, 1 pooled (other flagged as not up), max lag 1' => [
				[
					'qs1' => [ 'pooled' => true,  'enabled' => true,  'up' => true ],
					'qs2' => [ 'pooled' => true,  'enabled' => true,  'up' => false ],
				],
				[
					'qs1' => [ 'lag' => 1, 'instance' => 'qs1' ],
					'qs2' => [ 'lag' => 100,  'instance' => 'qs2' ],
				],
				[
					'lag' => 1,
					'instance' => 'qs1'
				]
			],
			'2 services, 2 pooled, max lag 100' => [
				[
					'qs1' => [ 'pooled' => true,  'enabled' => true,  'up' => true ],
					'qs2' => [ 'pooled' => true,  'enabled' => true,  'up' => true ],
				],
				[
					'qs1' => [ 'lag' => 1, 'instance' => 'qs1' ],
					'qs2' => [ 'lag' => 100,  'instance' => 'qs2' ],
				],
				[
					'lag' => 100,
					'instance' => 'qs2'
				]
			],
		];
	}

	/**
	 * @dataProvider provideGetMostLaggedPooledServer
	 */
	public function testGetMostLaggedPooledServer( $lbData, $lagData, $expected ) {
		$lbProvider = $this->newLoadBalancerProvider( $lbData );
		$lagProvider = $this->newLagProvider( $lagData );

		$provider = new MostLaggedPooledServerProvider(
			$lbProvider,
			$lagProvider,
			new NullLogger()
		);
		$result = $provider->getMostLaggedPooledServer();

		$this->assertSame( $expected, $result );
	}

}
