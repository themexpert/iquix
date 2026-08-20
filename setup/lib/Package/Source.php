<?php

namespace IQuix\Setup\Package;

defined('_JEXEC') or die('Unauthorized Access');

final class Source
{
    public function __construct(
        public readonly string $url,
        public readonly string $version,
        public readonly string $sha256,
        public readonly string $edition
    ) {
    }
}
