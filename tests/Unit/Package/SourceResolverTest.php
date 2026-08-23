<?php

namespace IQuix\Tests\Unit\Package;

use IQuix\Setup\Config;
use IQuix\Setup\Http\Response;
use IQuix\Setup\Package\SourceResolver;
use IQuix\Tests\Support\ArrayStore;
use IQuix\Tests\Support\FakeHttpClient;
use PHPUnit\Framework\TestCase;

final class SourceResolverTest extends TestCase
{
    private const PRO_XML = <<<'XML'
<?xml version="1.0" encoding="utf-8"?>
<updates>
  <update>
    <name>Quix Pro Update</name>
    <element>pkg_quix</element>
    <type>package</type>
    <version>6.2.7</version>
    <downloads>
      <downloadurl type="full" format="zip">https://my.converslabs.com/?fcdc_joomla=download&amp;pid=116</downloadurl>
    </downloads>
  </update>
</updates>
XML;

    private const FREE_XML = <<<'XML'
<?xml version="1.0" encoding="utf-8"?>
<updates>
  <update>
    <element>pkg_quix</element>
    <version>6.2.7</version>
    <downloads>
      <downloadurl type="full" format="zip">https://github.com/themexpert/quix-free/releases/download/6.2.7/pkg_quix_free.zip</downloadurl>
    </downloads>
    <sha256>bdd4e43e9792fb553ff84e83d71db7386b726d2573e20893ea1b7e928fa0e743</sha256>
  </update>
</updates>
XML;

    private function resolver(array $responses, array $stored = []): SourceResolver
    {
        $store = new ArrayStore($stored);

        return new SourceResolver(
            new Config($store),
            $store,
            new FakeHttpClient($responses),
            'https://example.test'
        );
    }

    public function testProSourceAppendsLicenseCredentialsToTheDownloadUrl(): void
    {
        $resolver = $this->resolver(
            [new Response(200, self::PRO_XML)],
            ['license_key' => 'KEY-123456789', 'activation_hash' => 'h1']
        );

        $source = $resolver->resolve('pro');

        $this->assertStringContainsString('license_key=KEY-123456789', $source->url);
        $this->assertStringContainsString('activation_hash=h1', $source->url);
        $this->assertStringContainsString('site_url=' . urlencode('https://example.test'), $source->url);
        $this->assertStringContainsString('pid=116', $source->url);
        $this->assertSame('6.2.7', $source->version);
        $this->assertSame('pro', $source->edition);
    }

    public function testProSourceHasNoHashBecauseTheServerDoesNotPublishOne(): void
    {
        $resolver = $this->resolver(
            [new Response(200, self::PRO_XML)],
            ['license_key' => 'KEY-123456789']
        );

        $this->assertSame('', $resolver->resolve('pro')->sha256);
    }

    public function testFreeSourceCarriesThePublishedSha256(): void
    {
        $source = $this->resolver([new Response(200, self::FREE_XML)])->resolve('free');

        $this->assertSame(
            'bdd4e43e9792fb553ff84e83d71db7386b726d2573e20893ea1b7e928fa0e743',
            $source->sha256
        );
        $this->assertSame('free', $source->edition);
        $this->assertStringNotContainsString('license_key', $source->url);
    }

    public function testFreeSourceIsFetchedFromTheGithubManifest(): void
    {
        $http = new FakeHttpClient([new Response(200, self::FREE_XML)]);
        $store = new ArrayStore();
        $resolver = new SourceResolver(new Config($store), $store, $http, 'https://example.test');

        $resolver->resolve('free');

        $this->assertSame(
            'https://raw.githubusercontent.com/themexpert/quix-free/main/jed.xml',
            $http->lastUrl()
        );
    }

    public function testAnHttpErrorIsReportedWithItsStatusCode(): void
    {
        $resolver = $this->resolver([new Response(503, 'Service Unavailable')]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/503/');

        $resolver->resolve('free');
    }

    public function testANonXmlBodyIsSurfacedVerbatim(): void
    {
        $resolver = $this->resolver([new Response(200, 'Missing license key.')]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/Missing license key/');

        $resolver->resolve('free');
    }

    public function testAManifestWithNoDownloadUrlIsRejected(): void
    {
        $xml = '<?xml version="1.0"?><updates><update><version>6.2.7</version></update></updates>';
        $resolver = $this->resolver([new Response(200, $xml)]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/download/i');

        $resolver->resolve('free');
    }

    public function testAnUnknownEditionIsRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->resolver([])->resolve('enterprise');
    }
}
