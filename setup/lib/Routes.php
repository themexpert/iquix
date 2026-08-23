<?php

namespace IQuix\Setup;

defined('_JEXEC') or die('Unauthorized Access');

/**
 * The complete list of reachable actions. Anything not named here is a 403.
 *
 * Replaces the previous dispatcher, which required a filename built from
 * request input and then invoked any method that happened to exist on the
 * resulting object.
 */
final class Routes
{
    /** @var array<string, list<string>> */
    public const MAP = [
        'license' => [
            'status',
            'verify',
            'useFree',
            'downloadDebugLog',
        ],
        'installation' => [
            'checkPackageExtension',
            'download',
            'cleanCache',
            'installExtensions',
            'syncDb',
            'installPost',
        ],
        'maintenance' => [
            'cleanInstallation',
            'removeUpdateRecord',
            'updateAssets',
        ],
        'update' => [
            'updateScript',
            'updateJoomlaUpdater',
        ],
    ];

    /** @var array<string, string> */
    private const CLASSES = [
        'license'      => Controller\License::class,
        'installation' => Controller\Installation::class,
        'maintenance'  => Controller\Maintenance::class,
        'update'       => Controller\Update::class,
    ];

    public static function allows(string $controller, string $task): bool
    {
        return isset(self::MAP[$controller]) && in_array($task, self::MAP[$controller], true);
    }

    public static function controllerClass(string $controller): ?string
    {
        return self::CLASSES[$controller] ?? null;
    }
}
