<?php
/**
* @package		Quix
* @copyright	Copyright (C) 2010 - 2017 ThemeXpert.com. All rights reserved.
* @license		GNU/GPL, see LICENSE.php
* Quix is free software. This version may have been modified pursuant
* to the GNU General Public License, and as distributed it includes or
* is derivative of works licensed under the GNU General Public License or
* other free or open source software licenses.
* See COPYRIGHT.php for copyright notices and details.
*/
defined('_JEXEC') or die('Unauthorized Access');
use Joomla\CMS\Filesystem\File;

$app = JFactory::getApplication();
$input = $app->input;

// Ensure that the Joomla sections don't appear.
$input->set('tmpl', 'component');

// Determines if we are now in developer mode.
$developer = $input->get('developer', false, 'bool');

if ($developer) {
	$session = JFactory::getSession();
	$session->set('quix.developer', true);
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

define('QX_SETUP_URL', rtrim(JURI::root(), '/') . '/administrator/components/com_iquix/setup');
define('QX_INSTALLER', 'launcher');
define('QX_PACKAGE', '');
define('QX_BETA', '');

// add constant for product id
define('QX_PRO_ID', '116');
define('QX_FREE_ID', '117');
define('QX_EXT_ID', '118');
define('QX_AGENCY_ID', '127');
define('QX_BUS_ID', '202');
define('QX_PRO_LT_ID', '220');
// Category
define('QX_CATID', '38');

// quix api url to check for response
define('QX_SERVER', 'https://www.themexpert.com/index.php?option=com_digicom&task=responses');

// download the file api
// &pid=116&username=AAA&key=AAA
define('QX_API_DOWNLOAD', QX_SERVER . '&source=release&format=xml&provider=joomla');

// update or release api 
// &pid=116
define('QX_API_UPDATE', QX_SERVER . '&source=release&format=xml&provider=joomla');

// license verification api
// &pid=116&username=AAA&key=AAA || catid=QX_CATID
define('QX_API_LICENSE', QX_SERVER . '&source=authapi');


############################################################
#### Process ajax calls
############################################################
if ($input->get('ajax', false, 'bool')) {

	$controller = $input->get('controller', '', 'cmd');
	$task = $input->get('task', '', 'cmd');

	$controllerFile = QX_CONTROLLERS . '/' . strtolower( $controller ) . '.php';

	require_once($controllerFile);

	$controllerName = 'iQuixController' . ucfirst( $controller );
	$controller = new $controllerName();

	return $controller->$task();
}

############################################################
#### Process controller
############################################################
$controller = $input->get('controller', '', 'cmd');

if (!empty($controller)) {
	$controllerFile = QX_CONTROLLERS . '/' . strtolower($controller) . '.php';

	require_once($controllerFile);

	$controllerName = 'iQuixController' . ucfirst( $controller );
	$controller = new $controllerName();
	return $controller->execute();
}

############################################################
#### Initialization
############################################################
$contents = file_get_contents(QX_CONFIG . '/installation.json');
$steps = json_decode($contents);

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

	$activeStep->title = JText::_('Installation Completed');
	$activeStep->template = 'complete';

	// Assign class names to the step items.
	if ($steps) {
		foreach ($steps as $step) {
			$step->className = ' current done';
		}
	}
} else {
	// Get the active step object.
	$activeStep = $steps[$stepIndex];

	// Assign class names to the step items.
	foreach ($steps as $step) {
		$step->className = $step->index == $active || $step->index < $active ? ' current' : '';
		$step->className .= $step->index < $active ? ' done' : '';
	}
}

require(QX_THEMES . '/default.php');
