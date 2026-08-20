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
    /**
     * setup/lib is written against PHP 8.1 syntax (readonly properties, the
     * never return type, match). Nothing in this script file uses it, so an
     * older site can still load the script far enough to be told why the
     * install is refused instead of parse-erroring later inside the wizard.
     */
    private const MINIMUM_PHP = '8.1.0';

    public function preflight($type, $parent)
    {
        if (version_compare(PHP_VERSION, self::MINIMUM_PHP, '<')) {
            Factory::getApplication()->enqueueMessage(
                sprintf(
                    'Quix Installer needs PHP %s or newer. This site runs PHP %s.',
                    self::MINIMUM_PHP,
                    PHP_VERSION
                ),
                'error'
            );

            return false;
        }

        $file = JPATH_ROOT . '/tmp/quix.installation';

        if (!is_file($file)) {
            // Joomla 4's File::write() takes $buffer by reference, so the
            // payload has to be a variable.
            $marker = json_encode(['new' => false, 'step' => 1, 'status' => 'installing']);

            File::write($file, $marker);
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
