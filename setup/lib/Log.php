<?php

namespace IQuix\Setup;

defined('_JEXEC') or die('Unauthorized Access');

use Joomla\CMS\Log\Log as JoomlaLog;

/**
 * Logging with license credentials masked. No key may ever reach the log,
 * including inside a URL query string.
 */
final class Log
{
    public const CATEGORY = 'iquix';

    private const SENSITIVE = ['license_key', 'activation_hash', 'key', 'authkey'];

    private static bool $registered = false;

    /**
     * Mask a credential, keeping the first and last four characters of
     * anything long enough for that to be safe. Matches QuixHelperLicense.
     */
    public static function maskKey(string $key): string
    {
        $len = strlen($key);

        if ($len <= 8) {
            return str_repeat('*', $len);
        }

        return substr($key, 0, 4) . str_repeat('*', $len - 8) . substr($key, -4);
    }

    /**
     * Mask every sensitive value in an array, leaving the rest untouched.
     */
    public static function redact(array $data): array
    {
        foreach (self::SENSITIVE as $name) {
            if (!empty($data[$name]) && is_string($data[$name])) {
                $data[$name] = self::maskKey($data[$name]);
            }
        }

        return $data;
    }

    /**
     * Mask credentials carried in a URL query string.
     */
    public static function redactUrl(string $url): string
    {
        $parts = parse_url($url);

        if (empty($parts['query'])) {
            return $url;
        }

        parse_str($parts['query'], $params);
        $params = self::redact($params);

        $rebuilt = $parts['scheme'] . '://' . $parts['host']
            . ($parts['path'] ?? '')
            . '?' . http_build_query($params);

        return $rebuilt;
    }

    /**
     * Write a debug entry. Silent unless Joomla debug mode is on.
     */
    public static function debug(string $message, array $context = []): void
    {
        if (!defined('JDEBUG') || !JDEBUG || !class_exists(JoomlaLog::class)) {
            return;
        }

        if (!self::$registered) {
            JoomlaLog::addLogger(['text_file' => 'iquix.log.php'], JoomlaLog::ALL, [self::CATEGORY]);
            self::$registered = true;
        }

        if ($context !== []) {
            $message .= ' ' . json_encode(self::redact($context));
        }

        JoomlaLog::add($message, JoomlaLog::DEBUG, self::CATEGORY);
    }
}
