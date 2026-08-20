<?php

namespace IQuix\Setup\License;

defined('_JEXEC') or die('Unauthorized Access');

use IQuix\Setup\Log;
use IQuix\Setup\StoreInterface;

/**
 * Decides whether the wizard has to ask for a license key at all.
 *
 * A site that already carries valid credentials — from a previous Quix
 * install, or from old iQuix — is never asked again.
 */
final class LicenseGate
{
    public function __construct(
        private readonly LicenseClient $client,
        private readonly StoreInterface $store
    ) {
    }

    /**
     * @return array{licensed: bool, edition: string, maskedKey: string, status: string, prompt: bool, reason: string}
     */
    public function evaluate(): array
    {
        if ($this->store->has('license_key') || $this->store->has('activation_hash')) {
            return $this->fromCheck();
        }

        $legacy = trim($this->store->get('key'));

        if ($legacy !== '') {
            return $this->fromLegacyKey($legacy);
        }

        return $this->promptResult('', '', '');
    }

    /**
     * Stored credentials exist — ask the server whether they are still good.
     */
    private function fromCheck(): array
    {
        $response = $this->client->check();
        $status   = $this->store->get('license_status');

        if ($response === null && $status !== Status::VALID) {
            // Server unreachable and no prior "valid" verdict to lean on.
            return $this->promptResult(
                $this->store->get('license_key'),
                $status,
                'We could not reach the license server. Enter your key to continue, or retry.'
            );
        }

        if ($this->client->isActivated()) {
            return $this->licensedResult();
        }

        return $this->promptResult(
            $this->store->get('license_key'),
            $status,
            LicenseClient::errorMessage($status)
        );
    }

    /**
     * Old iQuix stored a DigiCom username plus auth key. The migrator carried
     * those keys into FluentCart verbatim, so try it before asking.
     */
    private function fromLegacyKey(string $legacy): array
    {
        Log::debug('Trying legacy auth key as a FluentCart license key');

        try {
            $this->client->activate($legacy);
        } catch (LicenseException $e) {
            return $this->promptResult('', Status::INVALID, $e->getMessage());
        }

        return $this->licensedResult();
    }

    private function licensedResult(): array
    {
        return [
            'licensed'  => true,
            'edition'   => 'pro',
            'maskedKey' => Log::maskKey($this->store->get('license_key')),
            'status'    => $this->store->get('license_status'),
            'prompt'    => false,
            'reason'    => '',
        ];
    }

    private function promptResult(string $key, string $status, string $reason): array
    {
        return [
            'licensed'  => false,
            'edition'   => 'free',
            'maskedKey' => $key === '' ? '' : Log::maskKey($key),
            'status'    => $status,
            'prompt'    => true,
            'reason'    => $reason,
        ];
    }
}
