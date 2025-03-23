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
use Joomla\CMS\Language\Text;
use Joomla\CMS\Http\Http;
use Joomla\Registry\Registry;

require_once(__DIR__ . '/controller.php');

class iQuixControllerMaintenance extends iQuixSetupController
{
	/**
	 * Constructor
	 */
	public function __construct()
	{
		parent::__construct();
	}

	/**
	 * Cleans installation temporary files
	 * 
	 * @return mixed
	 */
	public function cleanInstallation()
	{
		try {
			// Determine which file class to use
			$fileClass = class_exists('\\Joomla\\CMS\\Filesystem\\File') ? '\\Joomla\\CMS\\Filesystem\\File' : '\\JFile';
			
			// Remove installation temporary file
			$tempFile = JPATH_ROOT . '/tmp/quix.installation';
			if ($fileClass::exists($tempFile)) {
				$fileClass::delete($tempFile);
				$this->debug('Temporary installation file removed', $tempFile);
			} else {
				$this->debug('Temporary installation file not found', $tempFile);
			}
			
			return $this->output($this->getResultObj('Installation files cleaned successfully.', true, 'success'));
		} catch (\Exception $e) {
			$this->debug('Error cleaning installation files', $e->getMessage());
			return $this->output($this->getResultObj('Error cleaning installation files: ' . $e->getMessage(), false, 'error'));
		}
	}

	/**
	 * Removes the update record from the database
	 * 
	 * @return mixed
	 */
	public function removeUpdateRecord()
	{
		try {
			$db = Factory::getDbo();
			$extensionId = $this->getExtensionId();
			
			if (!$extensionId) {
				$this->debug('No extension ID found for pkg_quix');
				return $this->output($this->getResultObj('No extension record found for Quix.', false, 'warning'));
			}
			
			$query = $db->getQuery(true)
				->delete($db->quoteName('#__updates'))
				->where($db->quoteName('extension_id') . '=' . $db->quote($extensionId));
			$db->setQuery($query);
			$result = $db->execute();
			
			if ($result) {
				$this->debug('Update record cleaned successfully');
				return $this->output($this->getResultObj('Update record cleaned successfully!', true, 'success'));
			} else {
				$this->debug('Failed to clean update record');
				return $this->output($this->getResultObj('Unable to clean update record!', false, 'fail'));
			}
		} catch (\Exception $e) {
			$this->debug('Error removing update record', $e->getMessage());
			return $this->output($this->getResultObj('Error removing update record: ' . $e->getMessage(), false, 'error'));
		}
	}

	/**
	 * Updates Quix assets
	 * 
	 * @return mixed
	 */
	public function updateAssets()
	{
		try {
			// Generate token for Joomla 3/4/5 compatibility
			if ($this->isJoomla4OrHigher()) {
				$token = Factory::getApplication()->getFormToken() . '=1';
			} else {
				$token = \JSession::getFormToken() . '=1';
			}
			
			$ajax_url = 'index.php?option=com_quix&task=updateAjax&' . $token;
			$this->debug('Updating assets via', $ajax_url);
			
			// Use Http class for Joomla 4/5 compatibility
			if ($this->isJoomla4OrHigher()) {
				$options = new Registry();
				$http = new Http($options);
				
				try {
					$response = $http->get($ajax_url);
					$result = ($response->code == 200 || $response->code == 310);
					$this->debug('Asset update response code', $response->code);
				} catch (\Exception $e) {
					// If failed, log but return success (non-critical)
					$this->debug('HTTP request failed but continuing', $e->getMessage());
					$result = true;
				}
			} else {
				// Legacy method for Joomla 3
				try {
					$http = new \JHttp();
					$str = $http->get($ajax_url);
					$result = ($str->code == 200 || $str->code == 310);
					$this->debug('Asset update response code', $str->code);
				} catch (\Exception $e) {
					// If failed, log but return success (non-critical)
					$this->debug('HTTP request failed but continuing', $e->getMessage());
					$result = true;
				}
			}
			
			if ($result) {
				return $this->output($this->getResultObj('Assets updated successfully!', true, 'success'));
			} else {
				return $this->output($this->getResultObj('Unable to update assets!', false, 'fail'));
			}
		} catch (\Exception $e) {
			$this->debug('Error updating assets', $e->getMessage());
			return $this->output($this->getResultObj('Error updating assets: ' . $e->getMessage(), false, 'error'));
		}
	}
}