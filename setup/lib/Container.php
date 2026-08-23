<?php

namespace IQuix\Setup;

defined('_JEXEC') or die('Unauthorized Access');

use IQuix\Setup\Http\ClientInterface;
use IQuix\Setup\Http\JoomlaClient;
use IQuix\Setup\License\LicenseClient;
use IQuix\Setup\License\LicenseGate;
use IQuix\Setup\Package\Downloader;
use IQuix\Setup\Package\PackageInstaller;
use IQuix\Setup\Package\SourceResolver;
use Joomla\CMS\Factory;
use Joomla\CMS\Uri\Uri;

final class Container
{
    private array $made = [];

    public function siteUrl(): string
    {
        return rtrim(Uri::root(), '/');
    }

    /**
     * Joomla's configured tmp path. The package must land here and nowhere
     * under administrator/components, which is served to the public.
     */
    public function tmpPath(): string
    {
        return (string) Factory::getApplication()->get('tmp_path', JPATH_ROOT . '/tmp');
    }

    public function store(): StoreInterface
    {
        return $this->made['store'] ??= new Store();
    }

    public function config(): Config
    {
        return $this->made['config'] ??= new Config($this->store());
    }

    public function http(): ClientInterface
    {
        return $this->made['http'] ??= new JoomlaClient($this->config());
    }

    public function license(): LicenseClient
    {
        return $this->made['license'] ??= new LicenseClient(
            $this->config(),
            $this->store(),
            $this->http(),
            $this->siteUrl()
        );
    }

    public function gate(): LicenseGate
    {
        return $this->made['gate'] ??= new LicenseGate($this->license(), $this->store());
    }

    public function sources(): SourceResolver
    {
        return $this->made['sources'] ??= new SourceResolver(
            $this->config(),
            $this->store(),
            $this->http(),
            $this->siteUrl()
        );
    }

    public function downloader(): Downloader
    {
        return $this->made['downloader'] ??= new Downloader($this->tmpPath());
    }

    public function installer(): PackageInstaller
    {
        return $this->made['installer'] ??= new PackageInstaller();
    }

    public function updateSite(): UpdateSite
    {
        return $this->made['updateSite'] ??= new UpdateSite(
            $this->config(),
            $this->store(),
            $this->siteUrl()
        );
    }
}
