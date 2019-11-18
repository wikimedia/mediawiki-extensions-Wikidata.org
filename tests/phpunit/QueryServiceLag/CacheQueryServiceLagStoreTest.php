<?php

namespace WikidataOrg\Tests\QueryServiceLag;

use HashBagOStuff;
use PHPUnit4And6Compat;
use WANObjectCache;
use WikidataOrg\QueryServiceLag\CacheQueryServiceLagStore;

/**
 * @covers \WikidataOrg\QueryServiceLag\CacheQueryServiceLagStore
 */
class CacheQueryServiceLagStoreTest extends \PHPUnit\Framework\TestCase {
	use PHPUnit4And6Compat;

	/** @var WANObjectCache */
	private $hashCache;

	/** @var string */
	private $cacheKeyVariation;

	/** @var int */
	private $ttl;

	/**
	 * @return void
	 */
	public function setUp(): void {
		$this->hashCache = new WANObjectCache( [ 'cache' => new HashBagOStuff() ] );
		$this->cacheKeyVariation = '';
		$this->ttl = 10;
	}

	public function testSuject_onInvalidTTL_throws() {
		$this->ttl = -1;

		$this->setExpectedException( \InvalidArgumentException::class );

		$this->makeSubject();
	}

	public function testSubject_onEmptyCache_retrievesNoLag() {
		$subject = $this->makeSubject();

		$this->assertNull( $subject->getLag() );
	}

	public function testSuject_onExpiredCache_retrievesNoLag() {
		$this->ttl = 3600;

		$subject = $this->makeSubject();

		$subject->updateLag( 10 );

		$mockedTime = time() + $this->ttl + 1;
		$this->hashCache->setMockTime( $mockedTime );

		$this->assertNull( $subject->getLag() );
	}

	public function testSubject_onNonExpiredCache_retrievesLag() {
		$subject = $this->makeSubject();
		$lag = 10;

		$subject->updateLag( $lag );

		$mockedTime = time() + $this->ttl - 1;
		$this->hashCache->setMockTime( $mockedTime );

		$this->assertEquals( $lag, $subject->getLag() );
	}

	public function testSubject_whenUpdatingLag_updatesLagAndTTL() {
		$subject = $this->makeSubject();

		$now = time();

		// Store old lag with mocked current time
		$oldMockedTime = $now + 10;
		$this->hashCache->setMockTime( $oldMockedTime );

		$oldLag = 42;
		$subject->updateLag( $oldLag );

		// oldLag is now expiring at now + 20
		// move mocked current time to right before oldLag
		// expiry and update it with newLag
		$newMockedTime = $now + 19;
		$this->hashCache->setMockTime( $newMockedTime );

		$newLag = 23;
		$subject->updateLag( $newLag );

		// newLag now will expire at now + 29
		// move mocked current time to right before newLag
		// expires
		$newMockedTime = $now + 28;
		$this->hashCache->setMockTime( $newMockedTime );

		$this->assertEquals( $newLag, $subject->getLag() );
	}

	private function makeSubject() {
		return new CacheQueryServiceLagStore(
			$this->hashCache,
			$this->ttl,
			$this->cacheKeyVariation
		);
	}
}
