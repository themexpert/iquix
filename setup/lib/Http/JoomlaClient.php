<?php

namespace IQuix\Setup\Http;

defined('_JEXEC') or die('Unauthorized Access');

use IQuix\Setup\Config;
use IQuix\Setup\Log;
use Joomla\CMS\Http\HttpFactory;
use Joomla\Registry\Registry;

final class JoomlaClient implements ClientInterface
{
    public function __construct(private readonly Config $config)
    {
    }

    /**
     * Browser-style User-Agent to clear Cloudflare's Browser Integrity Check;
     * the real identity travels in X-Quix-Client for the server's logs.
     */
    public static function headers(Config $config): array
    {
        return [
            'User-Agent'    => Config::USER_AGENT,
            'X-Quix-Client' => 'iQuix/' . IQX_VERSION . ' (Joomla ' . JVERSION . '; ' . $config->storeUrl() . ')',
            'Accept'        => 'application/json, text/xml, */*',
        ];
    }

    public function get(string $url, array $params = []): Response
    {
        if ($params !== []) {
            $url .= (str_contains($url, '?') ? '&' : '?') . http_build_query($params);
        }

        return $this->send('get', $url, null);
    }

    public function post(string $url, array $params = []): Response
    {
        return $this->send('post', $url, http_build_query($params));
    }

    private function send(string $method, string $url, ?string $body): Response
    {
        Log::debug('HTTP ' . strtoupper($method) . ' ' . Log::redactUrl($url));

        $http    = HttpFactory::getHttp(new Registry(['timeout' => Config::API_TIMEOUT]));
        $headers = self::headers($this->config);

        try {
            if ($method === 'get') {
                $response = $http->get($url, $headers);
            } else {
                $headers['Content-Type'] = 'application/x-www-form-urlencoded';
                $response = $http->post($url, $body, $headers);
            }
        } catch (\Throwable $e) {
            throw new \RuntimeException(
                sprintf(
                    'Could not reach %s. Ask your host to allow outgoing connections to it. (%s)',
                    parse_url($url, PHP_URL_HOST) ?: $url,
                    $e->getMessage()
                ),
                0,
                $e
            );
        }

        $result = new Response((int) ($response->code ?? 0), (string) ($response->body ?? ''));

        Log::debug('HTTP response ' . $result->code);

        return $result;
    }
}
