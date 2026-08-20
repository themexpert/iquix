<?php

namespace IQuix\Setup\Package;

defined('_JEXEC') or die('Unauthorized Access');

use IQuix\Setup\Config;
use IQuix\Setup\Http\ClientInterface;
use IQuix\Setup\Http\Response;
use IQuix\Setup\Log;
use IQuix\Setup\StoreInterface;

/**
 * Turns an edition into a concrete download URL and expected hash by reading
 * the relevant Joomla update manifest.
 */
final class SourceResolver
{
    public function __construct(
        private readonly Config $config,
        private readonly StoreInterface $store,
        private readonly ClientInterface $http,
        private readonly string $siteUrl
    ) {
    }

    public function resolve(string $edition): Source
    {
        return match ($edition) {
            'pro'   => $this->resolvePro(),
            'free'  => $this->resolveFree(),
            default => throw new \InvalidArgumentException('Unknown edition: ' . $edition),
        };
    }

    private function resolvePro(): Source
    {
        $update = $this->fetchManifest($this->config->proUpdateXmlUrl());

        $credentials = http_build_query([
            'license_key'     => $this->store->get('license_key'),
            'activation_hash' => $this->store->get('activation_hash'),
            'site_url'        => $this->siteUrl,
        ]);

        $url = $update['url'] . (str_contains($update['url'], '?') ? '&' : '?') . $credentials;

        return new Source($url, $update['version'], $update['sha256'], 'pro');
    }

    private function resolveFree(): Source
    {
        $update = $this->fetchManifest($this->config->freeUpdateXmlUrl());

        if ($update['sha256'] === '') {
            Log::debug('Free manifest published no sha256; integrity cannot be verified');
        }

        return new Source($update['url'], $update['version'], $update['sha256'], 'free');
    }

    /**
     * @return array{url: string, version: string, sha256: string}
     */
    private function fetchManifest(string $url): array
    {
        Log::debug('Fetching update manifest ' . Log::redactUrl($url));

        $response = $this->http->get($url);

        if (!$response->isOk()) {
            throw new \RuntimeException(sprintf(
                'The update server returned HTTP %d for %s: %s',
                $response->code,
                parse_url($url, PHP_URL_HOST) ?: $url,
                $this->snippet($response)
            ));
        }

        $xml = $this->parse($response);

        $update = $xml->update[0] ?? null;

        if ($update === null) {
            throw new \RuntimeException('The update manifest contained no releases.');
        }

        $downloadUrl = trim((string) ($update->downloads->downloadurl ?? ''));

        if ($downloadUrl === '') {
            throw new \RuntimeException('The update manifest contained no download URL.');
        }

        return [
            'url'     => $downloadUrl,
            'version' => trim((string) ($update->version ?? '')),
            'sha256'  => strtolower(trim((string) ($update->sha256 ?? ''))),
        ];
    }

    /**
     * A non-XML body means the server answered with an error string such as
     * "Missing license key." — surface it instead of a parse failure.
     */
    private function parse(Response $response): \SimpleXMLElement
    {
        $previous = libxml_use_internal_errors(true);
        $xml      = simplexml_load_string($response->body);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if ($xml === false) {
            throw new \RuntimeException(
                'The update server did not return a manifest: ' . $this->snippet($response)
            );
        }

        return $xml;
    }

    private function snippet(Response $response): string
    {
        $text = trim(strip_tags($response->body));

        return $text === '' ? '(empty response)' : substr($text, 0, 200);
    }
}
