<?php

namespace IQuix\Setup\Http;

defined('_JEXEC') or die('Unauthorized Access');

final class Response
{
    public function __construct(
        public readonly int $code,
        public readonly string $body
    ) {
    }

    /**
     * 310 is included because the license server has historically answered
     * with it on redirect-style responses that still carry a usable body.
     */
    public function isOk(): bool
    {
        return $this->code === 200 || $this->code === 310;
    }

    /**
     * Decoded JSON object, or null when the body is not a JSON object —
     * which is how a WAF block or a plain-text server error arrives.
     */
    public function json(): ?object
    {
        $decoded = json_decode($this->body);

        return is_object($decoded) ? $decoded : null;
    }
}
