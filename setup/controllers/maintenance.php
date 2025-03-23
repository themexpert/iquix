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

class iQuixControllerMaintenance extends iQuixSetupController
{
	public function __construct()
	{
		parent::__construct();
	}

	public function cleanInstallation()
	{
		// Remove installation temporary file
		JFile::delete(JPATH_ROOT . '/tmp/quix.installation');

		$result = $this->getResultObj('Updated maintenance version.', 1, 'success');

		return $this->output($result);
	}

	public function removeUpdateRecord()
	{
		$db = JFactory::getDBO();
		$query = 'DELETE FROM ' . $db->quoteName('#__updates') . ' WHERE ' . $db->quoteName('extension_id') . '=' . $db->Quote($this->getExtensionId());
		$db->setQuery($query);
		$result = $db->execute();
		if($result)
		{
			$msg = $this->getResultObj('Update record cleaned!', true, 'success');
		}
		else
		{
			$msg = $this->getResultObj('Unable to clean update record!', false, 'fail');
		}

		return $this->output($msg);
	}

	public function updateAssets()
	{
		// cache assets
		$token    = JSession::getFormToken() . '=' . 1;
		$ajax_url = 'index.php?option=com_quix&task=updateAjax&' . $token;

		try {
			$http = new JHttp();
			$str  = $http->get($ajax_url);

			if ($str->code != 200 && $str->code != 310)
			{
		        $result = false;
			}
			else
			{
				$result = true;
			}
		} catch (Exception $e) {
			// nothing to show now, lets ignore
			$result = true;		
		}

		if($result)
		{
			$msg = $this->getResultObj('Updating assets completed!!', true, 'success');
		}
		else
		{
			$msg = $this->getResultObj('Unable to update assets!', false, 'fail');
		}


		if(JDEBUG){
	    	\JLog::add("iQuix - updateAssets status: " . $result, JLog::DEBUG, 'iquix');
		}

		return $this->output($msg);
	}
}