<?php

namespace IQuix\Tests\Support;

use IQuix\Setup\Http\ClientInterface;
use IQuix\Setup\Http\Response;

final class FakeHttpClient implements ClientInterface
{
    /** @var list<Response|\RuntimeException> */
    private array $queue;

    /** @var list<array{method: string, url: string, params: array}> */
    private array $requests = [];

    /**
     * @param list<Response|\RuntimeException> $queue
     */
    public function __construct(array $queue)
    {
        $this->queue = $queue;
    }

    public function get(string $url, array $params = []): Response
    {
        return $this->record('get', $url, $params);
    }

    public function post(string $url, array $params = []): Response
    {
        return $this->record('post', $url, $params);
    }

    public function lastUrl(): string
    {
        return $this->requests === [] ? '' : end($this->requests)['url'];
    }

    public function lastParams(): array
    {
        return $this->requests === [] ? [] : end($this->requests)['params'];
    }

    /**
     * @return list<array{method: string, url: string, params: array}>
     */
    public function requests(): array
    {
        return $this->requests;
    }

    private function record(string $method, string $url, array $params): Response
    {
        $this->requests[] = ['method' => $method, 'url' => $url, 'params' => $params];

        if ($this->queue === []) {
            throw new \RuntimeException('FakeHttpClient: no response queued for ' . $method . ' ' . $url);
        }

        $next = array_shift($this->queue);

        if ($next instanceof \RuntimeException) {
            throw $next;
        }

        return $next;
    }
}
