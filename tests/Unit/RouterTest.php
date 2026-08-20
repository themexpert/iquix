<?php

namespace IQuix\Tests\Unit;

use IQuix\Setup\Router;
use IQuix\Tests\Support\FakeGuard;
use PHPUnit\Framework\TestCase;

final class RouterTest extends TestCase
{
    public function testItResolvesAWhitelistedRoute(): void
    {
        $router = new Router(new FakeGuard(true, true));

        $this->assertSame('IQuix\Setup\Controller\License', $router->resolve('license', 'verify'));
    }

    public function testItRejectsAnUnknownControllerWithForbidden(): void
    {
        $router = new Router(new FakeGuard(true, true));

        try {
            $router->resolve('evil', 'verify');
            $this->fail('Expected a RuntimeException');
        } catch (\RuntimeException $e) {
            $this->assertSame(Router::E_NOT_ALLOWED, $e->getCode());
        }
    }

    public function testItRejectsAnUnknownTask(): void
    {
        $router = new Router(new FakeGuard(true, true));

        $this->expectExceptionCode(Router::E_NOT_ALLOWED);

        $router->resolve('license', 'downloadEverything');
    }

    public function testItRejectsAnUnprivilegedUser(): void
    {
        $router = new Router(new FakeGuard(false, true));

        $this->expectExceptionCode(Router::E_FORBIDDEN);

        $router->resolve('license', 'verify');
    }

    public function testItRejectsAMissingCsrfToken(): void
    {
        $router = new Router(new FakeGuard(true, false));

        $this->expectExceptionCode(Router::E_BAD_TOKEN);

        $router->resolve('license', 'verify');
    }

    public function testItChecksPrivilegeBeforeTheToken(): void
    {
        // An unprivileged user must never learn whether their token was good.
        $router = new Router(new FakeGuard(false, false));

        $this->expectExceptionCode(Router::E_FORBIDDEN);

        $router->resolve('license', 'verify');
    }
}
