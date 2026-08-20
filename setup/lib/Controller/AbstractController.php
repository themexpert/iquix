<?php

namespace IQuix\Setup\Controller;

defined('_JEXEC') or die('Unauthorized Access');

use IQuix\Setup\Container;
use Joomla\CMS\Factory;

abstract class AbstractController
{
    public function __construct(protected readonly Container $container)
    {
    }

    protected function input(string $name, string $default = '', string $filter = 'string'): string
    {
        return (string) Factory::getApplication()->getInput()->get($name, $default, $filter);
    }

    /**
     * The wizard JS reads {state, message}; keep that envelope.
     */
    protected function ok(string $message, array $extra = []): never
    {
        $this->send(array_merge(['state' => true, 'message' => $message], $extra));
    }

    protected function fail(string $message, array $extra = []): never
    {
        $this->send(array_merge(['state' => false, 'message' => $message], $extra));
    }

    protected function send(array $payload): never
    {
        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        header('Content-Type: application/json; charset=utf-8');
        header('X-Content-Type-Options: nosniff');

        echo json_encode($payload);

        Factory::getApplication()->close();
    }
}
