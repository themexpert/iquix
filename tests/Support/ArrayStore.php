<?php

namespace IQuix\Tests\Support;

use IQuix\Setup\StoreInterface;

final class ArrayStore implements StoreInterface
{
    /** @var array<string, string> */
    private array $values;

    /**
     * @param array<string, string> $values
     */
    public function __construct(array $values = [])
    {
        $this->values = $values;
    }

    public function get(string $name, string $default = ''): string
    {
        return $this->values[$name] ?? $default;
    }

    public function has(string $name): bool
    {
        return isset($this->values[$name]) && $this->values[$name] !== '';
    }

    public function set(string $name, string $value): void
    {
        $this->values[$name] = $value;
    }

    public function setMany(array $values): void
    {
        foreach ($values as $name => $value) {
            $this->set($name, (string) $value);
        }
    }

    /**
     * @return array<string, string>
     */
    public function all(): array
    {
        return $this->values;
    }
}
