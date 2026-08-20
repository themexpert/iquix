<?php

namespace IQuix\Setup\Controller;

defined('_JEXEC') or die('Unauthorized Access');

use Joomla\Filesystem\File;

final class Maintenance extends AbstractController
{
    public function cleanInstallation(): never
    {
        $marker = JPATH_ROOT . '/tmp/quix.installation';

        if (is_file($marker)) {
            File::delete($marker);
        }

        $this->ok('Installation files cleaned.');
    }

    public function removeUpdateRecord(): never
    {
        $this->container->updateSite()->purgeUpdates();

        $this->ok('Update records refreshed.');
    }

    /**
     * There is deliberately no HTTP call here. The old code hit
     * index.php?option=com_quix&task=updateAjax, an administrator endpoint
     * that requires a logged-in admin session; a server-to-server request
     * from PHP carries no session cookie, so Joomla would only ever answer
     * with the login page — the request never rebuilt anything. It just
     * reported success unconditionally. Do not add the request back.
     *
     * Quix compiles its CSS/JS on the next front-end render once its cache
     * is empty, and Installation::cleanCache() already cleared that cache
     * earlier in this run, so nothing further needs to happen here.
     */
    public function updateAssets(): never
    {
        $this->ok('Quix will rebuild its assets on the next page load.');
    }
}
