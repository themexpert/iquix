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
use Joomla\CMS\Language\Text;
use Joomla\CMS\Log\Log;
use Joomla\CMS\Uri\Uri;
use Joomla\Registry\Registry;
use Joomla\CMS\Http\HttpFactory;

require_once(__DIR__ . '/controller.php');

class iQuixControllerLicense extends iQuixSetupController
{
	/**
	 * Execute method for the controller
	 * 
	 * @return mixed
	 */
	public function execute()
	{
		$task = $this->input->get('task', '');
		
		// Check if method exists
		if (!method_exists($this, $task)) {
			$this->debug('Task not found', $task);
			return $this->output($this->getResultObj('Invalid task specified.', false, 'error'));
		}
		
		// Execute the task
		return $this->$task();
	}

	/**
	 * Verify the license
	 * 
	 * @return mixed
	 */
	public function verify()
	{
		$username = $this->input->get('username', '', 'string');
		$key = $this->input->get('key', '', 'string');
		
		if (empty($username) || empty($key)) {
			$this->debug('Missing username or key');
			return $this->output($this->getResultObj('Please provide both username and license key.', false, 'error'));
		}
		
		try {
			$result = $this->verifyApiKey($username, $key);
			
			if (!$result) {
				return $this->output($this->getResultObj('Unable to verify the license. Please try again.', false, 'error'));
			}
						
			if (!isset($result->success) || $result->success !== true) {
				$message = isset($result->message) ? $result->message : 'License verification failed.';
				$this->debug('License verification failed', $message);
				return $this->output($this->getResultObj($message, false, 'error'));
			}
			
			// We need to store this into our session
			$session = $this->isJoomla4OrHigher() ? Factory::getApplication()->getSession() : Factory::getSession();
			$session->set('quix.license.status', $result->success);
			$session->set('quix.username', $username);
			$session->set('quix.key', $key);
			
			// Store the user information
			if (isset($result->data) && is_array($result->data)) {
				// Get a valid license from the response
				$license = $this->getValidLicense($result); 
				$license->id = 117;
				
				if (!$license) {
					$this->debug('No valid license found');
					return $this->output($this->getResultObj('No valid license found for Quix.', false, 'error'));
				}
				
				$session->set('quix.license', $license->name);
				$session->set('quix.id', $license->id);
				
				// Store license info in database
				$this->storeLicenseInfo($username, $key);
				
				$this->debug('License verified', [
					'username' => $username,
					'product' => $license->name,
					'id' => $license->id
				]);
				
				// Create a custom response object with an HTML field for the license ID input
				$responseObj = $this->getResultObj(
					$license->name ? 'Great! <strong>' . $license->name . '</strong> license found. Please click next to continue installation.' : 'License verified successfully!', 
					true, 
					'success'
				);
				
				// Add the HTML input field that was in the original code
				$responseObj->html = '<input data-source-license type="text" name="pid" value="' . $license->id . '">';
				
				return $this->output($responseObj);
			}
			
			$this->debug('Invalid license response format');
			return $this->output($this->getResultObj('Invalid license response format.', false, 'error'));
		} catch (\Exception $e) {
			$this->debug('License verification error', $e->getMessage());
			return $this->output($this->getResultObj('Error: ' . $e->getMessage(), false, 'error'));
		}
	}
	
	/**
	 * Store license information in the database
	 * 
	 * @param string $username
	 * @param string $authkey
	 * @return bool
	 */
	private function storeLicenseInfo($username, $authkey)
	{
		try {
			$db = Factory::getDbo();
			$query = $db->getQuery(true)
				->select('*')
				->from('#__quix_configs');
			$db->setQuery($query);
			$result = $db->loadObjectList();
			
			if (empty($result)) {
				// Insert new records
				$obj = new \stdClass();
				$obj->name = 'username';
				$obj->params = $username;
				$db->insertObject('#__quix_configs', $obj);
				
				$obj = new \stdClass();
				$obj->name = 'key';
				$obj->params = $authkey;
				$db->insertObject('#__quix_configs', $obj);
			} else {
				// Update existing records
				foreach ($result as $item) {
					if ($item->name == 'username') {
						$obj = new \stdClass();
						$obj->name = 'username';
						$obj->params = $username;
						$db->updateObject('#__quix_configs', $obj, 'name');
					}
					
					if ($item->name == 'key') {
						$obj = new \stdClass();
						$obj->name = 'key';
						$obj->params = $authkey;
						$db->updateObject('#__quix_configs', $obj, 'name');
					}
				}
			}
			
			$this->debug('License info stored in database');
			return true;
		} catch (\Exception $e) {
			$this->debug('Error storing license info', $e->getMessage());
			return false;
		}
	}
	
	/**
	 * Get valid license from the response
	 * 
	 * @param object $data
	 * @return object|false
	 */
	public function getValidLicense($data)
	{
		if (!isset($data->data) || !is_array($data->data)) {
			$this->debug('Invalid license data format');
			return false;
		}
		
		$products = $data->data;
		$quixPro = [QX_AGENCY_ID, QX_PRO_ID, QX_EXT_ID, QX_BUS_ID, QX_PRO_LT_ID];
		$hasPro = false;
		$hasFree = false;
		$proProduct = null;
		$proID = 0;
		
		foreach ($products as $product) {
			if (in_array($product->id, $quixPro, true) && ($product->has_access === true)) {
				$hasPro = true;
				$proProduct = $product;
				$proID = $product->id;
				break;
			}
			
			if ($product->id == QX_FREE_ID && $product->has_access === true) {
				$hasFree = true;
				$freeProduct = $product;
			}
		}
		
		if ($hasPro && $proProduct) {
			$this->debug('Found valid Pro license', $proProduct->name);
			return $proProduct;
		}
		
		if ($hasFree) {
			$this->debug('Found valid Free license');
			return $freeProduct;
		}
		
		$this->debug('No valid license found');
		return false;
	}
	
	/**
	 * Get release information
	 * 
	 * @return mixed
	 */
	public function getReleaseInfo()
	{
		$url = $this->getReleaseInfo();
		
		if (!$url) {
			$this->debug('Failed to get release info');
			return $this->output($this->getResultObj('Failed to get release information.', false, 'error'));
		}
		
		$this->debug('Release info retrieved', $url);
		return $this->output($this->getResultObj($url, true, 'success'));
	}

	/**
	 * Download the debug log file
	 * 
	 * This method allows users to download the iquix.log.php file from the logs directory
	 * 
	 * @return void
	 */
	public function downloadDebugLog()
	{
		try {
			$this->debug('Attempting to download debug log file');
			
			// Define the path to the log file - using JPATH_ROOT and DIRECTORY_SEPARATOR for compatibility
			$logFile = JPATH_ADMINISTRATOR . DIRECTORY_SEPARATOR . 'logs' . DIRECTORY_SEPARATOR . 'iquix.log.php';
			
			// Check if the file exists
			if (!file_exists($logFile)) {
				$this->debug('Debug log file not found', $logFile);
				return $this->output($this->getResultObj('Debug log file not found.', false, 'error'));
			}
			
			// Directly serve the file for download
			// Clear any buffered output
			while (ob_get_level()) {
				ob_end_clean();
			}
			
			// Set headers for file download
			header('Content-Description: File Transfer');
			header('Content-Type: application/octet-stream');
			header('Content-Disposition: attachment; filename="iquix_debug.log"');
			header('Content-Transfer-Encoding: binary');
			header('Expires: 0');
			header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
			header('Pragma: public');
			header('Content-Length: ' . filesize($logFile));
			
			// Output file content
			readfile($logFile);
			$this->debug('Debug log file downloaded successfully');
			exit;
		} catch (\Exception $e) {
			$this->debug('Error downloading debug log', $e->getMessage());
			return $this->output($this->getResultObj('Error: ' . $e->getMessage(), false, 'error'));
		}
	}
}