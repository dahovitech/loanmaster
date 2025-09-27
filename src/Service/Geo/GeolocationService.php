<?php

namespace App\Service\Geo;

use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\Cache\CacheInterface;

/**
 * Service for handling geolocation and country detection
 */
class GeolocationService
{
    private const IP_API_URL = 'http://ip-api.com/json/';
    private const CACHE_TTL = 3600; // 1 hour
    private const FALLBACK_COUNTRY = 'FR';

    public function __construct(
        private RequestStack $requestStack,
        private HttpClientInterface $httpClient,
        private LoggerInterface $logger,
        private CacheInterface $cache
    ) {}

    /**
     * Get user's country based on IP address with caching and fallback
     */
    public function getUserCountry(): string
    {
        $request = $this->requestStack->getCurrentRequest();
        if (!$request) {
            return self::FALLBACK_COUNTRY;
        }

        $ipAddress = $request->getClientIp();
        if (!$ipAddress || $this->isPrivateIp($ipAddress)) {
            return self::FALLBACK_COUNTRY;
        }

        $cacheKey = 'geo_country_' . md5($ipAddress);

        try {
            return $this->cache->get($cacheKey, function () use ($ipAddress) {
                return $this->fetchCountryFromApi($ipAddress);
            });
        } catch (\Throwable $e) {
            $this->logger->warning('Failed to get user country', [
                'ip' => $ipAddress,
                'error' => $e->getMessage()
            ]);
            return self::FALLBACK_COUNTRY;
        }
    }

    private function fetchCountryFromApi(string $ipAddress): string
    {
        try {
            $response = $this->httpClient->request('GET', self::IP_API_URL . $ipAddress, [
                'timeout' => 5,
                'headers' => ['User-Agent' => 'LoanMaster/1.0']
            ]);

            $data = $response->toArray();
            
            if ($data['status'] === 'success' && !empty($data['countryCode'])) {
                return strtoupper($data['countryCode']);
            }
        } catch (\Throwable $e) {
            $this->logger->error('IP geolocation API failed', [
                'ip' => $ipAddress,
                'error' => $e->getMessage()
            ]);
        }

        return self::FALLBACK_COUNTRY;
    }

    private function isPrivateIp(string $ip): bool
    {
        return !filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE);
    }
}