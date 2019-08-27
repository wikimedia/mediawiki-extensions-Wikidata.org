<?php

namespace WikidataOrg\QueryServiceLag;

/**
 * Provides lag of a connected query service
 *
 * @license GPL-2.0-or-later
 * @author Matthias Geisler
 * @author Alaa Sarhan
 */
interface CacheQueryServiceLagUpdater {

	/**
	 * Set the lag of a connected query service in cache store
	 *
	 * @param int $lag
	 */
	public function updateLag( $lag );
}
