<?php

namespace IQuix\Tests\Unit\License;

use IQuix\Setup\Config;
use IQuix\Setup\Http\Response;
use IQuix\Setup\License\LicenseClient;
use IQuix\Setup\License\LicenseGate;
use IQuix\Setup\License\Status;
use IQuix\Tests\Support\ArrayStore;
use IQuix\Tests\Support\FakeHttpClient;
use PHPUnit\Framework\TestCase;

final class LicenseGateTest extends TestCase
{
    private function gate(array $responses, array $stored = []): array
    {
        $store  = new ArrayStore($stored);
        $http   = new FakeHttpClient($responses);
        $client = new LicenseClient(new Config($store), $store, $http, 'https://example.test');

        return [new LicenseGate($client, $store), $store, $http];
    }

    public function testAValidStoredKeySkipsThePrompt(): void
    {
        [$gate] = $this->gate(
            [new Response(200, '{"status":"valid"}')],
            ['license_key' => 'KEY-123456789', 'activation_hash' => 'h1']
        );

        $result = $gate->evaluate();

        $this->assertTrue($result['licensed']);
        $this->assertFalse($result['prompt']);
        $this->assertSame('pro', $result['edition']);
        $this->assertSame('KEY-*****6789', $result['maskedKey']);
        $this->assertSame(Status::VALID, $result['status']);
    }

    public function testAnExpiredStoredKeyPromptsWithTheReason(): void
    {
        [$gate] = $this->gate(
            [new Response(200, '{"status":"expired"}')],
            ['license_key' => 'KEY-123456789']
        );

        $result = $gate->evaluate();

        $this->assertFalse($result['licensed']);
        $this->assertTrue($result['prompt']);
        $this->assertSame(Status::EXPIRED, $result['status']);
        $this->assertStringContainsString('expired', strtolower($result['reason']));
    }

    public function testNoStoredCredentialsPromptsWithoutCallingTheServer(): void
    {
        [$gate, , $http] = $this->gate([]);

        $result = $gate->evaluate();

        $this->assertTrue($result['prompt']);
        $this->assertFalse($result['licensed']);
        $this->assertSame('free', $result['edition']);
        $this->assertSame('', $result['maskedKey']);
        $this->assertSame([], $http->requests());
    }

    public function testALegacyAuthKeyIsTriedAsALicenseKeyBeforeAsking(): void
    {
        // The DigiCom to FluentCart migrator carried customer auth keys over
        // verbatim, so an old iQuix `key` row is very often the current
        // license key.
        [$gate, $store, $http] = $this->gate(
            [new Response(200, '{"success":true,"status":"valid","activation_hash":"h9"}')],
            ['username' => 'someone', 'key' => 'LEGACY-987654321']
        );

        $result = $gate->evaluate();

        $this->assertTrue($result['licensed']);
        $this->assertFalse($result['prompt']);
        $this->assertSame('LEGACY-987654321', $store->get('license_key'));
        $this->assertSame('h9', $store->get('activation_hash'));
        $this->assertStringContainsString('activate_license', $http->lastUrl());
    }

    public function testARejectedLegacyKeyFallsThroughToThePrompt(): void
    {
        [$gate, $store] = $this->gate(
            [new Response(200, '{"success":false,"error_type":"invalid_license"}')],
            ['username' => 'someone', 'key' => 'LEGACY-987654321']
        );

        $result = $gate->evaluate();

        $this->assertTrue($result['prompt']);
        $this->assertFalse($result['licensed']);
        $this->assertSame('', $store->get('license_key'));
    }

    public function testAnUnreachableServerPromptsRatherThanClaimingLicensed(): void
    {
        [$gate] = $this->gate(
            [new \RuntimeException('network down')],
            ['license_key' => 'KEY-123456789']
        );

        $result = $gate->evaluate();

        $this->assertTrue($result['prompt']);
        $this->assertFalse($result['licensed']);
    }

    public function testTheStoredKeyIsNeverReturnedInTheClear(): void
    {
        [$gate] = $this->gate(
            [new Response(200, '{"status":"valid"}')],
            ['license_key' => 'KEY-123456789']
        );

        $result = $gate->evaluate();

        $this->assertStringNotContainsString('123456789', json_encode($result));
    }
}
