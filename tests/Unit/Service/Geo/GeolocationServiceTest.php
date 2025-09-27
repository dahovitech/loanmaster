<?php

namespace App\Tests\Unit\Service\Geo;

use App\Service\Geo\GeolocationService;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

class GeolocationServiceTest extends TestCase
{
    private GeolocationService $service;
    private RequestStack $requestStack;
    private HttpClientInterface $httpClient;
    private LoggerInterface $logger;
    private CacheInterface $cache;

    protected function setUp(): void
    {
        $this->requestStack = $this->createMock(RequestStack::class);
        $this->httpClient = $this->createMock(HttpClientInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->cache = $this->createMock(CacheInterface::class);

        $this->service = new GeolocationService(
            $this->requestStack,
            $this->httpClient,
            $this->logger,
            $this->cache
        );
    }

    public function testGetUserCountryWithNoRequest(): void
    {
        $this->requestStack->method('getCurrentRequest')->willReturn(null);
        
        $result = $this->service->getUserCountry();
        
        $this->assertEquals('FR', $result);
    }

    public function testGetUserCountryWithPrivateIp(): void
    {
        $request = $this->createMock(Request::class);
        $request->method('getClientIp')->willReturn('192.168.1.1');
        
        $this->requestStack->method('getCurrentRequest')->willReturn($request);
        
        $result = $this->service->getUserCountry();
        
        $this->assertEquals('FR', $result);
    }

    public function testGetUserCountryWithValidPublicIp(): void
    {
        $request = $this->createMock(Request::class);
        $request->method('getClientIp')->willReturn('8.8.8.8');
        
        $this->requestStack->method('getCurrentRequest')->willReturn($request);
        
        $response = $this->createMock(ResponseInterface::class);
        $response->method('toArray')->willReturn([
            'status' => 'success',
            'countryCode' => 'US'
        ]);
        
        $this->httpClient->method('request')->willReturn($response);
        
        $this->cache->method('get')->willReturnCallback(function ($key, $callback) {
            return $callback();
        });
        
        $result = $this->service->getUserCountry();
        
        $this->assertEquals('US', $result);
    }

    public function testGetUserCountryWithApiFailure(): void
    {
        $request = $this->createMock(Request::class);
        $request->method('getClientIp')->willReturn('8.8.8.8');
        
        $this->requestStack->method('getCurrentRequest')->willReturn($request);
        
        $this->httpClient->method('request')->willThrowException(new \Exception('API Error'));
        
        $this->cache->method('get')->willReturnCallback(function ($key, $callback) {
            return $callback();
        });
        
        $this->logger->expects($this->once())->method('error');
        
        $result = $this->service->getUserCountry();
        
        $this->assertEquals('FR', $result);
    }

    public function testGetUserCountryWithInvalidApiResponse(): void
    {
        $request = $this->createMock(Request::class);
        $request->method('getClientIp')->willReturn('8.8.8.8');
        
        $this->requestStack->method('getCurrentRequest')->willReturn($request);
        
        $response = $this->createMock(ResponseInterface::class);
        $response->method('toArray')->willReturn([
            'status' => 'fail',
            'message' => 'Invalid IP'
        ]);
        
        $this->httpClient->method('request')->willReturn($response);
        
        $this->cache->method('get')->willReturnCallback(function ($key, $callback) {
            return $callback();
        });
        
        $result = $this->service->getUserCountry();
        
        $this->assertEquals('FR', $result);
    }
}