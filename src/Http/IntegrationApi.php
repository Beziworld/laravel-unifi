<?php

declare(strict_types=1);

namespace Beziworld\Unifi\Http;

use Beziworld\Unifi\Exceptions\ApiException;
use Beziworld\Unifi\Exceptions\AuthenticationException;
use Beziworld\Unifi\Exceptions\ConnectionFailed;
use Beziworld\Unifi\Exceptions\InvalidConfiguration;
use Beziworld\Unifi\Exceptions\RateLimitException;
use Beziworld\Unifi\UnifiConfig;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;

/**
 * HTTP transport of the official UniFi Network API (paths under /v1).
 *
 * Requests carry the API key in the X-API-KEY header and go either to the
 * local console (https://console/proxy/network/integration) or through the
 * Ubiquiti cloud connector. GET requests are repeated on connection errors
 * and HTTP 5xx; state-changing requests are sent exactly once.
 */
class IntegrationApi
{
    public function __construct(
        private readonly HttpFactory $http,
        private readonly UnifiConfig $config,
    ) {}

    public function config(): UnifiConfig
    {
        return $this->config;
    }

    public function withConfig(UnifiConfig $config): self
    {
        return new self($this->http, $config);
    }

    /**
     * @param array<string, scalar|null> $query
     * @return array<array-key, mixed>
     */
    public function get(string $path, array $query = []): array
    {
        return $this->send('GET', $path, $query);
    }

    /**
     * @param array<array-key, mixed>|null $body
     * @return array<array-key, mixed>
     */
    public function post(string $path, ?array $body = null): array
    {
        return $this->send('POST', $path, [], $body);
    }

    /**
     * @param array<array-key, mixed> $body
     * @return array<array-key, mixed>
     */
    public function put(string $path, array $body): array
    {
        return $this->send('PUT', $path, [], $body);
    }

    /**
     * @param array<array-key, mixed> $body
     * @return array<array-key, mixed>
     */
    public function patch(string $path, array $body): array
    {
        return $this->send('PATCH', $path, [], $body);
    }

    /**
     * @param array<string, scalar|null> $query
     * @return array<array-key, mixed>
     */
    public function delete(string $path, array $query = []): array
    {
        return $this->send('DELETE', $path, $query);
    }

    /**
     * Sends a request to any endpoint of the official API.
     *
     * @param string $path Path relative to the integration root, e.g. "/v1/sites".
     * @param array<string, scalar|null> $query
     * @param array<array-key, mixed>|null $body
     * @return array<array-key, mixed>
     *
     * @throws AuthenticationException
     * @throws RateLimitException
     * @throws ApiException
     * @throws ConnectionFailed
     */
    public function send(string $method, string $path, array $query = [], ?array $body = null): array
    {
        if ($this->config->apiKey === null) {
            throw InvalidConfiguration::apiKeyRequired($this->config->name);
        }

        $method = strtoupper($method);
        $path = '/' . ltrim($path, '/');
        $operation = $method . ' ' . $path;
        $query = array_filter($query, static fn(mixed $value): bool => $value !== null && $value !== '');
        $attempts = $method === 'GET' ? $this->config->retries + 1 : 1;

        for ($attempt = 1; ; $attempt++) {
            try {
                $options = [];

                if ($query !== []) {
                    $options['query'] = $query;
                }

                if ($body !== null) {
                    $options['json'] = $body;
                }

                $response = $this->request()->send($method, $this->config->integrationUrl() . $path, $options);
            } catch (ConnectionException $exception) {
                if ($attempt < $attempts) {
                    $this->pause($attempt);

                    continue;
                }

                throw ConnectionFailed::during($operation, $this->config->integrationUrl(), $exception);
            }

            if ($response->serverError() && $attempt < $attempts) {
                $this->pause($attempt);

                continue;
            }

            return $this->decode($operation, $response);
        }
    }

    /**
     * @return array<array-key, mixed>
     */
    private function decode(string $operation, Response $response): array
    {
        $payload = $response->json();
        $payload = is_array($payload) ? $payload : [];

        if ($response->status() === 401 || $response->status() === 403) {
            throw AuthenticationException::fromPayload($operation, $response->status(), $payload);
        }

        if ($response->status() === 429) {
            throw RateLimitException::fromResponse($operation, $response);
        }

        if ($response->failed()) {
            throw ApiException::fromPayload($operation, $response->status(), $payload);
        }

        if ($payload === [] && trim($response->body()) !== '' && ! in_array(trim($response->body()), ['[]', '{}'], true)) {
            throw ApiException::unexpected($operation, $response->status());
        }

        return $payload;
    }

    private function pause(int $attempt): void
    {
        if ($this->config->retryDelay > 0) {
            usleep($this->config->retryDelay * 1000 * $attempt);
        }
    }

    private function request(): PendingRequest
    {
        return $this->http
            ->createPendingRequest()
            ->withHeaders(['X-API-KEY' => (string) $this->config->apiKey])
            ->acceptJson()
            ->withUserAgent('beziworld-laravel-unifi/1.0')
            ->withOptions(['verify' => $this->config->usesCloudConnector() ? true : $this->config->verifySsl])
            ->timeout($this->config->timeout)
            ->connectTimeout($this->config->connectTimeout);
    }
}
