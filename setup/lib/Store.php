<?php

namespace IQuix\Setup;

defined('_JEXEC') or die('Unauthorized Access');

use Joomla\CMS\Factory;

/**
 * #__quix_configs persistence. Row names match what com_quix reads, so a
 * license activated here is already active when Quix finishes installing.
 */
final class Store implements StoreInterface
{
    private const TABLE = '#__quix_configs';

    /** @var array<string, string>|null */
    private ?array $cache = null;

    public function get(string $name, string $default = ''): string
    {
        $this->load();

        return $this->cache[$name] ?? $default;
    }

    public function has(string $name): bool
    {
        return $this->get($name) !== '';
    }

    public function set(string $name, string $value): void
    {
        $this->load();

        $db  = Factory::getDbo();
        $row = (object) ['name' => $name, 'params' => $value];

        if (array_key_exists($name, $this->cache)) {
            $db->updateObject(self::TABLE, $row, 'name');
        } else {
            $db->insertObject(self::TABLE, $row);
        }

        $this->cache[$name] = $value;
    }

    public function setMany(array $values): void
    {
        foreach ($values as $name => $value) {
            $this->set($name, (string) $value);
        }
    }

    private function load(): void
    {
        if ($this->cache !== null) {
            return;
        }

        $this->cache = [];

        $db    = Factory::getDbo();
        $query = $db->getQuery(true)
            ->select($db->quoteName(['name', 'params']))
            ->from($db->quoteName(self::TABLE));

        $db->setQuery($query);

        foreach ((array) $db->loadObjectList() as $row) {
            $this->cache[$row->name] = (string) $row->params;
        }
    }
}
