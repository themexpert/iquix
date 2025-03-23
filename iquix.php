<?php
/**
 * @package        quix
 * @copyright      Copyright (C) 2010 - 2017 ThemeXpert.com. All rights reserved.
 * @license        GNU/GPL, see LICENSE.php
 * quix is free software. This version may have been modified pursuant
 * to the GNU General Public License, and as distributed it includes or
 * is derivative of works licensed under the GNU General Public License or
 * other free or open source software licenses.
 * See COPYRIGHT.php for copyright notices and details.
 */
defined('_JEXEC') or die('Unauthorized Access');

use Joomla\CMS\Factory;
use Joomla\CMS\Filesystem\File;
use Joomla\CMS\Log\Log;
// define version
define('IQX_VERSION', '1.8.0');

if (defined('JDEBUG') && JDEBUG) {
	Log::addLogger(array('text_file' => 'iquix.log.php'), Log::ALL, array('iquix'));
}

$app = Factory::getApplication();
$input = $app->input;
$exitInstallation = $input->get('exitInstallation', false, 'bool');

// Check if there's a file initiated for installation
$file = JPATH_ROOT . '/tmp/quix.installation';
if ($exitInstallation) {
	if (File::exists($file)) {
		File::delete($file);
		return $app->redirect('index.php?option=com_quix');
	}
}

// check if we need to proceed with installation
$launchInstaller = $input->get('launchInstaller', false, 'bool');
if ($launchInstaller) {
	// Determines if the installation is a new installation or old installation.
	$obj = new stdClass();
	$obj->new = false;
	$obj->step = 1;
	$obj->status = 'installing';

	$contents = json_encode($obj);

	if (!File::exists($file)) {
		File::write($file, $contents);
	}
}

// finally check for setup view or not
require_once(dirname(__FILE__) . '/setup/bootstrap.php');
JExit();