<?php

namespace IQuix\Setup\Package;

defined('_JEXEC') or die('Unauthorized Access');

use IQuix\Setup\Config;
use Joomla\Filesystem\Folder;
use IQuix\Setup\Log;

/**
 * Downloads a package archive into Joomla's tmp path with TLS verified, a
 * size cap, and an integrity check.
 *
 * The archive never touches the component directory: writing it under
 * administrator/components/com_iquix/ made the paid package downloadable by
 * anyone who guessed the URL.
 */
final class Downloader
{
    /** Working directories older than this are abandoned; sweep them. */
    private const STALE_AFTER = 86400;

    public function __construct(private readonly string $tmpPath)
    {
    }

    /**
     * @return string absolute path to the downloaded archive
     *
     * @throws \RuntimeException on transport failure or a bad archive
     */
    public function fetch(Source $source): string
    {
        $this->sweepStale();

        $dir = $this->tmpPath . '/iquix-' . bin2hex(random_bytes(8));

        if (!@mkdir($dir, 0700, true) && !is_dir($dir)) {
            throw new \RuntimeException('Could not create a temporary directory at ' . $this->tmpPath);
        }

        $path   = $dir . '/pkg_quix.zip';
        $handle = fopen($path, 'w+b');

        if ($handle === false) {
            throw new \RuntimeException('Could not open ' . $path . ' for writing.');
        }

        Log::debug('Downloading package ' . Log::redactUrl($source->url));

        $curl = curl_init();
        curl_setopt_array($curl, self::curlOptions($source->url, $handle));

        $ok    = curl_exec($curl);
        $error = curl_error($curl);
        $code  = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);

        curl_close($curl);
        fclose($handle);

        if ($ok === false) {
            $this->cleanup($path);

            throw new \RuntimeException('The download failed: ' . ($error ?: 'unknown transport error'));
        }

        if ($code !== 200) {
            $body = trim(strip_tags((string) file_get_contents($path, false, null, 0, 500)));
            $this->cleanup($path);

            throw new \RuntimeException(sprintf(
                'The download server returned HTTP %d: %s',
                $code,
                $body === '' ? '(empty response)' : $body
            ));
        }

        try {
            self::verify($path, $source->sha256);
        } catch (\RuntimeException $e) {
            $this->cleanup($path);

            throw $e;
        }

        Log::debug('Package downloaded to ' . $path . ' (' . filesize($path) . ' bytes)');

        return $path;
    }

    /**
     * The security-critical option map, exposed so it can be asserted on
     * without making a network call.
     *
     * @param resource $handle
     */
    public static function curlOptions(string $url, $handle): array
    {
        return [
            CURLOPT_URL             => $url,
            CURLOPT_FILE            => $handle,
            CURLOPT_TIMEOUT         => Config::DOWNLOAD_TIMEOUT,
            CURLOPT_CONNECTTIMEOUT  => 30,
            CURLOPT_FOLLOWLOCATION  => true,
            CURLOPT_MAXREDIRS       => 3,
            CURLOPT_PROTOCOLS       => CURLPROTO_HTTPS,
            CURLOPT_REDIR_PROTOCOLS => CURLPROTO_HTTPS,
            CURLOPT_SSL_VERIFYPEER  => true,
            CURLOPT_SSL_VERIFYHOST  => 2,
            CURLOPT_USERAGENT       => Config::USER_AGENT,
            CURLOPT_NOPROGRESS      => false,
            CURLOPT_PROGRESSFUNCTION => static function ($resource, $downloadSize, $downloaded) {
                // Abort rather than fill the disk if the server misbehaves.
                return $downloaded > Config::MAX_PACKAGE_BYTES ? 1 : 0;
            },
        ];
    }

    /**
     * @throws \RuntimeException when the file is empty, is not a zip, or does
     *                           not match the published checksum
     */
    public static function verify(string $path, string $expectedSha256): void
    {
        $size = is_file($path) ? (int) filesize($path) : 0;

        if ($size === 0) {
            throw new \RuntimeException('The downloaded file was empty.');
        }

        $handle = fopen($path, 'rb');
        $magic  = (string) fread($handle, 4);
        fclose($handle);

        // Every zip starts "PK\x03\x04". Anything else is a server error page
        // or a plain-text message saved under a .zip name.
        if (strncmp($magic, "PK\x03\x04", 4) !== 0) {
            $body = trim(strip_tags((string) file_get_contents($path, false, null, 0, 500)));

            throw new \RuntimeException(
                'The server did not return a package: ' . ($body === '' ? '(binary junk)' : $body)
            );
        }

        if ($expectedSha256 === '') {
            Log::debug('No checksum published for this package; skipping integrity check');

            return;
        }

        $actual = hash_file('sha256', $path);

        if (!hash_equals($expectedSha256, (string) $actual)) {
            throw new \RuntimeException(
                'The downloaded package failed its checksum check. Please try again, and contact support if it keeps happening.'
            );
        }
    }

    /**
     * Remove working directories left by runs that never finished.
     *
     * A run that fails part way keeps its extraction directory on purpose, so
     * the user can retry and resume. But a user who gives up — closes the tab,
     * or never comes back after a failed step — leaves it there for good, and
     * each one holds an unpacked copy of the package. Sweeping anything older
     * than a day bounds that without ever touching a run still in progress.
     */
    private function sweepStale(): void
    {
        $cutoff = time() - self::STALE_AFTER;

        foreach ((array) glob($this->tmpPath . '/iquix-*', GLOB_ONLYDIR) as $dir) {
            $age = @filemtime($dir);

            if ($age === false || $age > $cutoff) {
                continue;
            }

            try {
                Folder::delete($dir);
                Log::debug('Swept stale download directory ' . basename($dir));
            } catch (\Throwable $e) {
                // A directory we cannot remove is not worth failing a download over.
                Log::debug('Could not sweep ' . basename($dir) . ': ' . $e->getMessage());
            }
        }
    }

    public function cleanup(string $path): void
    {
        if (is_file($path)) {
            @unlink($path);
        }

        $dir = dirname($path);

        if (is_dir($dir) && str_starts_with(basename($dir), 'iquix-')) {
            @rmdir($dir);
        }
    }
}
