<?php

require __DIR__ . '/../vendor/autoload.php';

// The library guards on _JEXEC the way Joomla extensions do. Tests satisfy
// that guard so the files can be loaded standalone.
if (!defined('_JEXEC')) {
    define('_JEXEC', 1);
}
