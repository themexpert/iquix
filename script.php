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

class com_iQuixInstallerScript
{
	/**
	 * Triggers before the installers are copied
	 *
	 * @since	1.0
	 * @access	public
	 */
	public function postflight()
	{
		ob_start();
		include(__DIR__ . '/setup.html');
		
		$contents = ob_get_contents();
		ob_end_clean();

		echo $contents;
	}

	/**
	 * Triggers after the installers are copied
	 *
	 * @since	1.0
	 * @access	public
	 */
	public function preflight()
	{
		// During the preflight, we need to create a new installer file in the temporary folder
		$file = JPATH_ROOT . '/tmp/quix.installation';

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

	/**
	 * Responsible to perform the installation
	 *
	 * @since	1.0
	 * @access	public
	 */
	public function install()
	{
		$this->createTableConfig();
	}

	/**
	 * Responsible to perform the uninstallation
	 *
	 * @since	1.0
	 * @access	public
	 */
	public function uninstall()
	{
		// @TODO: Disable modules
		// @TODO: Disable plugins
	}

	/**
	 * Responsible to perform component updates
	 *
	 * @since	1.0
	 * @access	public
	 */
	public function update()
	{
		$this->createTableConfig();
	}

	function createTableConfig()
	{
		$db = JFactory::getDbo();
		$sql = "
		CREATE TABLE IF NOT EXISTS `#__quix_configs` (
		  `name` varchar(255) NOT NULL,
		  `params` text NOT NULL
		) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COMMENT='Store any configuration in key => params maps';
		";
		$db->setQuery($sql);
		return $db->execute();
	}
}
