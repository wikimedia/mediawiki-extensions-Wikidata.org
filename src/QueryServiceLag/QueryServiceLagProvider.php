<?php

namespace WikidataOrg\QueryServiceLag;

/**
 * Provides lag of a connected query service
 *
 * @license GPL-2.0-or-later
 * @author Marius Hoch
 */
interface QueryServiceLagProvider {

	/**
	 * Get the lag of a connected query service
	 *
	 * @return int|null Lag in seconds or null if no valid lag is determined
	 */
	public function getLag();

}
