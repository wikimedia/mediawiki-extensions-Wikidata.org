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
	 * @param HttpRequestFactory $httpRequestFactory
	 * @param LoggerInterface $logger
	 * @param string[] $prometheusUrls Prometheus query endpoint URLs (.../query)
	 */
	public function __construct(
		HttpRequestFactory $httpRequestFactory,
		LoggerInterface $logger,
		array $prometheusUrls
	) {
		$this->prometheusUrls = $prometheusUrls;
		$this->httpRequestFactory = $httpRequestFactory;
		$this->logger = $logger;
	}

	/**
	 * @return array|null Array with keys 'lag' and 'host' or null
	 */
	public function getLag(): ?array {
		$mostLagged = null;
		foreach ( $this->prometheusUrls as $prometheusUrl ) {
			$fullUrl = $prometheusUrl . '?query=' . rawurlencode( $this->getQuery() );

			// XXX: Custom timeout?
			$request = $this->httpRequestFactory->create( $fullUrl, [], __METHOD__ );
			$requestStatus = $request->execute();

			if ( !$requestStatus->isOK() ) {
				$this->logger->warning(
					__METHOD__ . ': Request to Prometheus API {fullUrl} failed with {error}',
					[
						'fullUrl' => $fullUrl,
						'error' => $requestStatus->getMessage()->inContentLanguage()->text(),
					]
				);
				continue;
			}

			$response = json_decode( $request->getContent(), true );
			$result = $response['data']['result'][0] ?? [];

			if (
				isset( $result['value'][1] ) &&
				isset( $result['metric']['host'] )
			) {
				$maxLag = intval( round( floatval( $result['value'][1] ) ) );
				$host = $result['metric']['host'];

				if ( $mostLagged === null || $mostLagged['lag'] < $maxLag ) {
					$mostLagged = [
						'lag' => $maxLag,
						'host' => $host
					];
				}
			} else {
				$this->logger->warning(
					__METHOD__ . ': unexpected result from Prometheus API {fullUrl}: {response}',
					[
						'fullUrl' => $fullUrl,
						'response' => $request->getContent()
					]
				);
			}

		}

		return $mostLagged;
	}

	private function getQuery(): string {
		return 'topk(1, time() - label_replace(blazegraph_lastupdated, "host", "$1", "instance", "^([^:]+):.*")' .
			'and on(host) label_replace(rate(' .
			'org_wikidata_query_rdf_blazegraph_filters_QueryEventSenderFilter_event_sender_filter_StartedQueries{}' .
			'[5m]) > 1, "host", "$1", "instance", "^([^:]+):.*"))';
	}

}
