<?php

namespace IQuix\Setup\Package;

defined('_JEXEC') or die('Unauthorized Access');

use IQuix\Setup\Log;
use Joomla\CMS\Factory;
use Joomla\CMS\Installer\Installer;
use Joomla\CMS\Installer\InstallerHelper;
use Joomla\Filesystem\Folder;

/**
 * Extracts a Quix package and installs each bundled extension one at a time.
 *
 * Installing piecemeal is the whole point of iQuix: a site whose PHP upload
 * limit cannot accept the full package can still receive it this way.
 */
final class PackageInstaller
{
    /**
     * @return string the directory the package was extracted into
     */
    public function unpack(string $archivePath): string
    {
        // InstallerHelper::unpack extracts into Joomla's own tmp path and
        // returns where it landed; we do not choose the directory ourselves.
        $result = InstallerHelper::unpack($archivePath, true);

        if ($result === false || empty($result['dir'])) {
            throw new \RuntimeException('The package archive could not be extracted.');
        }

        Log::debug('Package extracted to ' . $result['dir']);

        return $result['dir'];
    }

    /**
     * @return list<string>
     */
    public function extensions(string $extractDir): array
    {
        $manifest = $this->manifestPath($extractDir);

        return ExtensionList::fromManifest((string) file_get_contents($manifest));
    }

    public function packageVersion(string $extractDir): string
    {
        $xml = simplexml_load_string((string) file_get_contents($this->manifestPath($extractDir)));

        return $xml === false ? '0.0.0' : trim((string) $xml->version);
    }

    /**
     * Install one bundled extension.
     *
     * @throws \RuntimeException carrying whatever Joomla put on the message queue
     */
    public function install(string $extractDir, string $filename): void
    {
        $archive = $extractDir . '/' . $filename;

        if (!is_file($archive)) {
            throw new \RuntimeException($filename . ' is missing from the package.');
        }

        $unpacked = InstallerHelper::unpack($archive, true);

        if ($unpacked === false || empty($unpacked['dir'])) {
            throw new \RuntimeException($filename . ' could not be extracted.');
        }

        // Mirrors Joomla's own PackageAdapter: a fresh Installer per extension
        // so state does not leak between them.
        $installer = new Installer();

        if (method_exists($installer, 'setDatabase')) {
            $installer->setDatabase(Factory::getDbo());
        }

        $installed = $installer->install($unpacked['dir']);

        InstallerHelper::cleanupInstall($archive, $unpacked['extractdir'] ?? $unpacked['dir']);

        if (!$installed) {
            throw new \RuntimeException($filename . ' failed to install. ' . $this->lastMessage());
        }

        Log::debug('Installed ' . $filename);
    }

    public function cleanup(string $archivePath, string $extractDir): void
    {
        InstallerHelper::cleanupInstall($archivePath, $extractDir);

        if (is_dir($extractDir)) {
            Folder::delete($extractDir);
        }
    }

    private function manifestPath(string $extractDir): string
    {
        $manifest = $extractDir . '/pkg_quix.xml';

        if (!is_file($manifest)) {
            throw new \RuntimeException('pkg_quix.xml was not found in the package.');
        }

        return $manifest;
    }

    private function lastMessage(): string
    {
        $queue = Factory::getApplication()->getMessageQueue();

        if ($queue === []) {
            return '';
        }

        $last = end($queue);

        return is_array($last) ? (string) ($last['message'] ?? '') : '';
    }
}
