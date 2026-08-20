<?php

namespace IQuix\Tests\Unit\Http;

use IQuix\Setup\Http\Response;
use PHPUnit\Framework\TestCase;

final class ResponseTest extends TestCase
{
    public function testIsOkAcceptsTwoHundredAndThreeTen(): void
    {
        $this->assertTrue((new Response(200, ''))->isOk());
        $this->assertTrue((new Response(310, ''))->isOk());
        $this->assertFalse((new Response(403, ''))->isOk());
        $this->assertFalse((new Response(0, ''))->isOk());
    }

    public function testJsonDecodesAnObjectBody(): void
    {
        $response = new Response(200, '{"success":true,"status":"valid"}');

        $this->assertSame('valid', $response->json()->status);
    }

    public function testJsonReturnsNullForNonJsonBodies(): void
    {
        $this->assertNull((new Response(403, 'Missing license key.'))->json());
        $this->assertNull((new Response(200, ''))->json());
        $this->assertNull((new Response(200, '[1,2,3]'))->json());
    }
}
