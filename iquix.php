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

if (defined('JDEBUG') && JDEBUG)
{
	\JLog::addLogger(array('text_file' => 'iquix.log.php'), \JLog::ALL, array('iquix'));
}


jimport('joomla.filesystem.folder');
jimport('joomla.filesystem.file');

$app = JFactory::getApplication();
$input = $app->input;
$exitInstallation = $input->get('exitInstallation', false, 'bool');

// Check if there's a file initiated for installation
$file = JPATH_ROOT . '/tmp/quix.installation';
if ($exitInstallation) {
	if (JFile::exists($file)) {
		JFile::delete($file);
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

	if (!JFile::exists($file)) {
		JFile::write($file, $contents);
	}	
}

// finally check for setup view or not
// $active = $input->get('active', 0, 'int');
// if (JFile::exists($file) || $active) {
	require_once(dirname(__FILE__) . '/setup/bootstrap.php');
	JExit();
// }

// Regular operation starts
// Access check.
// if ( !JFactory::getUser()->authorise( 'core.manage', 'com_iquix' ) ) {
//   return JError::raiseWarning( 404, JText::_( 'JERROR_ALERTNOAUTHOR' ) );
// }

// Include dependancies
// jimport( 'quix.app.bootstrap' );
// jimport( 'quix.app.init' );

// $controller = JControllerLegacy::getInstance( 'iQuix' );
// $controller->execute( JFactory::getApplication()->input->get( 'task' ) );
// $controller->redirect();
