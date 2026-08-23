<?php

namespace IQuix\Tests\Unit;

use IQuix\Setup\Config;
use IQuix\Setup\UpdateSite;
use IQuix\Tests\Support\ArrayStore;
use PHPUnit\Framework\TestCase;

final class UpdateSiteTest extends TestCase
{
    public function testExtraQueryCarriesTheCredentialsTheDownloadEndpointNeeds(): void
    {
        $store = new ArrayStore(['license_key' => 'KEY-123', 'activation_hash' => 'h1']);

        $query = UpdateSite::extraQuery(new Config($store), $store, 'https://example.test');

        parse_str($query, $params);

        $this->assertSame('KEY-123', $params['license_key']);
        $this->assertSame('h1', $params['activation_hash']);
        $this->assertSame('116', $params['item_id']);
        $this->assertSame('https://example.test', $params['site_url']);
    }

    public function testExtraQueryIsEmptyWhenThereIsNoLicense(): void
    {
        $store = new ArrayStore();

        $this->assertSame('', UpdateSite::extraQuery(new Config($store), $store, 'https://example.test'));
    }

    public function testTheUpdateSiteNameMatchesTheOneComQuixWrites(): void
    {
        $this->assertSame('Quix Update Site', Config::UPDATE_SITE_NAME);
    }
}
