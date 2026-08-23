<?php

namespace IQuix\Setup;

defined('_JEXEC') or die('Unauthorized Access');

/**
 * Key/value persistence. Backed by #__quix_configs in production — the same
 * table com_quix reads its license state from.
 */
interface StoreInterface
{
    public function get(string $name, string $default = ''): string;

    public function has(string $name): bool;

    public function set(string $name, string $value): void;

    /**
     * @param array<string, string> $values
     */
    public function setMany(array $values): void;
}
