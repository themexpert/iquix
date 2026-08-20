<?php

namespace IQuix\Setup\Package;

defined('_JEXEC') or die('Unauthorized Access');

use IQuix\Setup\Log;
use Joomla\CMS\Factory;
use Joomla\CMS\Installer\Installer;
use Joomla\CMS\Installer\InstallerHelper;
use Joomla\CMS\Table\Extension;
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
     * Extension ids reported by onExtensionAfterInstall during this request.
     * Joomla's own PackageAdapter collects them the same way, to stamp
     * package_id on the members it just installed.
     *
     * @var list<int>
     */
    private array $installedIds = [];

    private bool $listening = false;

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

        $this->collectExtensionIds();

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

    /**
     * Register the package itself, now that its members are in.
     *
     * Installing only the members leaves no `pkg_quix` row in `#__extensions`
     * and no `administrator/manifests/packages/pkg_quix.xml`, and the
     * consequences all land on the customer: UpdateSite::apply() cannot link
     * its update site to anything, purgeUpdates() has no extension to purge
     * for, and Joomla's Updater writes the `#__updates` row with
     * extension_id = 0 — which UpdateModel::getListQuery() filters out with
     * `u.extension_id != 0`. The Quix update simply never appears on the
     * Updates page, and com_quix's own manifest declares no update server to
     * fall back on.
     *
     * This does by hand what PackageAdapter::storeExtension() and
     * ::finaliseInstall() do: the extensions row, the manifest file, the
     * manifest script, and package_id on the members.
     *
     * @return int the package's extension id, 0 if it could not be stored
     */
    public function registerPackage(string $extractDir): int
    {
        $manifestPath = $this->manifestPath($extractDir);
        $xml          = simplexml_load_string((string) file_get_contents($manifestPath));

        if ($xml === false) {
            throw new \RuntimeException('The package manifest could not be parsed.');
        }

        // PackageAdapter::getElement() derives the element from the manifest
        // filename, not from anything inside the file.
        $element     = basename($manifestPath, '.xml');
        $packageName = trim((string) $xml->packagename);

        $db    = Factory::getDbo();
        $table = new Extension($db);

        if (!$table->load(['type' => 'package', 'element' => $element])) {
            $table->type      = 'package';
            $table->element   = $element;
            $table->folder    = '';
            $table->client_id = 0;
            $table->enabled   = 1;
            $table->protected = 0;
            $table->access    = 1;
            $table->params    = '{}';

            // #__extensions.custom_data is NOT NULL with no default, so a row
            // built by hand has to supply it or MySQL refuses the insert in
            // strict mode. Joomla's adapters set it for the same reason.
            $table->custom_data = '';
        }

        $table->name           = trim((string) $xml->name) ?: $element;
        $table->changelogurl   = trim((string) $xml->changelogurl);
        $table->manifest_cache = (string) json_encode(Installer::parseXMLInstallFile($manifestPath));

        if (!$table->store()) {
            Log::debug('Could not store the pkg_quix extension row: ' . $table->getError());

            return 0;
        }

        $packageId = (int) $table->extension_id;

        $this->copyPackageManifest($manifestPath, $element, $packageName, (string) $xml->scriptfile, $extractDir);
        $this->stampPackageId($packageId);

        Log::debug('Registered ' . $element . ' as extension ' . $packageId);

        return $packageId;
    }

    /**
     * Joomla reads administrator/manifests/packages/{element}.xml on uninstall
     * and to refresh the manifest cache; without it the package row is inert.
     */
    private function copyPackageManifest(
        string $manifestPath,
        string $element,
        string $packageName,
        string $scriptFile,
        string $extractDir
    ): void {
        $target = JPATH_MANIFESTS . '/packages';

        if (!is_dir($target) && !Folder::create($target)) {
            Log::debug('Could not create ' . $target);

            return;
        }

        if (!File::copy($manifestPath, $target . '/' . $element . '.xml')) {
            Log::debug('Could not copy the package manifest to ' . $target);
        }

        $scriptFile = trim($scriptFile);

        if ($scriptFile === '' || $packageName === '' || !is_file($extractDir . '/' . $scriptFile)) {
            return;
        }

        // The script lives beside the manifest, under <packagename>, exactly
        // where PackageAdapter::setupInstallPaths() puts the extension root.
        $root = $target . '/' . $packageName;

        if (!is_dir($root) && !Folder::create($root)) {
            Log::debug('Could not create ' . $root);

            return;
        }

        File::copy($extractDir . '/' . $scriptFile, $root . '/' . basename($scriptFile));
    }

    /**
     * Tie the members installed in this request to the package, so Joomla's
     * Manage view groups them and uninstalling the package takes them with it.
     *
     * Only the ids from this request are known. A run resumed after a timeout
     * therefore stamps only what it installed itself; that costs grouping for
     * the earlier members, nothing functional.
     */
    private function stampPackageId(int $packageId): void
    {
        $ids = array_values(array_unique(array_filter($this->installedIds)));

        if ($packageId === 0 || $ids === []) {
            return;
        }

        $db    = Factory::getDbo();
        $query = $db->getQuery(true)
            ->update($db->quoteName('#__extensions'))
            ->set($db->quoteName('package_id') . ' = ' . $packageId)
            ->whereIn($db->quoteName('extension_id'), $ids)
            // pkg_quix bundles pkg_jmedia, whose own adapter has already
            // claimed its members. Leave those alone: taking them would
            // orphan them from the package that actually owns them, so
            // uninstalling pkg_jmedia would then remove nothing.
            ->where(
                '(' . $db->quoteName('package_id') . ' = 0 OR '
                . $db->quoteName('package_id') . ' = ' . $packageId . ')'
            );

        try {
            $db->setQuery($query)->execute();
        } catch (\Throwable $e) {
            Log::debug('Could not set package_id on the installed extensions: ' . $e->getMessage());
        }
    }

    /**
     * Installer::install() returns a bool, so the only way to learn the id of
     * what it just installed is the event it fires. Registered once per
     * instance, immediately before the first install.
     */
    private function collectExtensionIds(): void
    {
        if ($this->listening) {
            return;
        }

        $this->listening = true;

        try {
            $dispatcher = Factory::getApplication()->getDispatcher();
        } catch (\Throwable $e) {
            Log::debug('No dispatcher available; package_id will not be set: ' . $e->getMessage());

            return;
        }

        $dispatcher->addListener('onExtensionAfterInstall', function ($event): void {
            $eid = $event->getArgument('eid', false);

            if ($eid) {
                $this->installedIds[] = (int) $eid;
            }
        });
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
