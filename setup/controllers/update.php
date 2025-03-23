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

require_once(__DIR__ . '/controller.php');

class iQuixControllerUpdate extends iQuixSetupController
{
	/**
	 * Verifies the user's license
	 *
	 * @since	2.1.0
	 * @access	public
	 */
	public function updateScript()
	{
		$session = JFactory::getSession();
		// $session->set('quix.scriptupdate', false);
		$scriptupdate = $session->get('quix.scriptupdate', false);
		if($scriptupdate)
		{
			$this->output($this->getResultObj( 'Already checked!' , true ));	
		}

		// 1. get current version
		$localVersion = $this->getVersion();
		// 2. get latest version info from server
		$xml = $this->getLatestVersion();
		
		$update 	= $xml->update[0]; // first one is the latest one
		if(isset($update->version))
		{
			$onlineVersion 	= (string) $update->version;
		}
		else
		{
			$onlineVersion = '1.0.0';
		}

		$session->set('quix.scriptupdate', true);

		// 3. match versions
		if(version_compare($onlineVersion, $localVersion) == '1')
		{
			$result = $this->installScriptComponent($update);
			if($result)
			{
				$this->output($this->getResultObj( 'Updated successfully!' , true , 302));	
			}
			else
			{
				$this->output($this->getResultObj( 'Something wrong to update the script! please manually install the Installer.' , false ));	
			}
		}
		else
		{
			// need to update the script
			// either we have updated or same
			$this->output($this->getResultObj( 'You have the latest version' , true ));			
		}
	}

	public function getLatestVersion()
	{
		$ch = curl_init('https://raw.githubusercontent.com/themexpert/iquix/master/mainfest.xml');

		curl_setopt($ch, CURLOPT_POST, false);
		// curl_setopt($ch, CURLOPT_POSTFIELDS, 'key=' . QX_KEY . '&version=' . $info->version);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_TIMEOUT, 35000);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

		$contents = curl_exec($ch);
		curl_close($ch);

		$xml 	= simplexml_load_string( $contents );

		return $xml;
	}

	public function installScriptComponent($update)
	{
		try {
			$app = JFactory::getApplication();
			$app->input->set('installtype', 'url');
			$app->input->set('install_url', $update->downloads->downloadurl); //install_directory
			JModelLegacy::addIncludePath(JPATH_ADMINISTRATOR.'/components/com_installer/models');
			$installerModel = JModelLegacy::getInstance('Install', 'InstallerModel');
			return $installerModel->install();
		} catch (Exception $e) {
			return $this->output($this->getResultObj( JText::_( 'Error: ' . $e->getMessage() ) , false ));
		}
	}
}