<?php

namespace App\Service;

use Symfony\Contracts\HttpClient\ResponseInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class ApiService
{
    private HttpClientInterface $client;
    private string $apiUrl;

    public function __construct(HttpClientInterface $client, string $apiUrl)
    {
        $this->client = $client;
        $this->apiUrl = $apiUrl;
    }

    public function translate($data=[
        'q' => '',
        'source' => 'auto',
        'target' => '',
        'format' => 'text',
        'alternatives' => 3,
    ]): string
    {
        $response = $this->client->request('POST', $this->apiUrl."/translate", [
            'json' => [
                'q' => $data['q'],
                'source' => isset($data['source'])?$data['source']:'auto',
                'target' => $data['target'],
                'format' => isset($data['format'])?$data['format']:'text',
                'alternatives' => isset($data['alternatives'])?$data['alternatives']:3,
            ],
        ]);

        $content = $response->getContent();
        $data = json_decode($content, true);

        return $data['translatedText'];
    }


    public function translateAsync(array $data): ResponseInterface
    {
        return $this->client->request('POST', $this->apiUrl."/translate", [
            'json' => [
                'q' => $data['q'],
                'source' => $data['source'] ?? 'auto',
                'target' => $data['target'],
                'format' => $data['format'] ?? 'text',
                'alternatives' => $data['alternatives'] ?? 3,
            ],
        ]);
    }

    public function parseTranslateResponse(ResponseInterface $response): string
    {
        $content = $response->getContent();
        $data = json_decode($content, true);

        return $data['translatedText'] ?? '';
    }

    public function languages(){
        $response = $this->client->request('GET', $this->apiUrl."/languages");

        $content = $response->getContent();
        $data = json_decode($content, true);


        return $data;
    }
}
