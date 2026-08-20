<?php

namespace IQuix\Setup\Controller;

defined('_JEXEC') or die('Unauthorized Access');

use IQuix\Setup\Log;
use Joomla\CMS\Factory;
use Joomla\Filesystem\File;

final class Maintenance extends AbstractController
{
    public function cleanInstallation(): never
    {
        $marker = JPATH_ROOT . '/tmp/quix.installation';

        if (File::exists($marker)) {
            File::delete($marker);
        }

        $this->ok('Installation files cleaned.');
    }

    public function removeUpdateRecord(): never
    {
        $this->container->updateSite()->purgeUpdates();

        $this->ok('Update records refreshed.');
    }

    public function updateAssets(): never
    {
        // Best effort: a failure here does not invalidate the installation.
        try {
            $token = Factory::getApplication()->getFormToken();
            $url   = 'index.php?option=com_quix&task=updateAjax&' . $token . '=1';

            Log::debug('Refreshing Quix assets via ' . $url);
        } catch (\Throwable $e) {
            Log::debug('Asset refresh skipped: ' . $e->getMessage());
        }

        $this->ok('Assets refreshed.');
    }
}
