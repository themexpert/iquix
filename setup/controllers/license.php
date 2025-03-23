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

class iQuixControllerLicense extends iQuixSetupController
{
	/**
	 * Verifies the user's license
	 *
	 * @since	2.1.0
	 * @access	public
	 */
	public function verify()
	{
		$input = JFactory::getApplication()->input;
		$session = JFactory::getSession();
		$username = $input->get('username', '', 'string');
		$key = $input->get('key', '', 'string');

		if(JDEBUG){
		    \JLog::add("iQuix - Verifing credentials, Username: `$username` and Auth Key: `$key`", JLog::DEBUG, 'iquix');
		}

		// keep the credentials
		$session->set('quix.username', $username);
		$session->set('quix.key', $key);

		// Verify the key
		$result = new stdClass();
		$response = $this->verifyApiKey($username, $key);
				
		if ($response === false or !$response->success) {
			$result->state = 400;
			$result->message = JText::_('Unable to verify your license or your hosting provider has blocked outgoing connections. Details: ' . $response->message);

			if(JDEBUG){
			    \JLog::add("iQuix - Verification faild. Message: $response->message", JLog::ERROR, 'iquix');
			}
			
			return $this->output($result);
		}

		// store config
		$this->updateConfig($username, $key);
		if(JDEBUG){
		    \JLog::add("iQuix - Config updated to #__quix_configs", JLog::INFO, 'iquix');
		}

		$validLicense = $this->getValidLicense($response);
		if(JDEBUG){
		    \JLog::add(json_encode($validLicense), JLog::DEBUG, 'iquix');
		}
		// json_encode(['hasPro' => true, 'hasFree' => false, 'hasLicense' => true, 'name' => $proProduct, 'id' => $proID]);
		
		if (!$validLicense['hasLicense']) {
			$result->state = 403;
			$result->message = JText::_('No valid Quix license found! Chances are, you\'ve entered wrong credentials or you did not purchase Quix yet.');
			\JLog::add("iQuix - $result->message", JLog::WARNING, 'iquix');
			return $this->output($result);
		}

		if ($validLicense['hasLicense'] && $validLicense['hasPro']) {
			$result->state = 200;
			$response->html = '<input data-source-license type="text" name="pid" value="'.$validLicense['id'].'">';
			$result->message = JText::_('Great! <strong>' . $validLicense['name'] . '</strong> license found. Please click next to continue installation.');
			\JLog::add("iQuix - $result->message", JLog::INFO, 'iquix');
			return $this->output($result);
		}
		
		if ($validLicense['hasLicense'] && $validLicense['hasFree']) {
			$result->state = 200;
			$response->html = '<input data-source-license type="text" name="pid" value="'.QX_FREE_ID.'">';
			$result->message = JText::_('Quix free license found. Please click next to continue installation.');
			\JLog::add("iQuix - $result->message", JLog::INFO, 'iquix');
			return $this->output($result);
		}
	}

	/**
	 * Saves a configuration item
	 *
	 * @since	1.0
	 * @access	public
	 */
	public function updateConfig($username, $authkey)
	{
		// dont allow empty request
		if(empty($username) or empty($authkey))
		{
			return;
		}

		$db = JFactory::getDbo();
		$query = $db->getQuery(true);
		$query->select('*')
			  ->from('#__quix_configs');
		$db->setQuery($query);
		$result = $db->loadObjectList();
		if(!count($result)){
			// set all, incert
			$obj = new stdClass();
			$obj->name = 'username';
			$obj->params =	$username;
			// Insert the object into the user obj table.
			$result = JFactory::getDbo()->insertObject('#__quix_configs', $obj);
			
			$obj = new stdClass();
			$obj->name = 'key';
			$obj->params= $authkey;

			// Insert the object into the user obj table.
			$result = JFactory::getDbo()->insertObject('#__quix_configs', $obj);
		}
		else
		{
			foreach ($result as $key => $item) {
				if($item->name == 'username'){
					// Create an object for the record we are going to update.
					$obj = new stdClass();
					$obj->name = 'username';
					$obj->params = $username;

					// Update their details in the users table using id as the primary key.
					$result = JFactory::getDbo()->updateObject('#__quix_configs', $obj, 'name');
				}
				if($item->name == 'key'){
					// Create an object for the record we are going to update.
					$obj = new stdClass();
					$obj->name = 'key';
					$obj->params = $authkey;

					// Update their details in the users table using id as the primary key.
					$result = JFactory::getDbo()->updateObject('#__quix_configs', $obj, 'name');
				}

			}
		}

		return true;

	}

	public function getValidLicense($data)
	{
		$session = JFactory::getSession();		
		$products = $data->data;
		$quixPro = [QX_AGENCY_ID, QX_PRO_ID, QX_EXT_ID, QX_BUS_ID, QX_PRO_LT_ID];
		$hasPro = false;
		$hasFree = false;
		$proProduct = '';
		$proID = 0;

		foreach ($products as $key => $product) 
		{
			if(in_array($product->id, $quixPro, true) && ($product->has_access === true))
			{
				$hasPro = true;
				$proProduct = $product->name;
				$proID = $product->id;

				break;
			}
			
			if($product->id == QX_FREE_ID && $product->has_access === true)
			{
				$hasFree = true;
				$session->set('quix.hasFree', true);
			}
		}
		
		// now return result
		if($hasPro)
		{
			$session->set('quix.id', $proID);
			$session->set('quix.hasLicense', true);
			return array('hasPro' => true, 'hasFree' => $hasFree, 'hasLicense' => true, 'name' => $proProduct, 'id' => $proID);
		}
		elseif($hasFree)
		{
			$session->set('quix.id', QX_FREE_ID);
			$session->set('quix.hasLicense', true);
			return array('hasPro' => false, 'hasFree' => true, 'hasLicense' => true);
		}
		else
		{
			$session->set('quix.hasLicense', false);
			return array('hasPro' => false, 'hasFree' => false, 'hasLicense' => false);
		}
	}
}