<?php
/**
 * @package        Quix
 * @copyright      Copyright (C) 2010 - 2023 ThemeXpert.com. All rights reserved.
 * @license        GNU/GPL, see LICENSE.php
 * Quix is free software. This version may have been modified pursuant
 * to the GNU General Public License, and as distributed it includes or
 * is derivative of works licensed under the GNU General Public License or
 * other free or open source software licenses.
 * See COPYRIGHT.php for copyright notices and details.
 */
defined('_JEXEC') or die('Unauthorized Access');

// Joomla 3 compatibility layer
if (!defined('JPATH_COMPONENT_ADMINISTRATOR')) {
    define('JPATH_COMPONENT_ADMINISTRATOR', JPATH_ADMINISTRATOR . '/components/com_iquix');
}

// Import namespaced classes for Joomla 4 and 5 compatibility
use Joomla\CMS\Factory;
use Joomla\CMS\Filesystem\File;
use Joomla\CMS\Filesystem\Folder;
use Joomla\CMS\Uri\Uri;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Session\Session;
use Joomla\CMS\Log\Log;
use Joomla\CMS\Http\HttpFactory;
use Joomla\Registry\Registry;
use Joomla\CMS\Application\ApplicationHelper;
use Joomla\CMS\Installer\Installer;
use Joomla\CMS\Version;

// For backward compatibility with Joomla 3
jimport('joomla.filesystem.folder');
jimport('joomla.filesystem.file');
jimport('joomla.filesystem.archive');
jimport('joomla.database.driver');
jimport('joomla.installer.helper');

class iQuixSetupController
{
    /**
     * @var array
     */
    private $result = [];

    /**
     * @var \Joomla\CMS\Application\CMSApplication
     */
    protected $app;
    
    /**
     * @var \Joomla\Input\Input
     */
    protected $input;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->app   = Factory::getApplication();
        $this->input = $this->app->input;
        
        // Initialize debug logging
        if ($this->isDebugEnabled()) {
            $this->initDebugLogging();
        }
    }

    /**
     * Check if debug is enabled
     * 
     * @return bool
     */
    protected function isDebugEnabled()
    {
        return defined('JDEBUG') && JDEBUG;
    }
    
    /**
     * Initialize debug logging
     */
    protected function initDebugLogging()
    {
        Log::addLogger(['text_file' => 'iquix.log.php'], Log::ALL, ['iquix']);
        $this->debug('Initialized debug logging');
    }
    
    /**
     * Add debug log entry
     * 
     * @param string $message
     * @param mixed $data Optional data to include in log
     */
    protected function debug($message, $data = null)
    {
        if ($this->isDebugEnabled()) {
            $logMessage = "iQuix - " . $message;
            if ($data !== null) {
                $logMessage .= ': ' . (is_string($data) ? $data : json_encode($data));
            }
            Log::add($logMessage, Log::DEBUG, 'iquix');
        }
    }

    /**
     * Add data to the result
     *
     * @param string $key
     * @param mixed $value
     */
    protected function data($key, $value)
    {
        $obj       = new \stdClass();
        $obj->$key = $value;

        $this->result[] = $obj;
    }

    /**
     * Renders a response with proper headers
     *
     * @param array $data
     * @return void
     * @since 2.1.0
     */
    public function output($data = [])
    {
        header('Content-Type: application/json; UTF-8');

        if (empty($data)) {
            $data = $this->result;
        }
        
        // Add debug information for AJAX responses if debug is enabled
        if ($this->isDebugEnabled() && is_array($data)) {
            $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2);
            $caller = isset($trace[1]['function']) ? $trace[1]['function'] : '';
            $this->debug("AJAX Response from {$caller}", $data);
        }

        echo json_encode($data);
        exit;
    }

    /**
     * Generates a result object that can be json encoded
     *
     * @param string $message
     * @param bool $state
     * @param string $stateMessage
     * @return \stdClass
     * @since 2.1.0
     */
    public function getResultObj($message, $state, $stateMessage = '')
    {
        $obj               = new \stdClass();
        $obj->state        = $state;
        $obj->stateMessage = $stateMessage;
        
        // Use namespaced Text for Joomla 4/5, fall back to JText for Joomla 3
        if (class_exists('\\Joomla\\CMS\\Language\\Text')) {
            $obj->message = Text::_($message);
        } else {
            $obj->message = \JText::_($message);
        }

        return $obj;
    }

    /**
     * Get's the version of this launcher so we know which to install
     *
     * @return string
     * @since 1.0
     */
    public function getVersion()
    {
        static $version = null;

        // Get the version from the manifest file
        if (is_null($version)) {
            $manifestPath = JPATH_ROOT . '/administrator/components/com_iquix/iquix.xml';
            
            // Use File class for Joomla 4/5 compatibility
            if (class_exists('\\Joomla\\CMS\\Filesystem\\File')) {
                $contents = File::exists($manifestPath) ? file_get_contents($manifestPath) : '';
            } else {
                $contents = \JFile::exists($manifestPath) ? file_get_contents($manifestPath) : '';
            }
            
            if (empty($contents)) {
                $this->debug('Manifest file not found or empty: ' . $manifestPath);
                return '0.0.0';
            }
            
            $parser   = simplexml_load_string($contents);
            $version  = $parser->xpath('version');
            $version  = (string) $version[0];
        }

        $this->debug("Version", $version);
        return $version;
    }

    /**
     * Retrieve the Joomla Version
     *
     * @return string
     * @since 2.0
     */
    public function getJoomlaVersion()
    {
        // For Joomla 4 and 5, use Version class
        if (class_exists('\\Joomla\\CMS\\Version')) {
            $version = new Version();
            $jVersion = $version->getShortVersion();
        } else {
            // Legacy method for Joomla 3
            $jVerArr  = explode('.', JVERSION);
            $jVersion = $jVerArr[0] . '.' . $jVerArr[1];
        }

        $this->debug("Joomla Version", $jVersion);
        return $jVersion;
    }

    /**
     * Check if current Joomla version is at least version 4
     * 
     * @return bool
     */
    protected function isJoomla4OrHigher()
    {
        $version = $this->getJoomlaVersion();
        return version_compare($version, '4.0', '>=');
    }

    /**
     * Check if current Joomla version is at least version 5
     * 
     * @return bool
     */
    protected function isJoomla5OrHigher()
    {
        $version = $this->getJoomlaVersion();
        return version_compare($version, '5.0', '>=');
    }

    /**
     * Retrieves the current site's domain information
     *
     * @return string
     * @since 2.0.9
     */
    public function getDomain()
    {
        static $domain = null;

        if (is_null($domain)) {
            // Use Uri class for Joomla 4/5 compatibility
            if (class_exists('\\Joomla\\CMS\\Uri\\Uri')) {
                $domain = Uri::root();
            } else {
                $domain = \JURI::root();
            }
            
            $domain = str_ireplace(['http://', 'https://'], '', $domain);
            $domain = rtrim($domain, '/');
        }

        $this->debug("Domain", $domain);
        return $domain;
    }

    /**
     * Retrieves the information about the latest version
     *
     * @return object|false
     * @since 2.0.9
     */
    public function getInfo()
    {
        $session  = $this->isJoomla4OrHigher() ? Factory::getApplication()->getSession() : Factory::getSession();
        $username = $session->get('quix.username', '');
        $key      = $session->get('quix.key', '');
        $id       = $session->get('quix.id', '');

        $url = QX_API_LICENSE . '&pid=' . $id . '&username=' . $username . '&key=' . $key;
        
        $this->debug("Retrieving information from", $url);

        try {
            // Use Http class for Joomla 4/5 compatibility
            if ($this->isJoomla4OrHigher()) {
                $httpOptions = new Registry();
                $http = HttpFactory::getHttp($httpOptions);
                $response = $http->get($url);
                $result = $response->body;
            } else {
                // Legacy method for Joomla 3
                $resource = curl_init();
                curl_setopt($resource, CURLOPT_URL, $url);
                curl_setopt($resource, CURLOPT_POST, false);
                curl_setopt($resource, CURLOPT_TIMEOUT, 120);
                curl_setopt($resource, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($resource, CURLOPT_SSL_VERIFYPEER, false);
                $result = curl_exec($resource);
                curl_close($resource);
            }

            $this->debug("Server response", $result);

            if (empty($result)) {
                return false;
            }

            $obj = json_decode($result);
            if (json_last_error() !== JSON_ERROR_NONE) {
                $this->debug("JSON decode error", json_last_error_msg());
                return false;
            }

            return $obj;
        } catch (\Exception $e) {
            $this->debug("Error retrieving information", $e->getMessage());
            return false;
        }
    }

    /**
     * Get authentication info from database
     *
     * @return void
     */
    public function getAuthInfo()
    {
        $db = Factory::getDbo();
        $query = $db->getQuery(true);
        $query->select('*')->from('#__quix_configs');
        $db->setQuery($query);
        
        try {
            $result = $db->loadObjectList();
            $this->debug("User info", $result);
            return $this->output($result);
        } catch (\Exception $e) {
            $this->debug("Error getting auth info", $e->getMessage());
            return $this->output($this->getResultObj("Error retrieving authentication info: " . $e->getMessage(), false, 'error'));
        }
    }

    /**
     * Loads the installed version of Quix
     *
     * @return string
     * @since 1.0
     */
    public function getInstalledVersion()
    {
        $manifestPath = JPATH_ADMINISTRATOR . '/components/com_quix/quix.xml';
        
        if (!file_exists($manifestPath)) {
            $this->debug("Manifest file does not exist", $manifestPath);
            return '0.0.0';
        }
        
        try {
            $xml = new \SimpleXMLElement(file_get_contents($manifestPath));
            $version = (string) $xml->version;
            $this->debug("COM_QUIX version", $version);
            return $version;
        } catch (\Exception $e) {
            $this->debug("Error reading installed version", $e->getMessage());
            return '0.0.0';
        }
    }

    /**
     * Get previous installed version
     *
     * @return string
     * @since 1.0
     */
    public function getPreviousVersion()
    {
        return $this->getInstalledVersion();
    }

    /**
     * Determines if we are in development mode
     *
     * @return bool
     * @since 1.2
     */
    public function isDevelopment()
    {
        $session = $this->isJoomla4OrHigher() ? Factory::getApplication()->getSession() : Factory::getSession();
        $developer = $session->get('quix.developer');
        return $developer;
    }

    /**
     * Verifies the api key
     *
     * @param string $username
     * @param string $key
     * @return mixed
     * @since 2.1.0
     */
    public function verifyApiKey($username, $key)
    {
        $url = QX_API_LICENSE . '&catid=' . QX_CATID . '&username=' . $username . '&key=' . $key;
        $this->debug("API Auth URL", $url);

        try {
            // Use Http class for Joomla 4/5 compatibility
            if ($this->isJoomla4OrHigher()) {
                $httpOptions = new Registry();
                $http = HttpFactory::getHttp($httpOptions);
                $response = $http->get($url);
                
                if ($response->code != 200 && $response->code != 310) {
                    $this->debug("API Auth failed with code", $response->code);
                    return false;
                }
                
                $result = json_decode($response->body);
            } else {
                // Legacy method for Joomla 3
                $httpOption = new Registry();
                $http = HttpFactory::getHttp($httpOption);
                $str = $http->get($url);
                
                if ($str->code != 200 && $str->code != 310) {
                    $this->debug("API Auth failed with code", $str->code);
                    return false;
                }
                
                $result = json_decode($str->body);
            }
            
            $this->debug("API Auth result", $result);
            return $result;
        } catch (\Exception $e) {
            $this->debug("API Auth error", $e->getMessage());
            return false;
        }
    }

    /**
     * Retrieves the extension id
     *
     * @param string $ext
     * @return int|null
     * @since 2.0.10
     */
    public function getExtensionId($ext = 'pkg_quix')
    {
        try {
            $db = Factory::getDbo();
            $query = $db->getQuery(true)
                ->select('extension_id')
                ->from($db->quoteName('#__extensions'))
                ->where($db->quoteName('element') . ' = ' . $db->quote($ext));
            $db->setQuery($query);
            
            $extensionId = $db->loadResult();
            $this->debug("{$ext} extension ID", $extensionId);
            
            return $extensionId;
        } catch (\Exception $e) {
            $this->debug("Error getting extension ID", $e->getMessage());
            return null;
        }
    }

    /**
     * Retrieves the information about the latest version
     *
     * @return string|false
     * @since 2.0.9
     */
    public function getReleaseInfo()
    {
        $session = $this->isJoomla4OrHigher() ? Factory::getApplication()->getSession() : Factory::getSession();
        $username = $session->get('quix.username', '');
        $key = $session->get('quix.key', '');
        $id = $session->get('quix.id', '');

        if (empty($id)) {
            $this->debug("No product ID found in session");
            return false;
        }
        
        try {
            // For Joomla 4/5
            if ($this->isJoomla4OrHigher()) {
                $updateClass = '\\Joomla\\CMS\\Updater\\Update';
                $update = new $updateClass;
            } else {
                // For Joomla 3
                $update = new \JUpdate;
            }
            
            $update->loadFromXml(QX_API_UPDATE . '&pid=' . $id, \JUpdater::STABILITY_STABLE);
            $this->debug("Release XML info", $update);
            
            // Handle different property access between Joomla versions
            if ($this->isJoomla4OrHigher()) {
                $downloadUrl = $update->get('downloadurl', []);
                $downloadUrl = is_object($downloadUrl) && isset($downloadUrl->_data) ? $downloadUrl->_data : '';
            } else {
                $downloadUrl = $update->get('downloadurl')->_data ?? '';
            }
            
            if (empty($downloadUrl)) {
                $this->debug("No download URL found in update XML");
                return false;
            }

            $downloadUrl = $downloadUrl . '&username=' . $username . '&key=' . $key;
            $this->debug("Download URL", $downloadUrl);
            
            return $downloadUrl;
        } catch (\Exception $e) {
            $this->debug("Error getting release info", $e->getMessage());
            return false;
        }
    }
}
