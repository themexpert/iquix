<?php

namespace IQuix\Tests\Unit\License;

use IQuix\Setup\Config;
use IQuix\Setup\Http\Response;
use IQuix\Setup\License\LicenseClient;
use IQuix\Setup\License\LicenseException;
use IQuix\Setup\License\Status;
use IQuix\Tests\Support\ArrayStore;
use IQuix\Tests\Support\FakeHttpClient;
use PHPUnit\Framework\TestCase;

final class LicenseClientTest extends TestCase
{
    private function client(array $responses, ArrayStore $store = null): array
    {
        $store  = $store ?? new ArrayStore();
        $http   = new FakeHttpClient($responses);
        $client = new LicenseClient(new Config($store), $store, $http, 'https://example.test');

        return [$client, $store, $http];
    }

    public function testActivatePostsTheLegacyDigicomItemId(): void
    {
        [$client, , $http] = $this->client([
            new Response(200, '{"success":true,"status":"valid","activation_hash":"h1"}'),
        ]);

        $client->activate('KEY-123456789');

        $this->assertSame('https://my.converslabs.com/?fluent-cart=activate_license', $http->lastUrl());
        $this->assertSame('116', $http->lastParams()['item_id']);
        $this->assertSame('KEY-123456789', $http->lastParams()['license_key']);
        $this->assertSame('https://example.test', $http->lastParams()['site_url']);
    }

    public function testActivateStoresTheRowNamesComQuixReads(): void
    {
        [$client, $store] = $this->client([
            new Response(200, '{"success":true,"status":"valid","activation_hash":"h1","expiration_date":"2027-01-01"}'),
        ]);

        $client->activate('KEY-123456789');

        $this->assertSame('KEY-123456789', $store->get('license_key'));
        $this->assertSame('h1', $store->get('activation_hash'));
        $this->assertSame('valid', $store->get('license_status'));
        $this->assertSame('2027-01-01', $store->get('license_expires'));
        $this->assertSame('1', $store->get('activated'));
        $this->assertNotSame('', $store->get('license_checked'));
    }

    public function testActivateTrimsTheKey(): void
    {
        [$client, $store] = $this->client([
            new Response(200, '{"success":true,"status":"valid"}'),
        ]);

        $client->activate('  KEY-123456789  ');

        $this->assertSame('KEY-123456789', $store->get('license_key'));
    }

    public function testActivateRejectsAnEmptyKeyWithoutCallingTheServer(): void
    {
        [$client, , $http] = $this->client([]);

        $this->expectException(LicenseException::class);

        try {
            $client->activate('   ');
        } finally {
            $this->assertSame([], $http->requests());
        }
    }

    public function testActivateThrowsWithTheMappedMessageWhenTheServerRejects(): void
    {
        [$client, $store] = $this->client([
            new Response(200, '{"success":false,"error_type":"activation_limit_reached"}'),
        ]);

        try {
            $client->activate('KEY-123456789');
            $this->fail('Expected LicenseException');
        } catch (LicenseException $e) {
            $this->assertStringContainsString('no more activations left', $e->getMessage());
        }

        $this->assertSame('', $store->get('license_key'));
    }

    public function testActivateSurfacesANonJsonBodyRatherThanFailingSilently(): void
    {
        [$client] = $this->client([new Response(403, 'Missing license key.')]);

        $this->expectException(LicenseException::class);
        $this->expectExceptionMessageMatches('/Missing license key/');

        $client->activate('KEY-123456789');
    }

    public function testCheckReturnsNullWhenNothingIsStored(): void
    {
        [$client, , $http] = $this->client([]);

        $this->assertNull($client->check());
        $this->assertSame([], $http->requests());
    }

    public function testCheckPrefersTheActivationHashOverTheRawKey(): void
    {
        $store = new ArrayStore(['license_key' => 'KEY-123456789', 'activation_hash' => 'h1']);
        [$client, , $http] = $this->client([new Response(200, '{"status":"valid"}')], $store);

        $client->check();

        $this->assertSame('h1', $http->lastParams()['activation_hash']);
        $this->assertArrayNotHasKey('license_key', $http->lastParams());
    }

    public function testCheckFallsBackToTheKeyWhenThereIsNoHash(): void
    {
        $store = new ArrayStore(['license_key' => 'KEY-123456789']);
        [$client, , $http] = $this->client([new Response(200, '{"status":"valid"}')], $store);

        $client->check();

        $this->assertSame('KEY-123456789', $http->lastParams()['license_key']);
    }

    public function testCheckMarksTheSiteActivatedOnAValidStatus(): void
    {
        $store = new ArrayStore(['license_key' => 'KEY-123456789', 'activated' => '0']);
        [$client] = $this->client([new Response(200, '{"status":"valid"}')], $store);

        $client->check();

        $this->assertSame('1', $store->get('activated'));
    }

    public function testCheckDeactivatesOnInvalidExpiredAndDisabled(): void
    {
        foreach ([Status::INVALID, Status::EXPIRED, Status::DISABLED] as $status) {
            $store = new ArrayStore(['license_key' => 'KEY-123456789', 'activated' => '1']);
            [$client] = $this->client([new Response(200, '{"status":"' . $status . '"}')], $store);

            $client->check();

            $this->assertSame('0', $store->get('activated'), 'status ' . $status . ' must deactivate');
            $this->assertSame($status, $store->get('license_status'));
        }
    }

    public function testCheckKeepsCurrentStateWhenTheServerIsUnreachable(): void
    {
        $store = new ArrayStore(['license_key' => 'KEY-123456789', 'activated' => '1']);
        [$client] = $this->client([new \RuntimeException('network down')], $store);

        $this->assertNull($client->check());
        $this->assertSame('1', $store->get('activated'));
        $this->assertNotSame('', $store->get('license_checked'));
    }

    public function testDeactivateClearsLocalStateEvenWhenTheServerFails(): void
    {
        $store = new ArrayStore([
            'license_key'     => 'KEY-123456789',
            'activation_hash' => 'h1',
            'activated'       => '1',
        ]);
        [$client] = $this->client([new \RuntimeException('network down')], $store);

        $client->deactivate();

        $this->assertSame('0', $store->get('activated'));
        $this->assertSame('', $store->get('activation_hash'));
        $this->assertSame(Status::DEACTIVATED, $store->get('license_status'));
    }

    public function testIsActivatedReflectsTheStoredFlag(): void
    {
        [$client] = $this->client([], new ArrayStore(['activated' => '1']));

        $this->assertTrue($client->isActivated());
    }

    public function testErrorMessageFallsBackToTheServerText(): void
    {
        $message = LicenseClient::errorMessage('some_new_code', 'Server said no');

        $this->assertStringContainsString('Server said no', $message);
        $this->assertStringContainsString('some_new_code', $message);
    }
}
