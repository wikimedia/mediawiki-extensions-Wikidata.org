<?php

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
class WikimediaPrometheusQueryServiceLagProvider implements QueryServiceLagProvider {

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
		$prometheusUrls,
		$relevantClusters
	) {
		$this->prometheusUrls = $prometheusUrls;
		$this->relevantClusters = $relevantClusters;
		$this->httpRequestFactory = $httpRequestFactory;
		$this->logger = $logger;
	}

	/**
	 * @return int|null Lag in seconds or null if the lag couldn't be determined.
	 */
	public function getLag() {
		$lags = $this->getLags();

		// Take the median lag +1
		sort( $lags );
		return $lags[(int)floor( count( $lags ) / 2 + 1 )] ?? null;
	}

	/**
	 * @return int[]
	 */
	private function getLags() {
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
				} elseif ( !in_array( $components['cluster'], $this->relevantClusters ) ) {
					continue;
				}

				$result[] = time() - $components['lastUpdated'];
			}
		}

		return $result;
	}

	private function getResultComponents( array $result ): ?array {
		$cluster = $result['metric']['cluster'] ?? null;
		$lastUpdated = $result['value'][1] ?? null;

		if ( !$cluster || !$lastUpdated ) {
			return null;
		}

		return [
			'cluster' => $cluster,
			'lastUpdated' => $lastUpdated,
		];
	}
}
