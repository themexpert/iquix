<?php
/**
 * @package        Quix
 * @copyright      Copyright (C) 2010 - 2017 ThemeXpert.com. All rights reserved.
 * @license        GNU/GPL, see LICENSE.php
 * Quix is free software. This version may have been modified pursuant
 * to the GNU General Public License, and as distributed it includes or
 * is derivative of works licensed under the GNU General Public License or
 * other free or open source software licenses.
 * See COPYRIGHT.php for copyright notices and details.
 */
defined('_JEXEC') or die('Unauthorized Access');
use Joomla\Archive\Archive;
require_once(dirname(__FILE__).'/controller.php');

class iQuixControllerInstallation extends iQuixSetupController
{

    public function cleanCache()
    {
        jimport('joomla.filesystem.file');
        jimport('joomla.filesystem.folder');
        jimport('joomla.filesystem.path');

        $cssfiles = (array) JFolder::files(JPATH_ROOT.'/media/quix/css');
        array_map(
            function ($file) {
                if ($file == 'index.html') {
                    return;
                }
                JFile::delete(JPATH_ROOT.'/media/quix/css/'.$file);
            },
            $cssfiles
        );

        $jsfiles = (array) JFolder::files(JPATH_ROOT.'/media/quix/js');
        array_map(
            function ($file) {
                if ($file == 'index.html') {
                    return;
                }
                JFile::delete(JPATH_ROOT.'/media/quix/js/'.$file);
            },
            $jsfiles
        );

        // Clear relavent cache
        $this->cachecleaner('com_quix');
        $this->cachecleaner('mod_quix');
        $this->cachecleaner('libquix', 1);
        $this->cachecleaner('lib_quix', 1);
        $this->cachecleaner('lib_quix');
        $this->cachecleaner('quix', 1);
        $this->cachecleaner('quix');

        if (JDEBUG) {
            \JLog::add("iQuix - Cleared cache", JLog::INFO, 'iquix');
        }

        return $this->output($this->getResultObj(JText::_('COM_IQUIX_INSTALLATION_CACHECLEAN_SUCCESS'), true));

    }

    public function cachecleaner($group = 'com_quix', $client_id = 0)
    {
        $conf = \JFactory::getConfig();

        $options = array(
            'defaultgroup' => $group,
            'cachebase'    => $client_id ? JPATH_ADMINISTRATOR.'/cache' : $conf->get('cache_path', JPATH_SITE.'/cache'),
            'result'       => true,
        );

        try {
            /** @var \JCacheControllerCallback $cache */
            $cache = \JCache::getInstance('callback', $options);
            $cache->clean();
        } catch (\JCacheException $exception) {
            $options['result'] = false;
        }

        // Trigger the onContentCleanCache event.
        // \JEventDispatcher::getInstance()->trigger('onContentCleanCache', $options);
        JFactory::getApplication()->triggerEvent('onContentCleanCache', $options);
    }

    public function checkPackageExtension()
    {
        $db = JFactory::getDBO();
        // Update installed version
        $query = "SELECT * FROM `#__extensions` WHERE `name` = 'pkg_quix' and `type` = 'package'";
        $db->setQuery($query);
        $result = $db->loadObject();

        if ($result->extension_id) {
            if (JDEBUG) {
                \JLog::add("iQuix - Only Updates, No need to update PKG information", JLog::INFO, 'iquix');
            }

            return true;
        } else {
            $newVersion = $this->getInstallableVersion();

            $manifest = '{"name":"pkg_quix","type":"package","creationDate":"2017-07-16","author":"ThemeXpert","copyright":"(C) 2010 - 2016 ThemeXpert. All rights reserved.","authorEmail":"info@themexpert.com","authorUrl":"www.themexpert.com","version":"'.$newVersion.'","description":"PKG_QUIX_XML_DESCRIPTION","group":"","filename":"pkg_quix"}';

            // new installation, add pkg_quix
            $obj                 = new stdClass();
            $obj->extension_id   = '';
            $obj->package_id     = 0;
            $obj->name           = 'pkg_quix';
            $obj->type           = 'package';
            $obj->element        = 'pkg_quix';
            $obj->enabled        = 1;
            $obj->access         = 1;
            $obj->manifest_cache = $manifest;
            $obj->params         = '{}';

            // Insert the object into the user profile table.
            $result = JFactory::getDbo()->insertObject('#__extensions', $obj);

            if (JDEBUG) {
                \JLog::add("iQuix - updated pkg_quix information with status:".$result, JLog::DEBUG, 'iquix');
            }

            if ($result) {
                return true;
            } else {
                return false;
            }
        }

    }

    public function backupDatabase()
    {
        $getPackage = $this->checkPackageExtension();
        if ( ! $getPackage) {

            if (JDEBUG) {
                \JLog::add("iQuix - backupDatabase failed! ".JText::_('Parent package failed to detect!'), JLog::ERROR, 'iquix');
            }

            return $this->output($this->getResultObj(JText::_('Parent package failed to detect!'), false));
        }

        $getComponent = \JComponentHelper::getComponent('com_quix');
        if (empty($getComponent->id) or ! $getComponent->id) {

            if (JDEBUG) {
                \JLog::add("iQuix - New Installation, No need backup", JLog::DEBUG, 'iquix');
            }

            return $this->output($this->getResultObj(JText::_('New installation'), true));
        }

        $version     = $this->getPreviousVersion();
        $versionText = str_replace(".", "", $version);

        $app    = JFactory::getApplication();
        $prefix = $app->get('dbprefix');

        $db     = JFactory::getDbo();
        $tables = JFactory::getDbo()->getTableList();

        if ( ! in_array($prefix.'quix', $tables)) {

            if (JDEBUG) {
                \JLog::add("iQuix - Database empty, No need backup", JLog::DEBUG, 'iquix');
            }

            return $this->output($this->getResultObj(JText::_('No record to backup!'), true));
        }

        try {
            $tables    = JFactory::getDbo()->getTableList();
            $quixTable = $prefix.'quix'.$versionText;
            if ( ! in_array($quixTable, $tables)) {

                $query = "CREATE TABLE IF NOT EXISTS `#__quix$versionText` LIKE `#__quix`;";
                $db->setQuery($query);
                $result = $db->execute();

                $query = "INSERT `#__quix$versionText` SELECT * FROM `#__quix`;";
                $db->setQuery($query);
                $db->execute();

                $query = "CREATE TABLE IF NOT EXISTS `#__quix_collections$versionText` LIKE `#__quix_collections`;";
                $db->setQuery($query);
                $db->execute();
                $query = "INSERT `#__quix_collections$versionText` SELECT * FROM `#__quix_collections`;";
                $db->setQuery($query);
                $db->execute();

                $query = "CREATE TABLE IF NOT EXISTS `#__quix_collection_map$versionText` LIKE `#__quix_collection_map`;";
                $db->setQuery($query);
                $db->execute();
                $query = "INSERT `#__quix_collection_map$versionText` SELECT * FROM `#__quix_collection_map`;";
                $db->setQuery($query);
                $db->execute();

                $query = "CREATE TABLE IF NOT EXISTS `#__quix_elements$versionText` LIKE `#__quix_elements`;";
                $db->setQuery($query);
                $db->execute();
                $query = "INSERT `#__quix_elements$versionText` SELECT * FROM `#__quix_elements`;";
                $db->setQuery($query);
                $db->execute();

                if (JDEBUG) {
                    \JLog::add("iQuix - Database backup complete, name: ".$prefix.'quix'.$versionText, JLog::DEBUG, 'iquix');
                }

                return $this->output($this->getResultObj(JText::_('Database backup complete'), true));
            } else {
                if (JDEBUG) {
                    \JLog::add("iQuix - No need backup, already has it. name: ".$prefix.'quix'.$versionText, JLog::DEBUG, 'iquix');
                }

                return $this->output($this->getResultObj(JText::sprintf('Version %s already has a backup', $version), true));
            }

        } catch (Exception $e) {
            if (JDEBUG) {
                \JLog::add("iQuix - Database update failed, due to: ".$e->getMessage(), JLog::DEBUG, 'iquix');
            }

            return $this->output($this->getResultObj(JText::_('Error: '.$e->getMessage()), false));
        }
    }

    /**
     * Downloads the file from the server
     *
     * @since     2.0.9
     * @access    public
     */
    public function download()
    {
        $getLatestRelease = $this->getReleaseInfo();

        if ( ! $getLatestRelease) {
            $info = $this->getInfo();

            if ( ! $info->success) {
                $result          = new stdClass();
                $result->state   = false;
                $result->message = $info->message;

                $this->output($result);
                exit;
            }
            if (JDEBUG) {
                \JLog::add("iQuix - server FIle Info : ".json_encode($info), JLog::DEBUG, 'iquix');
            }

            // Download the component installer.
            $data = $info->data;

        } else {
            $data               = new stdClass;
            $data->download_url = $getLatestRelease;

            if (JDEBUG) {
                \JLog::add("iQuix - download url from release : ".json_encode($data), JLog::DEBUG, 'iquix');
            }
        }

        if (JDEBUG) {
            \JLog::add("iQuix - Lets download : ".json_encode($data), JLog::DEBUG, 'iquix');
        }

        $storage = $this->getDownloadFile($data);

        // This only happens when there is no result returned from the server
        if ($storage === false) {
            $result          = new stdClass();
            $result->state   = false;
            $result->message = 'There was some errors when downloading the file from the server.';

            if (JDEBUG) {
                \JLog::add("iQuix - downloading failed : ".$result->message, JLog::ERROR, 'iquix');
            }

            $this->output($result);
        }

        if (JDEBUG) {
            \JLog::add("iQuix - Downloads completed!", JLog::DEBUG, 'iquix');
        }

        // Extract files here.
        $tmp = QX_TMP.'/pkg_quix';

        if (JFolder::exists($tmp)) {
            JFolder::delete($tmp);
        }

        try {
            // Try to extract the files
            // $state = JArchive::extract($storage, $tmp);

            // The archive instance
            $archive = new Archive(array('tmp_path' => JFactory::getConfig()->get('tmp_path')));
            // Extract the archive
            $state = $archive->extract($storage, $tmp);

        } catch (Exception $e) {
            $result          = new stdClass();
            $result->state   = false;
            $result->message = 'File extracting error: '.$e->getMessage();

            $this->output($result);
        }

        // If there is an error extracting the zip file, then there is a possibility that the server returned a json string
        if ( ! $state) {

            $contents = file_get_contents($storage);
            $result   = json_decode($contents);

            if (is_object($result)) {
                $result->state = false;

                if (JDEBUG) {
                    \JLog::add("iQuix - File extraction error : ".json_encode($result), JLog::ERROR, 'iquix');
                }

                $this->output($result);
                exit;
            }

            $result          = new stdClass();
            $result->state   = false;
            $result->message = 'There was some errors when extracting the archive from the server. If the problem still persists, please contact our support team.<br /><br /><a href="https://www.themexpert.com/forums" class="btn btn-default" target="_blank">Contact Support</a>';

            if (JDEBUG) {
                \JLog::add("iQuix - File extraction error : ".$result->message, JLog::ERROR, 'iquix');
            }

            $this->output($result);
            exit;
        }


        // Get the md5 hash of the stored file
        $hash = md5_file($storage);

        // @TODO: update server license plugin to generate md5hash for the file
        // Check if the md5 check sum matches the one provided from the server.
        // if (!in_array($hash, $info->md5)) {
        // 	$result = new stdClass();
        // 	$result->state = false;
        // 	$result->message = 'The MD5 hash of the downloaded file does not match. Please contact our support team to look into this.<br /><br /><a href="https://www.themexpert.com/forums" class="btn btn-default" target="_blank">Contact Support</a>';

        // 	$this->output($result);
        // 	exit;
        // }

        // After installation is completed, cleanup all zip files from the site
        $this->cleanupZipFiles(dirname($storage));
        if (JDEBUG) {
            \JLog::add("iQuix - Installation file downloaded successfully", JLog::DEBUG, 'iquix');
        }

        $result          = new stdClass();
        $result->message = 'Installation file downloaded successfully';
        $result->state   = $state;
        $result->path    = $tmp;

        $this->output($result);
    }

    /**
     * Downloads the installation files from our installation API
     *
     * @since     2.0.9
     * @access    public
     */
    public function getDownloadFile($info)
    {
        // Set the storage page
        $storage = QX_PACKAGES.'/pkg_quix.zip';

        // Delete zip archive if it already exists.
        if (JFile::exists($storage)) {
            JFile::delete($storage);
        }

        $download_url = preg_replace("/^http:/i", "https:", $info->download_url);

        if (JDEBUG) {
            \JLog::add("iQuix - downloading ".$download_url, JLog::DEBUG, 'iquix');
            \JLog::add("iQuix - downloading to location ".$storage, JLog::DEBUG, 'iquix');
        }

        set_time_limit(0);

        //This is the file where we save the    information
        $fp = fopen($storage, 'w+');

        //Here is the file we are downloading, replace spaces with %20
        $ch = curl_init($download_url);
        curl_setopt($ch, CURLOPT_TIMEOUT, 35000);
        // write curl response to file
        curl_setopt($ch, CURLOPT_FILE, $fp);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        // get curl response
        $result = curl_exec($ch);
        curl_close($ch);

        fclose($fp);

        return $result ? $storage : false;

    }


    /**
     * For users who uploaded the installer and needs a manual extraction
     *
     * @since     2.0.9
     * @access    public
     */
    public function extract()
    {
        // Check the api key from the request
        $apiKey = JRequest::getVar('apikey', '');

        // Construct the storage path
        $storage = QX_PACKAGES.'/'.QX_PACKAGE;
        $exists  = JFile::exists($storage);

        // Test if package really exists
        if ( ! $exists) {
            $result          = new stdClass();
            $result->state   = false;
            $result->message = 'The component package does not exist on the site.<br />Please contact our support team to look into this.';

            if (JDEBUG) {
                \JLog::add("iQuix - $result->message", JLog::ERROR, 'iquix');
            }
            $this->output($result);
            exit;
        }

        // Get the folder name
        $folderName = basename($storage);
        $folderName = str_ireplace('.zip', '', $folderName);

        // Extract files here.
        $tmp = QX_TMP.'/'.$folderName;

        // Ensure that there is no such folders exists on the site
        if (JFolder::exists($tmp)) {
            JFolder::delete($tmp);
        }

        // Try to extract the files
        //$state = JArchive::extract($storage, $tmp);
        // The archive instance
        $archive = new Archive(array('tmp_path' => JFactory::getConfig()->get('tmp_path')));
        // Extract the archive
        $state = $archive->extract($storage, $tmp);

        // Regardless of the extraction state, delete the zip file otherwise anyone can download the zip file.
        @JFile::delete($storage);

        if ( ! $state) {
            $result          = new stdClass();
            $result->state   = false;
            $result->message = 'There was some errors when extracting the zip file';

            if (JDEBUG) {
                \JLog::add("iQuix - $result->message", JLog::ERROR, 'iquix');
            }

            $this->output($result);
            exit;
        }

        $result = new stdClass();

        $result->message = 'Installation archive extracted successfully';
        $result->state   = $state;
        $result->path    = $tmp;

        if (JDEBUG) {
            \JLog::add("iQuix - $result->message", JLog::DEBUG, 'iquix');
        }

        $this->output($result);
    }


    public function getInstallableVersion()
    {
        try {
            $path = QX_TMP.'/pkg_quix/'.'pkg_quix.xml';
            // echo $path;die;
            if (JFile::exists($path)) {
                $content = file_get_contents($path);
                $xml     = simplexml_load_string($content);

                return (string) $xml->version;
            } else {
                return '2.0.0';
            }
        } catch (Exception $e) {
            return '2.0.0';
        }
    }


    public function installComponent()
    {
        // Try to extract the files
        $storage = QX_TMP.'/pkg_quix/com_quix.zip';
        $tmp     = QX_TMP.'/pkg_quix/com_quix';
        // $state   = JArchive::extract($storage, $tmp);

        // The archive instance
        $archive = new Archive(array('tmp_path' => JFactory::getConfig()->get('tmp_path')));
        // Extract the archive
        $state = $archive->extract($storage, $tmp);

        if (JDEBUG) {
            \JLog::add("iQuix - Installing component", JLog::DEBUG, 'iquix');
        }

        if ( ! $state) {

            if (JDEBUG) {
                $fileExist = JFile::exists($storage);
                \JLog::add("iQuix - Installing component failed! fileurl: $storage, isFileExist: $fileExist", JLog::DEBUG, 'iquix');
            }

            return $this->output($this->getResultObj(JText::_('COM_QUIX_INSTALLATION_ERROR_EXTRACT_COMPONENT'), false));
        }

        try {
            $app = JFactory::getApplication();
            $app->input->set('installtype', 'folder');
            $app->input->set('install_directory', $tmp);
            JModelLegacy::addIncludePath(JPATH_ADMINISTRATOR.'/components/com_installer/models');
            $installerModel = JModelLegacy::getInstance('Install', 'InstallerModel');
            $result         = $installerModel->install();

            if ($result) {
                if (JDEBUG) {
                    \JLog::add("iQuix - Component Installed", JLog::DEBUG, 'iquix');
                }

                return $this->output($this->getResultObj(JText::_('Component installation success!'), true));
            } else {
                if (JDEBUG) {
                    \JLog::add("iQuix - Component Installation failed. Error: ".end($app->getMessageQueue()), JLog::DEBUG, 'iquix');
                }

                return $this->output($this->getResultObj(JText::_('Installation failed! Error: '.end($app->getMessageQueue())), false));
            }

            if (JDEBUG) {
                $fileExist = JFile::exists($storage);
                \JLog::add("iQuix - Installing component failed! fileurl: $storage, isFileExist: $fileExist", JLog::DEBUG, 'iquix');
            }

        } catch (Exception $e) {
            if (JDEBUG) {
                \JLog::add("iQuix - Component Installation failed. Error: ".end($app->getMessageQueue()), JLog::DEBUG, 'iquix');
            }

            return $this->output($this->getResultObj(JText::_('Error: '.$e->getMessage()), false));
        }
    }

    public function installLibrary()
    {
        // Try to extract the files
        $storage = QX_TMP.'/pkg_quix/lib_quix.zip';
        if ( ! JFile::exists($storage)) {
            $storage = QX_TMP.'/pkg_quix/lib_quixnxt.zip';

            if ( ! JFile::exists($storage)) {

                if (JDEBUG) {
                    \JLog::add("iQuix - lib_quix does not exist: ".$storage, JLog::DEBUG, 'iquix');
                }

                return $this->output(JText::_('Installation failed! lib_quix does not exist'));
            }

        }

        $tmp   = QX_TMP.'/pkg_quix/lib_quix';
        //$state = JArchive::extract($storage, $tmp);
        // The archive instance
        $archive = new Archive(array('tmp_path' => JFactory::getConfig()->get('tmp_path')));
        // Extract the archive
        $state = $archive->extract($storage, $tmp);

        if ( ! $state) {
            if ( ! JFile::exists($storage)) {
                if (JDEBUG) {
                    \JLog::add("iQuix - FIle does not exist: ".$storage, JLog::DEBUG, 'iquix');
                }
            }

            return $this->output($this->getResultObj(JText::_('COM_QUIX_INSTALLATION_ERROR_EXTRACT_LIBRARY'), false));
        }

        try {
            $app = JFactory::getApplication();
            $app->input->set('installtype', 'folder');
            $app->input->set('install_directory', $tmp);
            JModelLegacy::addIncludePath(JPATH_ADMINISTRATOR.'/components/com_installer/models');
            $installerModel = JModelLegacy::getInstance('Install', 'InstallerModel');
            $result         = $installerModel->install();

            if (JDEBUG) {
                \JLog::add("iQuix - Library installation status: ".$result, JLog::DEBUG, 'iquix');
            }

            if ($result) {
                return $this->output($this->getResultObj(JText::_('Library installation success!'), true));
            } else {
                return $this->output($this->getResultObj(JText::_('Installation failed! Error: '.end($app->getMessageQueue())), false));
            }

        } catch (Exception $e) {
            if (JDEBUG) {
                \JLog::add("iQuix - Library installation error: ".$e->getMessage(), JLog::DEBUG, 'iquix');
            }

            return $this->output($this->getResultObj(JText::_('Error: '.$e->getMessage()), false));
        }
    }

    public function installModules()
    {
        $app     = JFactory::getApplication();
        $modules = ['mod_quix_menu', 'mod_quix', 'mod_quix_info'];
        foreach ($modules as $key => $module) {
            // Try to extract the files
            $storage = QX_TMP.'/pkg_quix/'.$module.'.zip';
            $tmp     = QX_TMP.'/pkg_quix/'.$module;
            //$state   = JArchive::extract($storage, $tmp);
            // The archive instance
            $archive = new Archive(array('tmp_path' => JFactory::getConfig()->get('tmp_path')));
            // Extract the archive
            $state = $archive->extract($storage, $tmp);

            if ( ! $state) {
                return $this->output($this->getResultObj(JText::_('COM_QUIX_INSTALLATION_ERROR_EXTRACT_'.strtoupper($module)), false));
            }

            try {

                $app->input->set('installtype', 'folder');
                $app->input->set('install_directory', $tmp);
                JModelLegacy::addIncludePath(JPATH_ADMINISTRATOR.'/components/com_installer/models');
                $installerModel = JModelLegacy::getInstance('Install', 'InstallerModel');
                $result         = $installerModel->install();

                if ( ! $result) {
                    return $this->output($this->getResultObj(JText::_(strtoupper($module).' installation failed! Error: '.end($app->getMessageQueue())),
                        false));
                }

            } catch (Exception $e) {
                return $this->output($this->getResultObj(JText::_('Error: '.$e->getMessage()), false));
            }

        }

        return $this->output($this->getResultObj(JText::sprintf('%s Modules installation success!', count($modules)), true));
    }


    public function installPlugins()
    {
        $app     = JFactory::getApplication();
        $plugins = [
            'plg_content_quix',
            'plg_editors_xtd_quix',
            'plg_finder_quix',
            'plg_quickicon_quix',
            'plg_quix_content',
            'plg_system_quix',
            'plg_system_seositeattributes'
        ];
        foreach ($plugins as $key => $plugin) {
            // Try to extract the files
            $storage = QX_TMP.'/pkg_quix/'.$plugin.'.zip';
            if ( ! JFile::exists($storage)) {
                continue;
            }

            $tmp   = QX_TMP.'/pkg_quix/'.$plugin;
            //$state = JArchive::extract($storage, $tmp);
            // The archive instance
            $archive = new Archive(array('tmp_path' => JFactory::getConfig()->get('tmp_path')));
            // Extract the archive
            $state = $archive->extract($storage, $tmp);

            if ( ! $state) {
                return $this->output($this->getResultObj(JText::_('COM_QUIX_INSTALLATION_ERROR_EXTRACT_'.strtoupper($plugin)), false));
            }

            try {

                $app->input->set('installtype', 'folder');
                $app->input->set('install_directory', $tmp);
                JModelLegacy::addIncludePath(JPATH_ADMINISTRATOR.'/components/com_installer/models');
                $installerModel = JModelLegacy::getInstance('Install', 'InstallerModel');
                $result         = $installerModel->install();

                if ( ! $result) {
                    return $this->output($this->getResultObj(JText::_(strtoupper($plugin).' installation failed! Error: '.end($app->getMessageQueue())),
                        false));
                }

            } catch (Exception $e) {
                return $this->output($this->getResultObj(JText::_('Error: '.$e->getMessage()), false));
            }

        }

        return $this->output($this->getResultObj(JText::sprintf('%s Plugins installation success!', count($plugins)), true));
    }

    public function installTemplates()
    {
        $app     = JFactory::getApplication();
        $templates = [
            'tpl_atom'
        ];
        foreach ($templates as $key => $template) {
            // Try to extract the files
            $storage = QX_TMP.'/pkg_quix/'.$template.'.zip';
            if ( ! JFile::exists($storage)) {
                continue;
            }

            $tmp   = QX_TMP.'/pkg_quix/'.$template;
            //$state = JArchive::extract($storage, $tmp);

            // The archive instance
            $archive = new Archive(array('tmp_path' => JFactory::getConfig()->get('tmp_path')));
            // Extract the archive
            $state = $archive->extract($storage, $tmp);

            if ( ! $state) {
                return $this->output($this->getResultObj(JText::_('COM_QUIX_INSTALLATION_ERROR_EXTRACT_'.strtoupper($template)), false));
            }

            try {

                $app->input->set('installtype', 'folder');
                $app->input->set('install_directory', $tmp);
                JModelLegacy::addIncludePath(JPATH_ADMINISTRATOR.'/components/com_installer/models');
                $installerModel = JModelLegacy::getInstance('Install', 'InstallerModel');
                $result         = $installerModel->install();

                if ( ! $result) {
                    return $this->output($this->getResultObj(JText::_(strtoupper($template).' installation failed! Error: '.end($app->getMessageQueue())),
                        false));
                }

            } catch (Exception $e) {
                return $this->output($this->getResultObj(JText::_('Error: '.$e->getMessage()), false));
            }

        }

        return $this->output($this->getResultObj(JText::sprintf('%s Templates installation success!', count($templates)), true));
    }

    /**
     * Post installation process
     *
     * @since     1.0
     * @access    public
     */
    public function syncDb()
    {
        include QX_TMP."/pkg_quix/pkg.script.php";

        try {
            $script = new pkg_QuixInstallerScript();
            ob_start();
            $script->postflight(array());
            $data = ob_get_contents();
            ob_end_clean();

            return $this->output($this->getResultObj(JText::_('Updating Database complete!'), true));

        } catch (Exception $e) {
            if (JDEBUG) {
                \JLog::add("iQuix - syncDb failed cause: : ".JText::_('Error: '.$e->getMessage()), JLog::DEBUG, 'iquix');
            }

            return $this->output($this->getResultObj(JText::_('Error: '.$e->getMessage()), false));
        }
    }

    /**
     * Post installation process
     *
     * @since     1.0
     * @access    public
     */
    public function installPost()
    {
        $results = array();

        // Update the api key on the server with the one from the bootstrap
        // $this->updateConfig();

        // update package version
        $this->updateJoomlaUpdater();

        try {
            // Cleanup temporary files from the tmp folder
            $tmp     = dirname(dirname(__FILE__)).'/tmp';
            $folders = JFolder::folders($tmp, '.', false, true);

            if ($folders) {
                foreach ($folders as $folder) {
                    @JFolder::delete($folder);
                }
            }

            // Update installation package to 'launcher'
            $this->updatePackage();

            $result          = new stdClass();
            $result->state   = true;
            $result->message = "Post operation completed!";


        } catch (Exception $e) {
            $result          = new stdClass();
            $result->state   = false;
            $result->message = "Post operation failed! but you proceed...";

        }


        return $this->output($result);
    }

    /**
     * Update installation package to launcher package to update issue via update button
     *
     * @since     2.1.3
     * @access    public
     */
    public function updatePackage()
    {
        // now we need to update the QX_INSTALLER to launcher to that the update button will
        // work correctly. #1558
        $path = JPATH_ADMINISTRATOR.'/components/com_iquix/setup/bootstrap.php';

        // Read the contents
        $contents = file_get_contents($path);

        $contents = str_ireplace("define('QX_INSTALLER', 'full');", "define('QX_INSTALLER', 'launcher');", $contents);
        $contents = preg_replace('/define\(\'QX_PACKAGE\', \'.*\'\);/i', "define('QX_PACKAGE', '');", $contents);

        JFile::write($path, $contents);
    }

    /**
     * Allows cleanup of installation files
     *
     * @since     1.3
     * @access    public
     */
    private function cleanupZipFiles($path)
    {
        return true;

        $zipFiles = JFolder::files($path, '.zip', false, true);

        if ($zipFiles) {
            foreach ($zipFiles as $file) {
                @JFile::delete($file);
            }
        }

        return true;
    }


    public function updateJoomlaUpdater()
    {
        $version = $this->getInstallableVersion();

        $db = JFactory::getDBO();
        // Update installed version
        $query = "SELECT * FROM `#__extensions` WHERE `name` = 'pkg_quix' and `type` = 'package'";
        $db->setQuery($query);
        $result = $db->loadObject();

        $manifest          = json_decode($result->manifest_cache);
        $manifest->version = $version;

        $result->manifest_cache = json_encode($manifest);

        return JFactory::getDbo()->updateObject('#__extensions', $result, 'extension_id');
    }

}
