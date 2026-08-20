<?php

namespace IQuix\Setup\Controller;

defined('_JEXEC') or die('Unauthorized Access');

use IQuix\Setup\Log;
use Joomla\CMS\Factory;
use Joomla\Filesystem\File;
use Joomla\Filesystem\Folder;

final class Installation extends AbstractController
{
    public function checkPackageExtension(): never
    {
        $db    = Factory::getDbo();
        $query = $db->getQuery(true)
            ->select($db->quoteName('element'))
            ->from($db->quoteName('#__extensions'))
            ->whereIn($db->quoteName('element'), ['pkg_quix', 'com_quix'], \Joomla\Database\ParameterType::STRING);
        $db->setQuery($query);

        $found = (array) $db->loadColumn();

        if (!in_array('pkg_quix', $found, true)) {
            $this->ok('Fresh installation, continuing.');
        }

        if (!in_array('com_quix', $found, true)) {
            $this->fail('The existing Quix installation looks damaged. Continue to reinstall it.');
        }

        $this->ok('Quix is already installed. Continuing will update it.');
    }

    public function download(): never
    {
        $store   = $this->container->store();
        $edition = $store->get('edition', 'free');

        try {
            $source  = $this->container->sources()->resolve($edition);
            $archive = $this->container->downloader()->fetch($source);
        } catch (\Throwable $e) {
            $this->fail($e->getMessage());
        }

        // Record the archive the moment it exists. installPost() is what
        // deletes it and it can only delete what the store knows about, so
        // storing this only after unpack() succeeded left the (possibly
        // paid) package sitting in tmp/ forever whenever unpack() threw.
        $store->set('install_archive', $archive);

        try {
            $extract = $this->container->installer()->unpack($archive);
        } catch (\Throwable $e) {
            $this->container->downloader()->cleanup($archive);
            $store->set('install_archive', '');

            $this->fail($e->getMessage());
        }

        $store->set('install_dir', $extract);

        $this->ok(
            sprintf('Quix %s (%s) downloaded.', $source->version, $edition),
            ['path' => $extract]
        );
    }

    public function cleanCache(): never
    {
        foreach (['/media/quix/css', '/media/quix/js', '/media/quixnxt/css', '/media/quixnxt/js'] as $relative) {
            $path = JPATH_ROOT . $relative;

            if (!is_dir($path)) {
                continue;
            }

            foreach ((array) Folder::files($path) as $file) {
                if ($file !== 'index.html') {
                    File::delete($path . '/' . $file);
                }
            }
        }

        foreach (['com_quix', 'mod_quix'] as $group) {
            try {
                Factory::getCache($group, '')->clean();
            } catch (\Throwable $e) {
                Log::debug('Could not clear cache group ' . $group . ': ' . $e->getMessage());
            }
        }

        $this->ok('Cache cleared.');
    }

    /**
     * Installs every extension the package manifest lists, in manifest order.
     * Replaces the five hardcoded per-type tasks the old JS called one by one.
     */
    public function installExtensions(): never
    {
        $dir = $this->container->store()->get('install_dir');

        if ($dir === '' || !is_dir($dir)) {
            $this->fail('The downloaded package is missing. Please restart the installation.');
        }

        $installer = $this->container->installer();

        try {
            $extensions = $installer->extensions($dir);
        } catch (\RuntimeException $e) {
            $this->fail($e->getMessage());
        }

        if ($extensions === []) {
            $this->fail('The package manifest listed no extensions to install.');
        }

        $installed = [];
        $skipped   = [];

        foreach ($extensions as $filename) {
            try {
                // false means the archive is already gone, i.e. an earlier,
                // interrupted attempt installed this one. Resuming rather
                // than repeating is the point: on the low-limit hosting this
                // tool exists for, a run that times out half way through has
                // to be able to carry on from where it stopped.
                if ($installer->install($dir, $filename)) {
                    $installed[] = $filename;
                } else {
                    $skipped[] = $filename;
                }
            } catch (\RuntimeException $e) {
                $this->fail($e->getMessage(), ['installed' => $installed, 'skipped' => $skipped]);
            }
        }

        // The members are in; register the package that owns them. Without a
        // pkg_quix row and manifest, Joomla has nothing to hang the update
        // site off and Quix updates never reach the Updates page.
        try {
            $packageId = $installer->registerPackage($dir);
        } catch (\Throwable $e) {
            $this->fail('The extensions installed, but the Quix package could not be registered: ' . $e->getMessage());
        }

        if ($packageId === 0) {
            $this->fail('The extensions installed, but the Quix package could not be registered, so updates would not be offered.');
        }

        $this->container->store()->set('installed_version', $installer->packageVersion($dir));

        $message = sprintf('%d extensions installed.', count($installed));

        if ($skipped !== []) {
            $message .= sprintf(
                ' %d %s already in place from an earlier attempt.',
                count($skipped),
                count($skipped) === 1 ? 'was' : 'were'
            );
        }

        $this->ok($message, ['installed' => $installed, 'skipped' => $skipped]);
    }

    public function syncDb(): never
    {
        $dir    = $this->container->store()->get('install_dir');
        $script = $dir . '/pkg.script.php';

        if ($dir === '' || !is_file($script)) {
            $this->ok('No database migration was bundled with this package.');
        }

        try {
            require_once $script;

            if (!class_exists('pkg_QuixInstallerScript')) {
                $this->ok('No database migration was bundled with this package.');
            }

            $instance = new \pkg_QuixInstallerScript();

            ob_start();
            $instance->postflight([]);
            ob_end_clean();
        } catch (\Throwable $e) {
            $this->fail('The database update failed: ' . $e->getMessage());
        }

        $this->ok('Database updated.');
    }

    public function installPost(): never
    {
        $store   = $this->container->store();
        $archive = $store->get('install_archive');
        $dir     = $store->get('install_dir');

        $updateSiteError = '';

        try {
            $this->container->updateSite()->apply();
            $this->container->updateSite()->purgeUpdates();
        } catch (\Throwable $e) {
            // Still not fatal — Quix is installed and the archive below must
            // go regardless — but do not swallow it either. A silent catch
            // here is how a broken update site went unnoticed.
            $updateSiteError = $e->getMessage();

            Log::debug('Could not configure the update site: ' . $e->getMessage());
        }

        // Always remove the package, even if something above failed — leaving
        // the paid archive on disk is how the previous version leaked it.
        if ($archive !== '' || $dir !== '') {
            $this->container->installer()->cleanup($archive, $dir);
        }

        // PackageInstaller::cleanup() only removes the archive file and the
        // extracted install_xxxxx/ directory. The wrapper directory
        // (tmp/iquix-{random}/) belongs to Downloader, which created it in
        // fetch() — so Downloader is the one that removes it, whether or not
        // the archive file is still there to unlink.
        if ($archive !== '') {
            $this->container->downloader()->cleanup($archive);
        }

        $store->setMany(['install_archive' => '', 'install_dir' => '']);

        if ($updateSiteError !== '') {
            $this->ok(
                'Installation finished, but the Quix update site could not be configured, '
                . 'so updates may not be offered: ' . $updateSiteError
            );
        }

        $this->ok('Installation finished.');
    }
}
