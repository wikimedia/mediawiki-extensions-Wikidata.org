<?php

namespace WikidataOrg\QueryServiceLag;

use Psr\Log\LoggerInterface;

class MostLaggedPooledServerProvider {

	/**
	 * @var WikimediaLoadBalancerQueryServicePoolStatusProvider
	 */
	private $lbStatusProvider;

	/**
	 * @var WikimediaPrometheusQueryServiceLagProvider
	 */
	private $rawLagProvider;

	/**
	 * @var LoggerInterface
	 */
	private $logger;

	/**
	 * @param WikimediaLoadBalancerQueryServicePoolStatusProvider $lbStatusProvider
	 * @param WikimediaPrometheusQueryServiceLagProvider $rawLagProvider
	 * @param LoggerInterface $logger
	 */
	public function __construct(
		WikimediaLoadBalancerQueryServicePoolStatusProvider $lbStatusProvider,
		WikimediaPrometheusQueryServiceLagProvider $rawLagProvider,
		LoggerInterface $logger
	) {
		$this->lbStatusProvider = $lbStatusProvider;
		$this->rawLagProvider = $rawLagProvider;
		$this->logger = $logger;
	}

	/**
	 * @return null|array Array with keys 'lag' and 'instance' or null
	 */
	public function getMostLaggedPooledServer() {
		$rawLags = $this->rawLagProvider->getLags();
		$lbStatus = $this->lbStatusProvider->getStatus();

		$mostLagged = null;

		foreach ( $rawLags as $instanceName => $lagDetails ) {
			if ( !$this->isInstancePooled( $instanceName, $lbStatus ) ) {
				continue;
			}

			if ( !$mostLagged || $lagDetails['lag'] > $mostLagged['lag'] ) {
				$mostLagged = $lagDetails;
			}
		}

		if ( $mostLagged === null ) {
			$this->logger->warning(
				'{method}: Failed to get any pooled servers',
				[
					'method' => __METHOD__,
				]
			);
			return null;
		}

		// Ensure our return contract
		if ( !array_key_exists( 'lag', $mostLagged ) || !array_key_exists( 'instance', $mostLagged ) ) {
			$this->logger->warning(
				'{method}: Most lagged server did not have expected lag and instance keys, had {keys}',
				[
					'method' => __METHOD__,
					'keys' => implode( ',', array_keys( $mostLagged ) ),
				]
			);
			return null;
		}

		return $mostLagged;
	}

	/**
	 * @param string $instanceName
	 * @param array $lbStatus
	 * @return bool
	 */
	private function isInstancePooled( $instanceName, array $lbStatus ) {
		if ( !array_key_exists( $instanceName, $lbStatus ) ) {
			// If we have no load balancer details for the instance it cant be pooled
			return false;
		}

		if (
			!$lbStatus[$instanceName]['pooled'] ||
			!$lbStatus[$instanceName]['enabled'] ||
			!$lbStatus[$instanceName]['up']
		) {
			// Got load balancer details for instance, but it is not pooled
			return false;
		}

		return true;
	}
}
