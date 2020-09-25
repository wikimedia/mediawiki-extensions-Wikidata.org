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
final class WikimediaPrometheusQueryServiceLagProvider implements QueryServiceLagProvider {

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
	 * @return int|null Lag in seconds or null if the lag couldn't be determined.
	 */
	public function getLag(): ?int {
		$lags = $this->getLags();

		// Take the median lag +1
		sort( $lags );
		return $lags[(int)floor( count( $lags ) / 2 + 1 )] ?? null;
	}

	private function getLags(): array {
		$result = [];
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

				if ( !$this->isRelevantCluster( $resultByInstance ) ) {
					continue;
				}
				$components = $this->getResultComponents( $resultByInstance );

				if ( !$components ) {
					$this->logger->warning(
						'{method}: unexpected result from Prometheus API {apiUrl}',
						[
							'method' => __METHOD__,
							'apiUrl' => $prometheusUrl,
						]
					);

					continue;
				}

				$result[] = time() - $components['lastUpdated'];
			}
		}

		return $result;
	}

	private function isRelevantCluster( array $result ): bool {
		// This is intended to remove wdqs-test from the results
		$cluster = $result['metric']['cluster'] ?? null;
		return in_array( $cluster, $this->relevantClusters, true );
	}

	private function getResultComponents( array $result ): ?array {
		$cluster = $result['metric']['cluster'] ?? null;
		$lastUpdated = $result['value'][1] ?? null;

		/**
		 * Prometheus can sometimes return non numeric values in cases where a machine is in
		 * some offline state. "NaN" for example.
		 * So only count services that actually return numeric values.
		 */
		if ( !$cluster || !$lastUpdated || !is_numeric( $lastUpdated ) ) {
			return null;
		}

		return [
			'cluster' => $cluster,
			'lastUpdated' => $lastUpdated,
		];
	}
}
