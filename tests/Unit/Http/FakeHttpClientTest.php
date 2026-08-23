<?php

namespace IQuix\Tests\Unit\Http;

use IQuix\Setup\Http\ClientInterface;
use IQuix\Setup\Http\Response;
use IQuix\Tests\Support\FakeHttpClient;
use PHPUnit\Framework\TestCase;

final class FakeHttpClientTest extends TestCase
{
    public function testItImplementsTheClientInterface(): void
    {
        $this->assertInstanceOf(ClientInterface::class, new FakeHttpClient([]));
    }

    public function testItReturnsQueuedResponsesInOrder(): void
    {
        $client = new FakeHttpClient([
            new Response(200, 'first'),
            new Response(403, 'second'),
        ]);

        $this->assertSame('first', $client->get('https://example.test')->body);
        $this->assertSame('second', $client->post('https://example.test')->body);
    }

    public function testItRecordsTheUrlAndParametersOfEachCall(): void
    {
        $client = new FakeHttpClient([new Response(200, 'ok')]);
        $client->post('https://example.test/?a=1', ['license_key' => 'k']);

        $this->assertSame('https://example.test/?a=1', $client->lastUrl());
        $this->assertSame(['license_key' => 'k'], $client->lastParams());
        $this->assertCount(1, $client->requests());
    }

    public function testItFailsLoudlyWhenNoResponseIsQueued(): void
    {
        $this->expectException(\RuntimeException::class);

        (new FakeHttpClient([]))->get('https://example.test');
    }
}
