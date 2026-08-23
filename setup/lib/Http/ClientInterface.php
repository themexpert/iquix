<?php

namespace IQuix\Setup\Http;

defined('_JEXEC') or die('Unauthorized Access');

interface ClientInterface
{
    /**
     * @param array<string, string> $params appended to the query string
     *
     * @throws \RuntimeException on transport failure
     */
    public function get(string $url, array $params = []): Response;

    /**
     * @param array<string, string> $params form-encoded into the body
     *
     * @throws \RuntimeException on transport failure
     */
    public function post(string $url, array $params = []): Response;
}
