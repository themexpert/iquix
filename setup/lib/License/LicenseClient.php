<?php

namespace IQuix\Setup\License;

defined('_JEXEC') or die('Unauthorized Access');

use IQuix\Setup\Config;
use IQuix\Setup\Http\ClientInterface;
use IQuix\Setup\Http\Response;
use IQuix\Setup\Log;
use IQuix\Setup\StoreInterface;

/**
 * FluentCart public license API client.
 *
 * Mirrors QuixHelperLicense in com_quix and writes the same #__quix_configs
 * rows, so Quix is already activated when this installer finishes.
 */
final class LicenseClient
{
    public function __construct(
        private readonly Config $config,
        private readonly StoreInterface $store,
        private readonly ClientInterface $http,
        private readonly string $siteUrl
    ) {
    }

    public function storedKey(): string
    {
        return $this->store->get('license_key');
    }

    public function isActivated(): bool
    {
        return $this->store->get('activated') === '1';
    }

    /**
     * @throws LicenseException when the key is empty or the server rejects it
     */
    public function activate(string $licenseKey): object
    {
        $licenseKey = trim($licenseKey);

        if ($licenseKey === '') {
            throw new LicenseException('Please enter your license key.');
        }

        $response = $this->request('activate_license', [
            'license_key'      => $licenseKey,
            'item_id'          => $this->config->itemId(),
            'site_url'         => $this->siteUrl,
            'server_version'   => PHP_VERSION,
            'platform_version' => JVERSION,
        ], 'post');

        $status = $response->status ?? Status::VALID;

        if (empty($response->success) || $status !== Status::VALID) {
            throw new LicenseException(self::errorMessage(
                (string) ($response->error_type ?? $response->code ?? $status),
                (string) ($response->message ?? '')
            ));
        }

        $this->store->setMany([
            'license_key'     => $licenseKey,
            'activation_hash' => (string) ($response->activation_hash ?? ''),
            'license_status'  => $status,
            'license_expires' => (string) ($response->expiration_date ?? ''),
            'license_checked' => (string) time(),
            'activated'       => '1',
        ]);

        return $response;
    }

    /**
     * Re-validate stored credentials. Returns null when there is nothing to
     * check or the server could not be reached — in the latter case the
     * current state is deliberately left alone.
     */
    public function check(): ?object
    {
        $key  = $this->store->get('license_key');
        $hash = $this->store->get('activation_hash');

        if ($key === '' && $hash === '') {
            return null;
        }

        $params = [
            'item_id'  => $this->config->itemId(),
            'site_url' => $this->siteUrl,
        ];

        if ($hash !== '') {
            $params['activation_hash'] = $hash;
        } else {
            $params['license_key'] = $key;
        }

        try {
            $response = $this->request('check_license', $params, 'get');
        } catch (LicenseException $e) {
            $this->store->set('license_checked', (string) time());

            return null;
        }

        $status = (string) ($response->status ?? Status::INVALID);

        $update = [
            'license_status'  => $status,
            'license_checked' => (string) time(),
        ];

        if (isset($response->expiration_date)) {
            $update['license_expires'] = (string) $response->expiration_date;
        }

        if (in_array($status, Status::REVOKING, true)) {
            $update['activated'] = '0';
        } elseif ($status === Status::VALID) {
            $update['activated'] = '1';
        }

        $this->store->setMany($update);

        return $response;
    }

    /**
     * Local state is always cleared, even when the remote call fails — a
     * revoked key must not leave the site stuck "activated".
     */
    public function deactivate(): void
    {
        $key = $this->store->get('license_key');

        if ($key !== '') {
            try {
                $this->request('deactivate_license', [
                    'license_key' => $key,
                    'item_id'     => $this->config->itemId(),
                    'site_url'    => $this->siteUrl,
                ], 'post');
            } catch (LicenseException $e) {
                // Intentionally swallowed; local state is cleared regardless.
            }
        }

        $this->store->setMany([
            'activation_hash' => '',
            'license_status'  => Status::DEACTIVATED,
            'license_checked' => (string) time(),
            'activated'       => '0',
        ]);
    }

    public static function errorMessage(string $error, string $message = ''): string
    {
        $renew = Config::STORE_URL;

        $errors = [
            'activation_limit_reached' => sprintf(
                'You have no more activations left. <a href="%s" target="_blank" rel="noopener">Upgrade your license</a> to add this site.',
                $renew
            ),
            'expired' => sprintf(
                'Your license has expired. <a href="%s" target="_blank" rel="noopener">Renew it</a> to install and keep receiving updates.',
                $renew
            ),
            'invalid_license'  => 'That license key is not valid. Please check it and try again.',
            'invalid'          => 'That license key is not valid. Please check it and try again.',
            'validation_error' => 'The license key could not be validated. Please check it and try again.',
            'missing'          => 'No license was found for that key. Please check it and try again.',
            'disabled'         => 'This license key has been cancelled, most likely after a refund. Please use a current license.',
            'revoked'          => 'This license key has been cancelled, most likely after a refund. Please use a current license.',
            'key_mismatch'     => 'This license is not valid for this domain. Please check your key again.',
        ];

        if (isset($errors[$error])) {
            return $errors[$error];
        }

        if ($message !== '') {
            return $message . ' (' . $error . ')';
        }

        return 'The license server could not be reached. Check your connection and try again. (' . $error . ')';
    }

    /**
     * @param array<string, string> $params
     *
     * @throws LicenseException on transport failure or a non-JSON body
     */
    private function request(string $action, array $params, string $method): object
    {
        $url = $this->config->licenseUrl($action);

        Log::debug('License ' . $action, $params);

        try {
            $response = $method === 'get'
                ? $this->http->get($url, $params)
                : $this->http->post($url, $params);
        } catch (\RuntimeException $e) {
            throw new LicenseException($e->getMessage(), 0, $e);
        }

        $body = $response->json();

        if ($body === null) {
            throw new LicenseException($this->describeBadResponse($response));
        }

        return $body;
    }

    /**
     * A non-JSON body is a firewall page, a WAF block, or a plain-text server
     * error such as "Missing license key." — say what actually happened.
     */
    private function describeBadResponse(Response $response): string
    {
        $snippet = trim(strip_tags($response->body));
        $snippet = $snippet === '' ? '(empty response)' : substr($snippet, 0, 200);

        return sprintf(
            'The license server returned an unexpected response (HTTP %d): %s',
            $response->code,
            $snippet
        );
    }
}
