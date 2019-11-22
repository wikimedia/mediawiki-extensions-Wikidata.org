<?php

namespace WikidataOrg\QueryServiceLag;

use MediaWiki\Http\HttpRequestFactory;
use Psr\Log\LoggerInterface;

class WikimediaLoadBalancerQueryServicePoolStatusProvider {

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
	private $loadBalancerUrls;

	/**
	 * @var string
	 */
	private $pool;

	/**
	 * @param HttpRequestFactory $httpRequestFactory
	 * @param LoggerInterface $logger
	 * @param string[] $loadBalancerUrls LoadBalancer base URLs e.g. http://lvs1015:9090
	 * @param string $pool e.g. wdqs_80
	 */
	public function __construct(
		HttpRequestFactory $httpRequestFactory,
		LoggerInterface $logger,
		$loadBalancerUrls,
		$pool
	) {
		$this->loadBalancerUrls = $loadBalancerUrls;
		$this->pool = $pool;
		$this->httpRequestFactory = $httpRequestFactory;
		$this->logger = $logger;
	}

	/**
	 * @return array[] Keys: Instance names such as wdqs1007
	 *                 Values: mixed[] with keys pooled(bool), enabled(bool), up(bool), weight(int)
	 */
	public function getStatus() {
		$result = [];

		foreach ( $this->loadBalancerUrls as $loadBalancerUrl ) {
			$requestUrl = $loadBalancerUrl . '/pools/' . $this->pool;
			// XXX: Custom timeout?
			$request = $this->httpRequestFactory->create(
				$requestUrl,
				[],
				__METHOD__
			);
			$request->setHeader( 'Accept', 'application/json' );
			$requestStatus = $request->execute();

			if ( !$requestStatus->isOK() ) {
				$this->logger->warning(
					'{method}: Request to LoadBalancer API {apiUrl} failed with {error}',
					[
						'method' => __METHOD__,
						'apiUrl' => $requestUrl,
						'error' => $requestStatus->getMessage()->inContentLanguage()->text()
					]
				);
				continue;
			}

			$value = json_decode( $request->getContent(), true );

			if ( !is_array( $value ) ) {
				$this->logger->warning(
					'{method}: Request to LoadBalancer API {apiUrl} has unexpected value {value}',
					[
						'method' => __METHOD__,
						'apiUrl' => $requestUrl,
						'value' => $request->getContent()
					]
				);
				continue;
			}

			foreach ( $value as $key => $status ) {
				$result[explode( '.', $key )[0]] = $status;
			}
		}
		return $result;
	}

}
