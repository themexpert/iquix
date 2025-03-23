<?php
/**
* @package		Quix
* @copyright	Copyright (C) 2010 - 2023 ThemeXpert.com. All rights reserved.
* @license		GNU/GPL, see LICENSE.php
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
use Joomla\CMS\Language\Text;
use Joomla\CMS\Http\Http;
use Joomla\Registry\Registry;
use Joomla\CMS\Version;
use Joomla\CMS\Uri\Uri;

require_once(__DIR__ . '/controller.php');

class iQuixControllerUpdate extends iQuixSetupController
{
	/**
	 * Constructor
	 */
	public function __construct()
	{
		parent::__construct();
	}

	/**
	 * Updates Joomla updater database
	 * 
	 * @return mixed
	 */
	public function updateJoomlaUpdater()
	{
		try {
			// Get Joomla Version to determine the approach
			$jVersion = null;
			if (class_exists('\\Joomla\\CMS\\Version')) {
				$version = new Version();
				$jVersion = $version->getShortVersion();
			} else {
				$jVerArr = explode('.', JVERSION);
				$jVersion = $jVerArr[0] . '.' . $jVerArr[1];
			}
			
			$isJoomla4OrHigher = version_compare($jVersion, '4.0', '>=');
			$this->debug('Updating Joomla updater for version', $jVersion);
			
			// Get the extension ID
			$extensionId = $this->getExtensionId();
			
			if (!$extensionId) {
				$this->debug('No extension ID found for pkg_quix');
				return $this->output($this->getResultObj('No extension record found for Quix.', false, 'warning'));
			}
			
			// Get license ID from session
			$session = $isJoomla4OrHigher ? Factory::getApplication()->getSession() : Factory::getSession();
			$id = $session->get('quix.id', '');
			
			if (empty($id)) {
				$this->debug('No license ID found in session');
				return $this->output($this->getResultObj('No license information found.', false, 'error'));
			}
			
			// Build the update URL
			$update_site = QX_API_UPDATE . '&pid=' . $id;
			$this->debug('Update site URL', $update_site);
			
			// Update the update site information in the database
			$db = Factory::getDbo();
			
			// First check if the update site exists
			$query = $db->getQuery(true)
				->select('update_site_id')
				->from($db->quoteName('#__update_sites'))
				->where($db->quoteName('name') . ' = ' . $db->quote('Quix'))
				->where($db->quoteName('type') . ' = ' . $db->quote('extension'));
			
			$db->setQuery($query);
			$update_site_id = $db->loadResult();
			
			if ($update_site_id) {
				// Update existing update site
				$query = $db->getQuery(true)
					->update($db->quoteName('#__update_sites'))
					->set($db->quoteName('location') . ' = ' . $db->quote($update_site))
					->where($db->quoteName('update_site_id') . ' = ' . $db->quote($update_site_id));
				$db->setQuery($query);
				$db->execute();
				
				$this->debug('Updated existing update site record', $update_site_id);
			} else {
				// Insert new update site
				$updateSiteObject = new \stdClass;
				$updateSiteObject->name = 'Quix';
				$updateSiteObject->type = 'extension';
				$updateSiteObject->location = $update_site;
				$updateSiteObject->enabled = 1;
				$updateSiteObject->last_check_timestamp = 0;
				
				$db->insertObject('#__update_sites', $updateSiteObject);
				$update_site_id = $db->insertid();
				
				// Link the update site to the extension
				if ($update_site_id && $extensionId) {
					$updateSiteExtObject = new \stdClass;
					$updateSiteExtObject->update_site_id = $update_site_id;
					$updateSiteExtObject->extension_id = $extensionId;
					
					$db->insertObject('#__update_sites_extensions', $updateSiteExtObject);
					$this->debug('Created new update site record', $update_site_id);
				}
			}
			
			// Refresh the update information
			if ($isJoomla4OrHigher) {
				// For Joomla 4 and 5
				$query = $db->getQuery(true)
					->delete($db->quoteName('#__updates'))
					->where($db->quoteName('extension_id') . ' = ' . $db->quote($extensionId));
				$db->setQuery($query);
				$db->execute();
				
				// Trigger update check
				$updateSites = [$update_site_id];
				$modelFile = JPATH_ADMINISTRATOR . '/components/com_installer/models/update.php';
				
				if (File::exists($modelFile)) {
					require_once $modelFile;
					$model = new \Joomla\Component\Installer\Administrator\Model\UpdateModel(['update_site_id' => $updateSites]);
					$model->findUpdates($extensionId, 0);
					$this->debug('Triggered update check via model');
				} else {
					// Alternative method if model not available
					$app = Factory::getApplication();
					$app->triggerEvent('onExtensionAfterUpdate', ['installer.updatecache', null, ['update_site_id' => $updateSites]]);
					$this->debug('Triggered update check via event');
				}
			} else {
				// For Joomla 3
				$query = $db->getQuery(true)
					->delete($db->quoteName('#__updates'))
					->where($db->quoteName('extension_id') . ' = ' . $db->quote($extensionId));
				$db->setQuery($query);
				$db->execute();
				
				// Trigger update check
				Factory::getApplication()->triggerEvent('onExtensionAfterUpdate', 
					['installer.updatecache', null]
				);
				$this->debug('Triggered update check via event (J3)');
			}
			
			return $this->output($this->getResultObj('Joomla updater updated successfully!', true, 'success'));
		} catch (\Exception $e) {
			$this->debug('Error updating Joomla updater', $e->getMessage());
			return $this->output($this->getResultObj('Error updating Joomla updater: ' . $e->getMessage(), false, 'error'));
		}
	}

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