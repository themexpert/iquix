<?php

namespace IQuix\Tests\Unit;

use IQuix\Setup\Config;
use IQuix\Tests\Support\ArrayStore;
use PHPUnit\Framework\TestCase;

final class ConfigTest extends TestCase
{
    public function testDefaultStoreUrlIsTheFluentCartServer(): void
    {
        $config = new Config(new ArrayStore());

        $this->assertSame('https://my.converslabs.com', $config->storeUrl());
    }

    public function testStoreUrlOverrideIsHonouredAndTrailingSlashStripped(): void
    {
        $config = new Config(new ArrayStore(['store_url' => 'http://converslab.test/']));

        $this->assertSame('http://converslab.test', $config->storeUrl());
    }

    public function testDefaultItemIdIsTheLegacyDigicomPid(): void
    {
        $config = new Config(new ArrayStore());

        $this->assertSame('116', $config->itemId());
    }

    public function testItemIdOverrideIsHonoured(): void
    {
        $config = new Config(new ArrayStore(['item_id' => '662']));

        $this->assertSame('662', $config->itemId());
    }

    public function testLicenseUrlUsesTheFluentCartActionParameter(): void
    {
        $config = new Config(new ArrayStore());

        $this->assertSame(
            'https://my.converslabs.com/?fluent-cart=activate_license',
            $config->licenseUrl('activate_license')
        );
    }

    public function testProUpdateXmlUrlCarriesTheItemId(): void
    {
        $config = new Config(new ArrayStore());

        $this->assertSame(
            'https://my.converslabs.com/?fcdc_joomla=update&pid=116',
            $config->proUpdateXmlUrl()
        );
    }

    public function testProUpdateXmlUrlFollowsTheStoreUrlOverride(): void
    {
        $config = new Config(new ArrayStore(['store_url' => 'http://converslab.test']));

        $this->assertSame(
            'http://converslab.test/?fcdc_joomla=update&pid=116',
            $config->proUpdateXmlUrl()
        );
    }

    public function testFreeUpdateXmlUrlIsThePublicGithubManifest(): void
    {
        $config = new Config(new ArrayStore());

        $this->assertSame(
            'https://raw.githubusercontent.com/themexpert/quix-free/main/jed.xml',
            $config->freeUpdateXmlUrl()
        );
    }

    public function testNoEndpointPointsAtTheRetiredThemexpertApi(): void
    {
        $config = new Config(new ArrayStore());

        $urls = [
            $config->storeUrl(),
            $config->licenseUrl('check_license'),
            $config->proUpdateXmlUrl(),
            $config->freeUpdateXmlUrl(),
            $config->selfUpdateXmlUrl(),
        ];

        foreach ($urls as $url) {
            $this->assertStringNotContainsString('themexpert.com', $url);
            $this->assertStringNotContainsString('com_digicom', $url);
        }
    }
}
