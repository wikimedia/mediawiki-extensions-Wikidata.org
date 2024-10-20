<?php

namespace WikidataOrg\QueryServiceLag;

use Wikimedia\ObjectCache\WANObjectCache;

/**
 * Manages retrieving and updating query service lag data in cache
 */
class CacheQueryServiceLagStore {

	private const CACHE_CLASS = 'CacheQueryServiceLagStore';
	private const CACHE_KEY_LAG = 'lag';

	/** @var WANObjectCache */
	private $cache;

	/** @var int */
	private $ttl;

	/**
	 * @param WANObjectCache $cache
	 * @param int $ttl positive time-to-live in seconds for cached lag
	 */
	public function __construct(
		WANObjectCache $cache,
		$ttl
	) {
		if ( !is_int( $ttl ) || $ttl <= 0 ) {
			throw new \InvalidArgumentException( '$ttl cannot be less or equal to 0' );
		}

		$this->cache = $cache;
		$this->ttl = $ttl;
	}

	/**
	 * Retrieves lag from underlying cache medium
	 *
	 * @return array|null
	 */
	public function getLag() {
		$lagData = $this->cache->get( $this->makeCacheKey( self::CACHE_KEY_LAG ) );

		// No cached value
		if ( $lagData === false ) {
			return null;
		}

		// Back compat for before server was also stored in cache
		if ( strstr( $lagData, ' ' ) === false ) {
			return [ 'unknown', $lagData ];
		}

		// Current storage is server and lag value separated by a space
		$values = explode( ' ', $lagData );
		$values[1] = (int)$values[1];
		return $values;
	}

	/**
	 * Updates stored lag in cache.
	 *
	 * @param string $server
	 * @param int $lag
	 */
	public function updateLag( $server, $lag ) {
		if ( !is_string( $server ) ) {
			throw new \InvalidArgumentException( '$server must be string.' );
		}
		if ( is_int( $lag ) && $lag < 0 ) {
			throw new \InvalidArgumentException( '$lag must be null or a non-negative integer.' );
		}

		$this->cache->set(
			$this->makeCacheKey( self::CACHE_KEY_LAG ),
			$server . ' ' . $lag,
			$this->ttl
		);
	}

	/**
	 * @param string $type
	 * @return string
	 */
	private function makeCacheKey( $type ) {
		return $this->cache->makeKey( self::CACHE_CLASS, $type );
	}

}
