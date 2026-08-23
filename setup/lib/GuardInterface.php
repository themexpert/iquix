<?php

namespace IQuix\Setup;

defined('_JEXEC') or die('Unauthorized Access');

interface GuardInterface
{
    /** Is the current user allowed to install extensions on this site? */
    public function isAuthorised(): bool;

    /** Did the request carry a valid Joomla CSRF token? */
    public function hasValidToken(): bool;
}
