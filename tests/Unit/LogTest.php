<?php

namespace IQuix\Tests\Unit;

use IQuix\Setup\Log;
use PHPUnit\Framework\TestCase;

final class LogTest extends TestCase
{
    public function testMaskKeyStarsOutShortKeysEntirely(): void
    {
        $this->assertSame('********', Log::maskKey('abcdefgh'));
        $this->assertSame('***', Log::maskKey('abc'));
        $this->assertSame('', Log::maskKey(''));
    }

    public function testMaskKeyKeepsFirstAndLastFourOfLongKeys(): void
    {
        $this->assertSame('abcd******wxyz', Log::maskKey('abcde12345wxyz'));
    }

    public function testRedactMasksSensitiveKeysAndLeavesOthers(): void
    {
        $redacted = Log::redact([
            'license_key'     => 'abcde12345wxyz',
            'activation_hash' => 'hhhhhiiiiijjjjj',
            'item_id'         => '116',
        ]);

        $this->assertSame('abcd******wxyz', $redacted['license_key']);
        $this->assertSame('hhhh*******jjjj', $redacted['activation_hash']);
        $this->assertSame('116', $redacted['item_id']);
    }

    public function testRedactLeavesEmptySensitiveValuesAlone(): void
    {
        $this->assertSame(['license_key' => ''], Log::redact(['license_key' => '']));
    }

    public function testRedactUrlMasksCredentialsInQueryString(): void
    {
        $url = Log::redactUrl(
            'https://my.converslabs.com/?fcdc_joomla=download&pid=116&license_key=abcde12345wxyz'
        );

        $this->assertStringNotContainsString('abcde12345wxyz', $url);
        $this->assertStringContainsString('license_key=abcd%2A%2A%2A%2A%2A%2Awxyz', $url);
        $this->assertStringContainsString('pid=116', $url);
    }

    public function testRedactUrlLeavesUrlsWithoutQueryStringUnchanged(): void
    {
        $url = 'https://my.converslabs.com/';

        $this->assertSame($url, Log::redactUrl($url));
    }
}
