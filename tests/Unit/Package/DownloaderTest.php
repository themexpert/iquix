<?php

namespace IQuix\Tests\Unit\Package;

use IQuix\Setup\Config;
use IQuix\Setup\Package\Downloader;
use PHPUnit\Framework\TestCase;

final class DownloaderTest extends TestCase
{
    private string $tmp;

    protected function setUp(): void
    {
        $this->tmp = sys_get_temp_dir() . '/iquix-test-' . bin2hex(random_bytes(6));
        mkdir($this->tmp, 0700, true);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->tmp . '/*') ?: [] as $file) {
            @unlink($file);
        }

        @rmdir($this->tmp);
    }

    private function writeZip(string $name): string
    {
        $path = $this->tmp . '/' . $name;
        $zip  = new \ZipArchive();
        $zip->open($path, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);
        $zip->addFromString('pkg_quix.xml', '<extension type="package"/>');
        $zip->close();

        return $path;
    }

    public function testCurlOptionsEnforceTlsVerification(): void
    {
        $handle  = fopen('php://memory', 'w+');
        $options = Downloader::curlOptions('https://my.converslabs.com/', $handle);
        fclose($handle);

        $this->assertTrue($options[CURLOPT_SSL_VERIFYPEER]);
        $this->assertSame(2, $options[CURLOPT_SSL_VERIFYHOST]);
    }

    public function testCurlOptionsCapRedirectsAndRestrictThemToHttps(): void
    {
        $handle  = fopen('php://memory', 'w+');
        $options = Downloader::curlOptions('https://my.converslabs.com/', $handle);
        fclose($handle);

        $this->assertTrue($options[CURLOPT_FOLLOWLOCATION]);
        $this->assertSame(3, $options[CURLOPT_MAXREDIRS]);
        $this->assertSame(CURLPROTO_HTTPS, $options[CURLOPT_REDIR_PROTOCOLS]);
    }

    public function testCurlOptionsSetABoundedTimeoutNotUnlimited(): void
    {
        $handle  = fopen('php://memory', 'w+');
        $options = Downloader::curlOptions('https://my.converslabs.com/', $handle);
        fclose($handle);

        $this->assertSame(Config::DOWNLOAD_TIMEOUT, $options[CURLOPT_TIMEOUT]);
        $this->assertGreaterThan(0, $options[CURLOPT_TIMEOUT]);
    }

    public function testVerifyAcceptsAZipWhoseHashMatches(): void
    {
        $path = $this->writeZip('good.zip');

        Downloader::verify($path, hash_file('sha256', $path));

        $this->assertFileExists($path);
    }

    public function testVerifyAcceptsAZipWhenNoHashIsPublished(): void
    {
        $path = $this->writeZip('nohash.zip');

        Downloader::verify($path, '');

        $this->assertFileExists($path);
    }

    public function testVerifyRejectsAHashMismatch(): void
    {
        $path = $this->writeZip('bad.zip');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/checksum/i');

        Downloader::verify($path, str_repeat('a', 64));
    }

    public function testVerifyRejectsAServerErrorStringSavedAsAZip(): void
    {
        $path = $this->tmp . '/error.zip';
        file_put_contents($path, 'Missing license key.');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/Missing license key/');

        Downloader::verify($path, '');
    }

    public function testVerifyRejectsAnEmptyDownload(): void
    {
        $path = $this->tmp . '/empty.zip';
        file_put_contents($path, '');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/empty/i');

        Downloader::verify($path, '');
    }

    public function testCleanupRemovesTheArchive(): void
    {
        $path = $this->writeZip('gone.zip');

        (new Downloader($this->tmp))->cleanup($path);

        $this->assertFileDoesNotExist($path);
    }
}
