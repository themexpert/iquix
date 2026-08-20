<?php

namespace IQuix\Tests\Support;

use IQuix\Setup\GuardInterface;

final class FakeGuard implements GuardInterface
{
    public function __construct(
        private readonly bool $authorised,
        private readonly bool $validToken
    ) {
    }

    public function isAuthorised(): bool
    {
        return $this->authorised;
    }

    public function hasValidToken(): bool
    {
        return $this->validToken;
    }
}
