<?php

namespace IQuix\Setup;

defined('_JEXEC') or die('Unauthorized Access');

use Joomla\CMS\Factory;
use Joomla\CMS\Session\Session;

final class JoomlaGuard implements GuardInterface
{
    /**
     * Installing extensions is a Super User action, so nothing weaker than
     * core.admin on the site root will do.
     */
    public function isAuthorised(): bool
    {
        $user = Factory::getApplication()->getIdentity();

        return $user !== null && $user->authorise('core.admin');
    }

    /**
     * 'request' checks both the query string and the POST body, so GET-style
     * links such as the debug log download are covered too.
     */
    public function hasValidToken(): bool
    {
        return Session::checkToken('request');
    }
}
