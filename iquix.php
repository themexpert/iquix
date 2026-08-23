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
use Joomla\Filesystem\File;

define('IQX_VERSION', '2.0.0');

$app   = Factory::getApplication();
$input = $app->getInput();
$file  = JPATH_ROOT . '/tmp/quix.installation';

if ($input->get('exitInstallation', false, 'bool')) {
    if (is_file($file)) {
        File::delete($file);
    }

    $app->redirect('index.php?option=com_quix');
}

if ($input->get('launchInstaller', false, 'bool') && !is_file($file)) {
    // Joomla 4's File::write() takes $buffer by reference, so the payload has
    // to be a variable — passing json_encode(...) straight in is a fatal
    // "Cannot pass parameter 2 by reference" there.
    $marker = json_encode(['new' => false, 'step' => 1, 'status' => 'installing']);

    File::write($file, $marker);
}

require_once __DIR__ . '/setup/bootstrap.php';

$app->close();
