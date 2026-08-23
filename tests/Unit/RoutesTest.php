<?php

namespace IQuix\Tests\Unit;

use IQuix\Setup\Routes;
use PHPUnit\Framework\TestCase;

final class RoutesTest extends TestCase
{
    public function testKnownRoutesAreAllowed(): void
    {
        $this->assertTrue(Routes::allows('license', 'verify'));
        $this->assertTrue(Routes::allows('license', 'status'));
        $this->assertTrue(Routes::allows('installation', 'download'));
        $this->assertTrue(Routes::allows('maintenance', 'cleanInstallation'));
    }

    public function testUnknownControllersAreRejected(): void
    {
        $this->assertFalse(Routes::allows('evil', 'verify'));
        $this->assertFalse(Routes::allows('', 'verify'));
        $this->assertFalse(Routes::allows('../../configuration', 'verify'));
    }

    public function testUnknownTasksAreRejected(): void
    {
        $this->assertFalse(Routes::allows('license', 'storeLicenseInfo'));
        $this->assertFalse(Routes::allows('license', 'output'));
        $this->assertFalse(Routes::allows('license', '__construct'));
        $this->assertFalse(Routes::allows('installation', ''));
    }

    public function testTheCredentialDumpingTaskIsGone(): void
    {
        foreach (array_keys(Routes::MAP) as $controller) {
            $this->assertFalse(
                Routes::allows($controller, 'getAuthInfo'),
                $controller . ' must not expose getAuthInfo'
            );
        }
    }

    public function testEveryRouteMapsToAControllerClass(): void
    {
        foreach (array_keys(Routes::MAP) as $controller) {
            $this->assertNotNull(Routes::controllerClass($controller));
        }
    }
}
