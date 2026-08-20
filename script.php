<?php
/**
* @package		quix
* @copyright	Copyright (C) 2010 - 2017 ThemeXpert.com. All rights reserved.
* @license		GNU/GPL, see LICENSE.php
* quix is free software. This version may have been modified pursuant
* to the GNU General Public License, and as distributed it includes or
* is derivative of works licensed under the GNU General Public License or
* other free or open source software licenses.
* See COPYRIGHT.php for copyright notices and details.
*/
defined('_JEXEC') or die('Unauthorized Access');

use Joomla\CMS\Factory;
use Joomla\CMS\Installer\InstallerScriptInterface;
use Joomla\Filesystem\File;

class Com_IquixInstallerScript
{
    public function preflight($type, $parent)
    {
        $file = JPATH_ROOT . '/tmp/quix.installation';

        if (!File::exists($file)) {
            File::write($file, json_encode(['new' => false, 'step' => 1, 'status' => 'installing']));
        }

        return true;
    }

    public function postflight($type, $parent)
    {
        $this->createConfigTable();

        Factory::getApplication()->enqueueMessage(
            'Quix Installer is ready. Open it from Components → Quix Installer to install Quix.',
            'message'
        );

        return true;
    }

    public function install($parent)
    {
        return true;
    }

    public function update($parent)
    {
        return true;
    }

    public function uninstall($parent)
    {
        return true;
    }

    private function createConfigTable(): bool
    {
        $db = Factory::getDbo();

        // InnoDB, not MyISAM: com_quix reads and writes this table too.
        $db->setQuery(
            'CREATE TABLE IF NOT EXISTS ' . $db->quoteName('#__quix_configs') . ' ('
            . $db->quoteName('name') . ' VARCHAR(255) NOT NULL,'
            . $db->quoteName('params') . ' TEXT NOT NULL,'
            . 'PRIMARY KEY (' . $db->quoteName('name') . ')'
            . ') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
        );

        return (bool) $db->execute();
    }
}
