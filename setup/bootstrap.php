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

use Joomla\CMS\Factory;
use Joomla\CMS\Filesystem\File;
use Joomla\CMS\Filesystem\Folder;
use Joomla\CMS\Uri\Uri;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Session\Session;
use Joomla\CMS\Log\Log;
use Joomla\CMS\Version;

// Initialize logging if in debug mode
if (defined('JDEBUG') && JDEBUG) {
    Log::addLogger(['text_file' => 'iquix.log.php'], Log::ALL, ['iquix']);
    Log::add('iQuix - Bootstrap initialization started', Log::DEBUG, 'iquix');
}

// Get application and input objects
$app = Factory::getApplication();
$input = $app->input;

// Ensure that the Joomla sections don't appear
$input->set('tmpl', 'component');

// Get Joomla version
$jVersion = (new Version())->getShortVersion();
$isJoomla4OrAbove = version_compare($jVersion, '4.0', '>=');
$isJoomla5OrAbove = version_compare($jVersion, '5.0', '>=');

if (defined('JDEBUG') && JDEBUG) {
    Log::add('iQuix - Joomla Version: ' . $jVersion, Log::DEBUG, 'iquix');
    Log::add('iQuix - Is Joomla 4+: ' . ($isJoomla4OrAbove ? 'Yes' : 'No'), Log::DEBUG, 'iquix');
    Log::add('iQuix - Is Joomla 5+: ' . ($isJoomla5OrAbove ? 'Yes' : 'No'), Log::DEBUG, 'iquix');
}

// Determines if we are now in developer mode
$developer = $input->get('developer', false, 'bool');

if ($developer) {
    $session = $isJoomla4OrAbove ? $app->getSession() : Factory::getSession();
    $session->set('quix.developer', true);
    
    if (defined('JDEBUG') && JDEBUG) {
        Log::add('iQuix - Developer mode enabled', Log::DEBUG, 'iquix');
    }
}

############################################################
#### Constants
############################################################
$path = __DIR__;
define('QX_PACKAGES', $path . '/packages');
define('QX_CONFIG', $path . '/config');
define('QX_THEMES', $path . '/views');
define('QX_LIB', $path . '/libraries');
define('QX_CONTROLLERS', $path . '/controllers');
define('QX_TMP', $path . '/tmp');

// Use Uri class for Joomla 4/5 compatibility
define('QX_SETUP_URL', rtrim(Uri::root(), '/') . '/administrator/components/com_iquix/setup');
define('QX_INSTALLER', 'launcher');
define('QX_PACKAGE', '');
define('QX_BETA', '');

// Add constant for product id
define('QX_PRO_ID', '116');
define('QX_FREE_ID', '117');
define('QX_EXT_ID', '118');
define('QX_AGENCY_ID', '127');
define('QX_BUS_ID', '202');
define('QX_PRO_LT_ID', '220');
// Category
define('QX_CATID', '38');

// Quix API URLs
define('QX_SERVER', 'https://www.themexpert.com/index.php?option=com_digicom&task=responses');
define('QX_API_DOWNLOAD', QX_SERVER . '&source=release&format=xml&provider=joomla');
define('QX_API_UPDATE', QX_SERVER . '&source=release&format=xml&provider=joomla');
define('QX_API_LICENSE', QX_SERVER . '&source=authapi');

############################################################
#### Process ajax calls
############################################################
if ($input->get('ajax', false, 'bool')) {
    $controller = $input->get('controller', '', 'cmd');
    $task = $input->get('task', '', 'cmd');

    $controllerFile = QX_CONTROLLERS . '/' . strtolower($controller) . '.php';

    if (defined('JDEBUG') && JDEBUG) {
        Log::add('iQuix - Processing AJAX request: ' . $controller . '/' . $task, Log::DEBUG, 'iquix');
    }

    try {
        if (File::exists($controllerFile)) {
            require_once($controllerFile);

            $controllerName = 'iQuixController' . ucfirst($controller);
            
            if (class_exists($controllerName)) {
                $controllerInstance = new $controllerName();
                
                if (method_exists($controllerInstance, $task)) {
                    return $controllerInstance->$task();
                } else {
                    throw new Exception('Task not found: ' . $task);
                }
            } else {
                throw new Exception('Controller class not found: ' . $controllerName);
            }
        } else {
            throw new Exception('Controller file not found: ' . $controllerFile);
        }
    } catch (Exception $e) {
        if (defined('JDEBUG') && JDEBUG) {
            Log::add('iQuix - AJAX Error: ' . $e->getMessage(), Log::ERROR, 'iquix');
        }
        
        // Send JSON error response
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'status' => false,
            'message' => 'Error: ' . $e->getMessage()
        ]);
        $app->close();
    }
}

############################################################
#### Process controller
############################################################
$controller = $input->get('controller', '', 'cmd');

if (!empty($controller)) {
    $controllerFile = QX_CONTROLLERS . '/' . strtolower($controller) . '.php';

    if (defined('JDEBUG') && JDEBUG) {
        Log::add('iQuix - Processing controller request: ' . $controller, Log::DEBUG, 'iquix');
    }

    try {
        if (File::exists($controllerFile)) {
            require_once($controllerFile);

            $controllerName = 'iQuixController' . ucfirst($controller);
            
            if (class_exists($controllerName)) {
                $controllerInstance = new $controllerName();
                return $controllerInstance->execute();
            } else {
                throw new Exception('Controller class not found: ' . $controllerName);
            }
        } else {
            throw new Exception('Controller file not found: ' . $controllerFile);
        }
    } catch (Exception $e) {
        if (defined('JDEBUG') && JDEBUG) {
            Log::add('iQuix - Controller Error: ' . $e->getMessage(), Log::ERROR, 'iquix');
        }
        
        $app->enqueueMessage('Error: ' . $e->getMessage(), 'error');
    }
}

############################################################
#### Initialization
############################################################
try {
    $contents = file_get_contents(QX_CONFIG . '/installation.json');
    $steps = json_decode($contents);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception('Failed to parse installation steps: ' . json_last_error_msg());
    }
} catch (Exception $e) {
    if (defined('JDEBUG') && JDEBUG) {
        Log::add('iQuix - Initialization Error: ' . $e->getMessage(), Log::ERROR, 'iquix');
    }
    
    $app->enqueueMessage('Error loading installation configuration: ' . $e->getMessage(), 'error');
    $steps = [];
}

############################################################
#### Workflow
############################################################
$active = $input->get('active', 0, 'int');

if ($active == 0) {
    $active = 1;
    $stepIndex = 0;
} else {
    $active += 1;
    $stepIndex = $active - 1;
}

if ($active > count($steps)) {
    $active = 'complete';
    $activeStep = new stdClass();

    $activeStep->title = Text::_('Installation Completed');
    $activeStep->template = 'complete';
} else {
    $activeStep = $steps[$stepIndex];
}

// Register variables for main template
$template = $activeStep->template ?? 'default';
$title = $activeStep->title ?? '';
$description = $activeStep->description ?? '';

// Include the main template
$themeFile = QX_THEMES . '/default.php';
if (File::exists($themeFile)) {
    include($themeFile);
} else {
    $app->enqueueMessage('Theme file not found: ' . $themeFile, 'error');
}