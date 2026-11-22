<?php

declare(strict_types=1);

namespace Tests\Support\HTTP;

use CodeIgniter\HTTP\ResponseInterface;
use CodeIgniter\HTTP\URI;
use CodeIgniter\Test\Mock\MockCURLRequest;
use CodeIgniter\Test\Mock\MockResponse;
use Config\App;

/**
 * Reusable MockCURLRequest double supporting queued HTTP responses, exceptions,
 * and recorded per-request cURL options.
 */
class QueuedMockCURLRequest extends MockCURLRequest
{
    /**
     * @var list<string|\Throwable>
     */
    private array $queue = [];

    /**
     * @var list<array<int, mixed>>
     */
    public array $recordedCurlOptions = [];

    /**
     * @var list<array{method: string, url: string, options: array<string, mixed>, curl_options: array<int, mixed>}>
     */
    public array $recordedRequests = [];

    /**
     * @var array{method: string, url: string, options: array<string, mixed>}|null
     */
    private ?array $pendingRequest = null;

    /**
     * @param array<string, mixed> $options
     */
    public function __construct(
        ?App $config = null,
        ?URI $uri = null,
        ?ResponseInterface $response = null,
        array $options = ['http_errors' => false],
    ) {
        $config ??= config(App::class);
        $uri ??= new URI('http://orthanc.test:8042');
        $response ??= new MockResponse($config);

        parent::__construct($config, $uri, $response, $options);
    }

    public function queueJson(int $status, mixed $payload, ?string $reason = null): self
    {
        $encoded = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $body    = $encoded === false ? '{}' : $encoded;
        $phrase  = $reason ?? $this->reasonPhrase($status);

        $this->queue[] = "HTTP/1.1 {$status} {$phrase}\r\nContent-Type: application/json\r\n\r\n{$body}";

        return $this;
    }

    public function queueRaw(int $status, string $body = '', ?string $reason = null): self
    {
        $phrase        = $reason ?? $this->reasonPhrase($status);
        $this->queue[] = "HTTP/1.1 {$status} {$phrase}\r\n\r\n{$body}";

        return $this;
    }

    public function queueException(\Throwable $exception): self
    {
        $this->queue[] = $exception;

        return $this;
    }

    /**
     * @param string               $method
     * @param array<string, mixed> $options
     */
    public function request($method, string $url, array $options = []): ResponseInterface
    {
        $this->pendingRequest = [
            'method'  => strtoupper((string) $method),
            'url'     => $url,
            'options' => $options,
        ];

        try {
            return parent::request($method, $url, $options);
        } finally {
            $this->pendingRequest = null;
        }
    }

    /**
     * @param array<int, mixed> $curlOptions
     */
    protected function sendRequest(array $curlOptions = []): string
    {
        $this->response               = clone $this->responseOrig;
        $this->curl_options           = $curlOptions;
        $this->recordedCurlOptions[]  = $curlOptions;

        $pending = $this->pendingRequest ?? [
            'method'  => (string) ($curlOptions[CURLOPT_CUSTOMREQUEST] ?? 'GET'),
            'url'     => (string) ($curlOptions[CURLOPT_URL] ?? ''),
            'options' => [],
        ];

        $this->recordedRequests[] = [
            'method'       => $pending['method'],
            'url'          => $pending['url'],
            'options'      => $pending['options'],
            'curl_options' => $curlOptions,
        ];

        if ($this->queue === []) {
            return $this->output;
        }

        $next = array_shift($this->queue);
        if ($next instanceof \Throwable) {
            throw $next;
        }

        return $next;
    }

    /**
     * @return array<int, mixed>
     */
    public function lastCurlOptions(): array
    {
        if ($this->recordedCurlOptions === []) {
            return [];
        }

        return $this->recordedCurlOptions[array_key_last($this->recordedCurlOptions)];
    }

    /**
     * @return array{method: string, url: string, options: array<string, mixed>, curl_options: array<int, mixed>}|null
     */
    public function lastRequest(): ?array
    {
        if ($this->recordedRequests === []) {
            return null;
        }

        return $this->recordedRequests[array_key_last($this->recordedRequests)];
    }

    private function reasonPhrase(int $status): string
    {
        return match ($status) {
            200 => 'OK',
            201 => 'Created',
            204 => 'No Content',
            400 => 'Bad Request',
            401 => 'Unauthorized',
            403 => 'Forbidden',
            404 => 'Not Found',
            422 => 'Unprocessable Entity',
            500 => 'Internal Server Error',
            502 => 'Bad Gateway',
            503 => 'Service Unavailable',
            504 => 'Gateway Timeout',
            default => 'Status',
        };
    }
}
