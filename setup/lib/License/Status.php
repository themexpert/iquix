<?php

namespace IQuix\Setup\License;

defined('_JEXEC') or die('Unauthorized Access');

final class Status
{
    public const VALID         = 'valid';
    public const INVALID       = 'invalid';
    public const EXPIRED       = 'expired';
    public const DEACTIVATED   = 'deactivated';
    public const DISABLED      = 'disabled';
    public const SITE_INACTIVE = 'site_inactive';

    /** Statuses that mean this site is no longer entitled to Pro. */
    public const REVOKING = [self::INVALID, self::DISABLED, self::EXPIRED];
}
