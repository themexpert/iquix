<?php

namespace IQuix\Setup\Package;

defined('_JEXEC') or die('Unauthorized Access');

use IQuix\Setup\Log;

/**
 * Reads the sub-extension archives out of a package manifest's <files>.
 *
 * Manifest order is install order — the same order Joomla's own PackageAdapter
 * uses when the package is installed the normal way.
 */
final class ExtensionList
{
    /**
     * @return list<string>
     */
    public static function fromManifest(string $xml): array
    {
        $previous = libxml_use_internal_errors(true);
        $parsed   = simplexml_load_string($xml);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if ($parsed === false) {
            throw new \RuntimeException('The package manifest could not be parsed.');
        }

        $names = [];

        foreach ($parsed->files->file ?? [] as $file) {
            $name = trim((string) $file);

            if (!self::isSafeArchiveName($name)) {
                Log::debug('Skipping unusable manifest entry: ' . $name);

                continue;
            }

            $names[] = $name;
        }

        return $names;
    }

    /**
     * A plain zip filename and nothing else — no directory separators, no
     * traversal, no unreplaced release placeholder.
     */
    private static function isSafeArchiveName(string $name): bool
    {
        if ($name === '' || $name !== basename($name)) {
            return false;
        }

        if (str_contains($name, '..') || str_contains($name, '#')) {
            return false;
        }

        return (bool) preg_match('/^[A-Za-z0-9._-]+\.zip$/', $name);
    }
}
