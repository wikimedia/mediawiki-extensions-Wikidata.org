<?php

declare( strict_types = 1 );

namespace WikidataOrg\QueryServiceLag;

use MediaWiki\Http\HttpRequestFactory;
use Psr\Log\LoggerInterface;

/**
 * Looks up lag of query service in Prometheous backend.
 *
 * @license GPL-2.0-or-later
 * @author Marius Hoch
 * @author Alaa Sarhan
 */
class WikimediaPrometheusQueryServiceLagProvider {

	/**
	 * @var HttpRequestFactory
	 */
	private $httpRequestFactory;

	/**
	 * @var LoggerInterface
	 */
	private $logger;

	/**
	 * @var string[]
	 */
	private $prometheusUrls;

	/**
	 * @var string[]
	 */
	private $relevantClusters;

	/**
	 * @param HttpRequestFactory $httpRequestFactory
	 * @param LoggerInterface $logger
	 * @param string[] $prometheusUrls Prometheus full query URLs to fetch lag metric
	 * @param string[] $relevantClusters
	 */
	public function __construct(
		HttpRequestFactory $httpRequestFactory,
		LoggerInterface $logger,
		array $prometheusUrls,
		array $relevantClusters
	) {
		$this->prometheusUrls = $prometheusUrls;
		$this->relevantClusters = $relevantClusters;
		$this->httpRequestFactory = $httpRequestFactory;
		$this->logger = $logger;
	}

	/**
	 * @return array[] Keys: Instance names such as wdqs1007
	 *                 Values: mixed[] with keys instance(string), cluster(string), lag(int)
	 */
	public function getLags(): array {
		$results = [];
		foreach ( $this->prometheusUrls as $prometheusUrl ) {
			// XXX: Custom timeout?
			$request = $this->httpRequestFactory->create(
				$prometheusUrl,
				[],
				__METHOD__
			);
			$requestStatus = $request->execute();

			if ( !$requestStatus->isOK() ) {
				$this->logger->warning(
					'{method}: Request to Prometheus API {apiUrl} failed with {error}',
					[
						'method' => __METHOD__,
						'apiUrl' => $prometheusUrl,
						'error' => $requestStatus->getMessage()->inContentLanguage()->text()
					]
				);
				continue;
			}

			$value = json_decode( $request->getContent(), true );
			foreach ( $value['data']['result'] ?? [] as $resultByInstance ) {

				$prettyResult = $this->getResultComponents( $resultByInstance );

				if ( !$prettyResult ) {
					$this->logger->warning(
						'{method}: unexpected result from Prometheus API {apiUrl}',
						[
							'method' => __METHOD__,
							'apiUrl' => $prometheusUrl,
						]
					);

					continue;
				}

				if ( !$this->isRelevantCluster( $prettyResult['cluster'] ) ) {
					continue;
				}

				$results[$prettyResult['instance']] = $prettyResult;
			}
		}

		return $results;
	}

	private function isRelevantCluster( string $cluster ): bool {
		return in_array( $cluster, $this->relevantClusters, true );
	}

	private function getResultComponents( array $result ): ?array {
		$cluster = $result['metric']['cluster'] ?? null;
		$instance = $result['metric']['instance']
			? explode( ':', $result['metric']['instance'] )[0]
			: null;

		if ( !$cluster || !$instance ) {
			return null;
		}

		// https://prometheus.io/docs/prometheus/latest/querying/api/#expression-query-result-formats
		$lastUpdatedTimestamp = $result['value'][0] ?? null;
		$lastUpdatedValue = $result['value'][1] ?? null;

		if ( !is_float( $lastUpdatedTimestamp ) ||
			!is_string( $lastUpdatedValue ) || !ctype_digit( $lastUpdatedValue ) ) {
			// Incomplete data: Server is almost certainly not in a valid state and thus not pooled (T321710).
			return null;
		}

		return [
			'cluster' => $cluster,
			'instance' => $instance,
			'lag' => $lastUpdatedTimestamp - intval( $lastUpdatedValue ),
		];
	}
}
