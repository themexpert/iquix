<?php

namespace IQuix\Setup;

defined('_JEXEC') or die('Unauthorized Access');

final class Router
{
    public const E_FORBIDDEN   = 403;
    public const E_BAD_TOKEN   = 419;
    public const E_NOT_ALLOWED = 404;

    public function __construct(private readonly GuardInterface $guard)
    {
    }

    /**
     * @return class-string the controller to instantiate
     *
     * @throws \RuntimeException coded with one of the E_* constants
     */
    public function resolve(string $controller, string $task): string
    {
        // Privilege first: an unprivileged user must not learn anything about
        // token validity or which routes exist.
        if (!$this->guard->isAuthorised()) {
            throw new \RuntimeException(
                'You do not have permission to install extensions on this site.',
                self::E_FORBIDDEN
            );
        }

        if (!$this->guard->hasValidToken()) {
            throw new \RuntimeException(
                'Your session has expired. Please reload this page and try again.',
                self::E_BAD_TOKEN
            );
        }

        if (!Routes::allows($controller, $task)) {
            throw new \RuntimeException('Unknown action.', self::E_NOT_ALLOWED);
        }

        $class = Routes::controllerClass($controller);

        if ($class === null || !class_exists($class)) {
            throw new \RuntimeException('Unknown action.', self::E_NOT_ALLOWED);
        }

        return $class;
    }
}
