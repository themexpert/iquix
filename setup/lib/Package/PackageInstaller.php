<?php

namespace IQuix\Setup\Package;

defined('_JEXEC') or die('Unauthorized Access');

use IQuix\Setup\Log;
use Joomla\CMS\Factory;
use Joomla\CMS\Installer\Installer;
use Joomla\CMS\Installer\InstallerHelper;
use Joomla\Filesystem\File;
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
     * A member archive is deleted only once its extension has installed, so
     * its absence is the progress marker for the run. That is what makes a
     * retry resumable: this tool exists for hosts with small limits, where a
     * run hitting max_execution_time part-way through is entirely normal, and
     * the previous behaviour (delete the archive whether or not the install
     * worked) turned any such interruption into a dead end — the next attempt
     * failed on the first entry with "com_quix.zip is missing".
     *
     * @return bool true when this call installed the extension, false when an
     *              earlier attempt already did and there was nothing to do
     *
     * @throws \RuntimeException carrying whatever Joomla put on the message queue
     */
    public function install(string $extractDir, string $filename): bool
    {
        $archive = $extractDir . '/' . $filename;

        if (!is_file($archive)) {
            Log::debug('Skipping ' . $filename . ': an earlier attempt already installed it');

            return false;
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

        // The working copy goes either way; the archive only on success.
        $unpackedDir = (string) ($unpacked['extractdir'] ?? $unpacked['dir']);

        if ($unpackedDir !== '' && is_dir($unpackedDir)) {
            Folder::delete($unpackedDir);
        }

        if (!$installed) {
            throw new \RuntimeException($filename . ' failed to install. ' . $this->lastMessage());
        }

        File::delete($archive);

        Log::debug('Installed ' . $filename);

        return true;
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
