<?php

namespace WikidataOrg\QueryServiceLag;

use WANObjectCache;

/**
 * Manages retrieving and updating query service lag data in cache
 */
class CacheQueryServiceLagStore implements QueryServiceLagProvider, CacheQueryServiceLagUpdater {

	private const CACHE_CLASS = 'CacheQueryServiceLagStore';
	private const CACHE_KEY_LAG = 'lag';

	/** @var WANObjectCache */
	private $cache;

	/** @var string */
	private $cacheKeyVariation;

	/** @var int */
	private $ttl;

	/**
	 * @param WANObjectCache $cache
	 * @param int $ttl positive time-to-live in seconds for cached lag
	 * @param string $cacheKeyVariation
	 */
	public function __construct(
		WANObjectCache $cache,
		$ttl,
		$cacheKeyVariation = ''
	) {
		if ( !is_int( $ttl ) || $ttl <= 0 ) {
			throw new \InvalidArgumentException( '$ttl cannot be less or equal to 0' );
		}

		$this->cache = $cache;
		$this->ttl = $ttl;
		$this->cacheKeyVariation = $cacheKeyVariation;
	}

	/**
	 * Retrives lag from underlying cache medium
	 *
	 * @return int|null
	 */
	public function getLag() {
		$lag = $this->cache->get( $this->makeCacheKey( self::CACHE_KEY_LAG ) );
		return $lag === false ? null : $lag;
	}

	/**
	 * Updates stored lag in cache.
	 *
	 * @param int $lag
	 */
	public function updateLag( $lag ) {
		if ( is_int( $lag ) && $lag < 0 ) {
			throw new \InvalidArgumentException( '$lag must be null or a non-negative integer.' );
		}

		$this->cache->set(
			$this->makeCacheKey( self::CACHE_KEY_LAG ),
			$lag,
			$this->ttl
		);
	}

	/**
	 * @param string $type
	 * @return string
	 */
	private function makeCacheKey( $type ) {
		return $this->cache->makeKey( self::CACHE_CLASS, $type, $this->cacheKeyVariation );
	}

}
