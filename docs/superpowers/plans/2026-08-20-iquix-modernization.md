# iQuix Installer Modernization Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Rebuild the `com_iquix` setup layer so it installs Quix from the FluentCart server on Joomla 4, 5 and 6, without the security holes the current version ships.

**Architecture:** Keep the outer shell — the `com_iquix` manifest, the four-step wizard, and the `controller=<name>&task=<name>` AJAX contract the existing JS speaks. Replace everything behind it with a new `setup/lib/` of small single-responsibility classes under the `IQuix\Setup` namespace, dispatched through a whitelisting `Router` that enforces ACL and CSRF. Joomla-touching work (HTTP, database, installer) sits behind narrow seams so the logic is unit-testable without booting Joomla.

**Tech Stack:** PHP 8.1+ (dev machine runs 8.4), Joomla 4/5/6 CMS APIs, `Joomla\Filesystem` and `Joomla\Archive` framework packages, PHPUnit 11 (new dev dependency), plain jQuery in the wizard views.

**Spec:** `docs/superpowers/specs/2026-08-20-iquix-modernization-design.md`

## Global Constraints

- **Joomla 4, 5 and 6 only.** No Joomla 3 compatibility branches, no `J*` prefixed classes (`JFile`, `JFolder`, `JText`, `JFactory`, `JLog`, `JURI`, `JRequest`, `JUpdate`, `JUpdater`, `JModelLegacy`), no `JPATH_PLATFORM`. Joomla 6 ships no `libraries/classmap.php`, so these resolve to nothing and fail silently.
- **Filesystem access uses `Joomla\Filesystem\*`**, never `Joomla\CMS\Filesystem\*` — the latter does not exist in Joomla 6.
- **License server:** `https://my.converslabs.com`, license API at `/?fluent-cart=<action>`.
- **Pro update XML:** `https://my.converslabs.com/?fcdc_joomla=update&pid=116`
- **Free update XML:** `https://raw.githubusercontent.com/themexpert/quix-free/main/jed.xml`
- **`item_id` is always `116`.** This is the legacy DigiCom pid; the license server translates it to FluentCart product 662 internally. Never send 662 from the client.
- **No `themexpert.com` API endpoint may remain** anywhere in the codebase when the plan is complete, including the `#__update_sites` row the installer writes. The GitHub-hosted iQuix self-update manifest (`raw.githubusercontent.com/themexpert/iquix/master/mainfest.xml`) is not a ThemeXpert API and stays.
- **License state lives in `#__quix_configs`** under exactly the row names `com_quix` reads: `license_key`, `activation_hash`, `license_status`, `license_expires`, `license_checked`, `activated`. The override rows `store_url` and `item_id` are honoured. Reference implementation: `QuixHelperLicense` in the Quix repo at `src/com_quix/admin/helpers/license.php`.
- **No database backup step.** Backups happen before upgrade, outside this tool.
- **Every license key is masked in log output.** No key may appear in a logged URL.
- **Namespace:** `IQuix\Setup\…`, PSR-4 against `setup/lib/`.
- **Commit format:** `<type>(<scope>): <subject>` — imperative, lowercase, no trailing period.

---

### Task 1: Test harness, autoloader, and masking log

**Files:**
- Create: `composer.json`
- Create: `phpunit.xml`
- Create: `setup/lib/autoload.php`
- Create: `setup/lib/Log.php`
- Create: `tests/bootstrap.php`
- Test: `tests/Unit/LogTest.php`
- Modify: `.gitignore`

**Interfaces:**
- Consumes: nothing.
- Produces:
  - `IQuix\Setup\Log::maskKey(string $key): string`
  - `IQuix\Setup\Log::redact(array $data): array`
  - `IQuix\Setup\Log::redactUrl(string $url): string`
  - An autoloader at `setup/lib/autoload.php` mapping `IQuix\Setup\` to `setup/lib/`.

- [ ] **Step 1: Create the composer manifest**

`composer.json`:

```json
{
    "name": "themexpert/iquix",
    "description": "Quix installer for Joomla sites with upload limits",
    "type": "joomla-component",
    "license": "GPL-2.0-or-later",
    "require": {
        "php": ">=8.1"
    },
    "require-dev": {
        "phpunit/phpunit": "^11.0"
    },
    "autoload": {
        "psr-4": {
            "IQuix\\Setup\\": "setup/lib/"
        }
    },
    "autoload-dev": {
        "psr-4": {
            "IQuix\\Tests\\": "tests/"
        }
    },
    "scripts": {
        "test": "phpunit"
    },
    "config": {
        "sort-packages": true
    }
}
```

- [ ] **Step 2: Create the PHPUnit config**

`phpunit.xml`:

```xml
<?xml version="1.0" encoding="UTF-8"?>
<phpunit xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
         xsi:noNamespaceSchemaLocation="vendor/phpunit/phpunit/phpunit.xsd"
         bootstrap="tests/bootstrap.php"
         colors="true"
         cacheDirectory=".phpunit.cache">
    <testsuites>
        <testsuite name="unit">
            <directory>tests/Unit</directory>
        </testsuite>
    </testsuites>
</phpunit>
```

- [ ] **Step 3: Create the test bootstrap**

Unit tests must run without a Joomla installation. `tests/bootstrap.php` loads Composer's autoloader and defines only the constants the library reads at parse time.

```php
<?php

require __DIR__ . '/../vendor/autoload.php';

// The library guards on _JEXEC the way Joomla extensions do. Tests satisfy
// that guard so the files can be loaded standalone.
if (!defined('_JEXEC')) {
    define('_JEXEC', 1);
}
```

- [ ] **Step 4: Create the runtime autoloader**

Composer's `vendor/` is not shipped inside the installed component, so the extension registers its own autoloader. `setup/lib/autoload.php`:

```php
<?php

defined('_JEXEC') or die('Unauthorized Access');

spl_autoload_register(static function ($class) {
    $prefix = 'IQuix\\Setup\\';

    if (strncmp($class, $prefix, strlen($prefix)) !== 0) {
        return;
    }

    $relative = substr($class, strlen($prefix));
    $path     = __DIR__ . '/' . str_replace('\\', '/', $relative) . '.php';

    if (is_file($path)) {
        require_once $path;
    }
});
```

- [ ] **Step 5: Ignore build artefacts**

Append to `.gitignore`:

```
# Composer / PHPUnit
/vendor/
/.phpunit.cache/
composer.lock
```

- [ ] **Step 6: Install dev dependencies**

Run: `composer install`
Expected: `vendor/bin/phpunit` exists.

- [ ] **Step 7: Write the failing test**

`tests/Unit/LogTest.php`:

```php
<?php

namespace IQuix\Tests\Unit;

use IQuix\Setup\Log;
use PHPUnit\Framework\TestCase;

final class LogTest extends TestCase
{
    public function testMaskKeyStarsOutShortKeysEntirely(): void
    {
        $this->assertSame('********', Log::maskKey('abcdefgh'));
        $this->assertSame('***', Log::maskKey('abc'));
        $this->assertSame('', Log::maskKey(''));
    }

    public function testMaskKeyKeepsFirstAndLastFourOfLongKeys(): void
    {
        $this->assertSame('abcd*****wxyz', Log::maskKey('abcde12345wxyz'));
    }

    public function testRedactMasksSensitiveKeysAndLeavesOthers(): void
    {
        $redacted = Log::redact([
            'license_key'     => 'abcde12345wxyz',
            'activation_hash' => 'hhhhhiiiiijjjjj',
            'item_id'         => '116',
        ]);

        $this->assertSame('abcd*****wxyz', $redacted['license_key']);
        $this->assertSame('hhhh*******jjjj', $redacted['activation_hash']);
        $this->assertSame('116', $redacted['item_id']);
    }

    public function testRedactLeavesEmptySensitiveValuesAlone(): void
    {
        $this->assertSame(['license_key' => ''], Log::redact(['license_key' => '']));
    }

    public function testRedactUrlMasksCredentialsInQueryString(): void
    {
        $url = Log::redactUrl(
            'https://my.converslabs.com/?fcdc_joomla=download&pid=116&license_key=abcde12345wxyz'
        );

        $this->assertStringNotContainsString('abcde12345wxyz', $url);
        $this->assertStringContainsString('license_key=abcd%2A%2A%2A%2A%2Awxyz', $url);
        $this->assertStringContainsString('pid=116', $url);
    }

    public function testRedactUrlLeavesUrlsWithoutQueryStringUnchanged(): void
    {
        $url = 'https://my.converslabs.com/';

        $this->assertSame($url, Log::redactUrl($url));
    }
}
```

- [ ] **Step 8: Run the test to verify it fails**

Run: `vendor/bin/phpunit --testsuite unit`
Expected: FAIL — `Class "IQuix\Setup\Log" not found`.

- [ ] **Step 9: Write the implementation**

`setup/lib/Log.php`:

```php
<?php

namespace IQuix\Setup;

defined('_JEXEC') or die('Unauthorized Access');

use Joomla\CMS\Log\Log as JoomlaLog;

/**
 * Logging with license credentials masked. No key may ever reach the log,
 * including inside a URL query string.
 */
final class Log
{
    public const CATEGORY = 'iquix';

    private const SENSITIVE = ['license_key', 'activation_hash', 'key', 'authkey'];

    private static bool $registered = false;

    /**
     * Mask a credential, keeping the first and last four characters of
     * anything long enough for that to be safe. Matches QuixHelperLicense.
     */
    public static function maskKey(string $key): string
    {
        $len = strlen($key);

        if ($len <= 8) {
            return str_repeat('*', $len);
        }

        return substr($key, 0, 4) . str_repeat('*', $len - 8) . substr($key, -4);
    }

    /**
     * Mask every sensitive value in an array, leaving the rest untouched.
     */
    public static function redact(array $data): array
    {
        foreach (self::SENSITIVE as $name) {
            if (!empty($data[$name]) && is_string($data[$name])) {
                $data[$name] = self::maskKey($data[$name]);
            }
        }

        return $data;
    }

    /**
     * Mask credentials carried in a URL query string.
     */
    public static function redactUrl(string $url): string
    {
        $parts = parse_url($url);

        if (empty($parts['query'])) {
            return $url;
        }

        parse_str($parts['query'], $params);
        $params = self::redact($params);

        $rebuilt = $parts['scheme'] . '://' . $parts['host']
            . ($parts['path'] ?? '')
            . '?' . http_build_query($params);

        return $rebuilt;
    }

    /**
     * Write a debug entry. Silent unless Joomla debug mode is on.
     */
    public static function debug(string $message, array $context = []): void
    {
        if (!defined('JDEBUG') || !JDEBUG || !class_exists(JoomlaLog::class)) {
            return;
        }

        if (!self::$registered) {
            JoomlaLog::addLogger(['text_file' => 'iquix.log.php'], JoomlaLog::ALL, [self::CATEGORY]);
            self::$registered = true;
        }

        if ($context !== []) {
            $message .= ' ' . json_encode(self::redact($context));
        }

        JoomlaLog::add($message, JoomlaLog::DEBUG, self::CATEGORY);
    }
}
```

- [ ] **Step 10: Run the test to verify it passes**

Run: `vendor/bin/phpunit --testsuite unit`
Expected: PASS, 6 tests.

- [ ] **Step 11: Commit**

```bash
git add composer.json phpunit.xml .gitignore setup/lib/autoload.php setup/lib/Log.php tests/
git commit -m "test(setup): add phpunit harness and masking log"
```

---

### Task 2: Configuration store

**Files:**
- Create: `setup/lib/StoreInterface.php`
- Create: `setup/lib/Store.php`
- Create: `tests/Support/ArrayStore.php`
- Test: `tests/Unit/ArrayStoreTest.php`

**Interfaces:**
- Consumes: the autoloader from Task 1.
- Produces:
  - `IQuix\Setup\StoreInterface` with `get(string $name, string $default = ''): string`, `set(string $name, string $value): void`, `setMany(array $values): void`, `has(string $name): bool`
  - `IQuix\Setup\Store` — the `#__quix_configs` implementation
  - `IQuix\Tests\Support\ArrayStore` — in-memory test double

Every later task that needs persistence takes a `StoreInterface`, never a database handle.

- [ ] **Step 1: Write the interface**

`setup/lib/StoreInterface.php`:

```php
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
```

- [ ] **Step 2: Write the failing test for the in-memory double**

`tests/Unit/ArrayStoreTest.php`:

```php
<?php

namespace IQuix\Tests\Unit;

use IQuix\Setup\StoreInterface;
use IQuix\Tests\Support\ArrayStore;
use PHPUnit\Framework\TestCase;

final class ArrayStoreTest extends TestCase
{
    public function testItImplementsTheStoreInterface(): void
    {
        $this->assertInstanceOf(StoreInterface::class, new ArrayStore());
    }

    public function testGetReturnsTheDefaultForMissingNames(): void
    {
        $store = new ArrayStore();

        $this->assertSame('', $store->get('license_key'));
        $this->assertSame('fallback', $store->get('license_key', 'fallback'));
    }

    public function testSetThenGetRoundTrips(): void
    {
        $store = new ArrayStore();
        $store->set('license_key', 'abc123');

        $this->assertSame('abc123', $store->get('license_key'));
        $this->assertTrue($store->has('license_key'));
    }

    public function testHasIsFalseForAnEmptyStoredValue(): void
    {
        $store = new ArrayStore(['license_key' => '']);

        $this->assertFalse($store->has('license_key'));
    }

    public function testSetManyWritesEveryPair(): void
    {
        $store = new ArrayStore();
        $store->setMany(['license_key' => 'k', 'activated' => '1']);

        $this->assertSame('k', $store->get('license_key'));
        $this->assertSame('1', $store->get('activated'));
    }
}
```

- [ ] **Step 3: Run the test to verify it fails**

Run: `vendor/bin/phpunit --testsuite unit`
Expected: FAIL — `Class "IQuix\Tests\Support\ArrayStore" not found`.

- [ ] **Step 4: Write the in-memory double**

`tests/Support/ArrayStore.php`:

```php
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
```

- [ ] **Step 5: Run the test to verify it passes**

Run: `vendor/bin/phpunit --testsuite unit`
Expected: PASS.

- [ ] **Step 6: Write the database-backed implementation**

Not unit-tested — it is a thin wrapper over the Joomla database and is exercised by the real-install verification in Task 14. `setup/lib/Store.php`:

```php
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
```

- [ ] **Step 7: Run the tests**

Run: `vendor/bin/phpunit --testsuite unit`
Expected: PASS.

- [ ] **Step 8: Commit**

```bash
git add setup/lib/StoreInterface.php setup/lib/Store.php tests/Support/ArrayStore.php tests/Unit/ArrayStoreTest.php
git commit -m "feat(setup): add quix_configs key value store"
```

---

### Task 3: Endpoint configuration

**Files:**
- Create: `setup/lib/Config.php`
- Test: `tests/Unit/ConfigTest.php`

**Interfaces:**
- Consumes: `IQuix\Setup\StoreInterface`, `IQuix\Tests\Support\ArrayStore`.
- Produces: `IQuix\Setup\Config` with
  - `__construct(StoreInterface $store)`
  - `storeUrl(): string`
  - `itemId(): string`
  - `licenseUrl(string $action): string`
  - `proUpdateXmlUrl(): string`
  - `freeUpdateXmlUrl(): string`
  - `selfUpdateXmlUrl(): string`
  - constants `DOWNLOAD_TIMEOUT`, `API_TIMEOUT`, `MAX_PACKAGE_BYTES`, `UPDATE_SITE_NAME`, `USER_AGENT`

Every URL in the codebase comes from here. No other file may contain a hostname.

- [ ] **Step 1: Write the failing test**

`tests/Unit/ConfigTest.php`:

```php
<?php

namespace IQuix\Tests\Unit;

use IQuix\Setup\Config;
use IQuix\Tests\Support\ArrayStore;
use PHPUnit\Framework\TestCase;

final class ConfigTest extends TestCase
{
    public function testDefaultStoreUrlIsTheFluentCartServer(): void
    {
        $config = new Config(new ArrayStore());

        $this->assertSame('https://my.converslabs.com', $config->storeUrl());
    }

    public function testStoreUrlOverrideIsHonouredAndTrailingSlashStripped(): void
    {
        $config = new Config(new ArrayStore(['store_url' => 'http://converslab.test/']));

        $this->assertSame('http://converslab.test', $config->storeUrl());
    }

    public function testDefaultItemIdIsTheLegacyDigicomPid(): void
    {
        $config = new Config(new ArrayStore());

        $this->assertSame('116', $config->itemId());
    }

    public function testItemIdOverrideIsHonoured(): void
    {
        $config = new Config(new ArrayStore(['item_id' => '662']));

        $this->assertSame('662', $config->itemId());
    }

    public function testLicenseUrlUsesTheFluentCartActionParameter(): void
    {
        $config = new Config(new ArrayStore());

        $this->assertSame(
            'https://my.converslabs.com/?fluent-cart=activate_license',
            $config->licenseUrl('activate_license')
        );
    }

    public function testProUpdateXmlUrlCarriesTheItemId(): void
    {
        $config = new Config(new ArrayStore());

        $this->assertSame(
            'https://my.converslabs.com/?fcdc_joomla=update&pid=116',
            $config->proUpdateXmlUrl()
        );
    }

    public function testProUpdateXmlUrlFollowsTheStoreUrlOverride(): void
    {
        $config = new Config(new ArrayStore(['store_url' => 'http://converslab.test']));

        $this->assertSame(
            'http://converslab.test/?fcdc_joomla=update&pid=116',
            $config->proUpdateXmlUrl()
        );
    }

    public function testFreeUpdateXmlUrlIsThePublicGithubManifest(): void
    {
        $config = new Config(new ArrayStore());

        $this->assertSame(
            'https://raw.githubusercontent.com/themexpert/quix-free/main/jed.xml',
            $config->freeUpdateXmlUrl()
        );
    }

    public function testNoEndpointPointsAtTheRetiredThemexpertApi(): void
    {
        $config = new Config(new ArrayStore());

        $urls = [
            $config->storeUrl(),
            $config->licenseUrl('check_license'),
            $config->proUpdateXmlUrl(),
            $config->freeUpdateXmlUrl(),
            $config->selfUpdateXmlUrl(),
        ];

        foreach ($urls as $url) {
            $this->assertStringNotContainsString('themexpert.com', $url);
            $this->assertStringNotContainsString('com_digicom', $url);
        }
    }
}
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `vendor/bin/phpunit --testsuite unit`
Expected: FAIL — `Class "IQuix\Setup\Config" not found`.

- [ ] **Step 3: Write the implementation**

`setup/lib/Config.php`:

```php
<?php

namespace IQuix\Setup;

defined('_JEXEC') or die('Unauthorized Access');

/**
 * Every endpoint, product id and limit the installer uses. No other file in
 * this extension may contain a hostname.
 */
final class Config
{
    /** FluentCart license server. The public API lives at /?fluent-cart=<action>. */
    public const STORE_URL = 'https://my.converslabs.com';

    /**
     * Legacy DigiCom product id, sent as item_id. The license server maps it
     * to FluentCart product 662 internally — do not send 662 from here.
     */
    public const ITEM_ID = '116';

    /** Public manifest for the free edition. Carries a sha256 we verify. */
    public const FREE_UPDATE_XML = 'https://raw.githubusercontent.com/themexpert/quix-free/main/jed.xml';

    /** iQuix's own update manifest. GitHub, not a ThemeXpert API. */
    public const SELF_UPDATE_XML = 'https://raw.githubusercontent.com/themexpert/iquix/master/mainfest.xml';

    public const UPDATE_SITE_NAME = 'Quix Update Site';

    public const API_TIMEOUT = 20;

    public const DOWNLOAD_TIMEOUT = 300;

    /** Refuse a package larger than this. Quix is well under 100MB. */
    public const MAX_PACKAGE_BYTES = 209715200;

    /**
     * Cloudflare's Browser Integrity Check 403s unrecognised agents at the
     * edge, and an IP allow rule does not bypass that layer. Real client
     * identity travels in the X-Quix-Client header instead.
     */
    public const USER_AGENT = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0.0.0 Safari/537.36';

    public function __construct(private readonly StoreInterface $store)
    {
    }

    public function storeUrl(): string
    {
        $override = $this->store->get('store_url');

        return rtrim($override !== '' ? $override : self::STORE_URL, '/');
    }

    public function itemId(): string
    {
        $override = $this->store->get('item_id');

        return $override !== '' ? $override : self::ITEM_ID;
    }

    public function licenseUrl(string $action): string
    {
        return $this->storeUrl() . '/?fluent-cart=' . $action;
    }

    public function proUpdateXmlUrl(): string
    {
        return $this->storeUrl() . '/?fcdc_joomla=update&pid=' . $this->itemId();
    }

    public function freeUpdateXmlUrl(): string
    {
        return self::FREE_UPDATE_XML;
    }

    public function selfUpdateXmlUrl(): string
    {
        return self::SELF_UPDATE_XML;
    }
}
```

- [ ] **Step 4: Run the test to verify it passes**

Run: `vendor/bin/phpunit --testsuite unit`
Expected: PASS, 9 tests in this file.

- [ ] **Step 5: Commit**

```bash
git add setup/lib/Config.php tests/Unit/ConfigTest.php
git commit -m "feat(setup): centralise fluentcart endpoints in config"
```

---

### Task 4: HTTP seam

**Files:**
- Create: `setup/lib/Http/Response.php`
- Create: `setup/lib/Http/ClientInterface.php`
- Create: `setup/lib/Http/JoomlaClient.php`
- Create: `tests/Support/FakeHttpClient.php`
- Test: `tests/Unit/Http/ResponseTest.php`
- Test: `tests/Unit/Http/FakeHttpClientTest.php`

**Interfaces:**
- Consumes: `IQuix\Setup\Config`.
- Produces:
  - `IQuix\Setup\Http\Response` — readonly `int $code`, `string $body`; `isOk(): bool`, `json(): ?object`
  - `IQuix\Setup\Http\ClientInterface` — `get(string $url, array $params = []): Response`, `post(string $url, array $params = []): Response`
  - `IQuix\Setup\Http\JoomlaClient` — `__construct(Config $config)`, plus `public static function headers(Config $config): array`
  - `IQuix\Tests\Support\FakeHttpClient` — `__construct(array $responses)`, `lastUrl(): string`, `lastParams(): array`, `requests(): array`

- [ ] **Step 1: Write the failing tests**

`tests/Unit/Http/ResponseTest.php`:

```php
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
```

`tests/Unit/Http/FakeHttpClientTest.php`:

```php
<?php

namespace IQuix\Tests\Unit\Http;

use IQuix\Setup\Http\ClientInterface;
use IQuix\Setup\Http\Response;
use IQuix\Tests\Support\FakeHttpClient;
use PHPUnit\Framework\TestCase;

final class FakeHttpClientTest extends TestCase
{
    public function testItImplementsTheClientInterface(): void
    {
        $this->assertInstanceOf(ClientInterface::class, new FakeHttpClient([]));
    }

    public function testItReturnsQueuedResponsesInOrder(): void
    {
        $client = new FakeHttpClient([
            new Response(200, 'first'),
            new Response(403, 'second'),
        ]);

        $this->assertSame('first', $client->get('https://example.test')->body);
        $this->assertSame('second', $client->post('https://example.test')->body);
    }

    public function testItRecordsTheUrlAndParametersOfEachCall(): void
    {
        $client = new FakeHttpClient([new Response(200, 'ok')]);
        $client->post('https://example.test/?a=1', ['license_key' => 'k']);

        $this->assertSame('https://example.test/?a=1', $client->lastUrl());
        $this->assertSame(['license_key' => 'k'], $client->lastParams());
        $this->assertCount(1, $client->requests());
    }

    public function testItFailsLoudlyWhenNoResponseIsQueued(): void
    {
        $this->expectException(\RuntimeException::class);

        (new FakeHttpClient([]))->get('https://example.test');
    }
}
```

- [ ] **Step 2: Run the tests to verify they fail**

Run: `vendor/bin/phpunit --testsuite unit`
Expected: FAIL — `Class "IQuix\Setup\Http\Response" not found`.

- [ ] **Step 3: Write the response value object**

`setup/lib/Http/Response.php`:

```php
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
```

- [ ] **Step 4: Write the client interface**

`setup/lib/Http/ClientInterface.php`:

```php
<?php

namespace IQuix\Setup\Http;

defined('_JEXEC') or die('Unauthorized Access');

interface ClientInterface
{
    /**
     * @param array<string, string> $params appended to the query string
     *
     * @throws \RuntimeException on transport failure
     */
    public function get(string $url, array $params = []): Response;

    /**
     * @param array<string, string> $params form-encoded into the body
     *
     * @throws \RuntimeException on transport failure
     */
    public function post(string $url, array $params = []): Response;
}
```

- [ ] **Step 5: Write the test double**

`tests/Support/FakeHttpClient.php`:

```php
<?php

namespace IQuix\Tests\Support;

use IQuix\Setup\Http\ClientInterface;
use IQuix\Setup\Http\Response;

final class FakeHttpClient implements ClientInterface
{
    /** @var list<Response|\RuntimeException> */
    private array $queue;

    /** @var list<array{method: string, url: string, params: array}> */
    private array $requests = [];

    /**
     * @param list<Response|\RuntimeException> $queue
     */
    public function __construct(array $queue)
    {
        $this->queue = $queue;
    }

    public function get(string $url, array $params = []): Response
    {
        return $this->record('get', $url, $params);
    }

    public function post(string $url, array $params = []): Response
    {
        return $this->record('post', $url, $params);
    }

    public function lastUrl(): string
    {
        return $this->requests === [] ? '' : end($this->requests)['url'];
    }

    public function lastParams(): array
    {
        return $this->requests === [] ? [] : end($this->requests)['params'];
    }

    /**
     * @return list<array{method: string, url: string, params: array}>
     */
    public function requests(): array
    {
        return $this->requests;
    }

    private function record(string $method, string $url, array $params): Response
    {
        $this->requests[] = ['method' => $method, 'url' => $url, 'params' => $params];

        if ($this->queue === []) {
            throw new \RuntimeException('FakeHttpClient: no response queued for ' . $method . ' ' . $url);
        }

        $next = array_shift($this->queue);

        if ($next instanceof \RuntimeException) {
            throw $next;
        }

        return $next;
    }
}
```

- [ ] **Step 6: Run the tests to verify they pass**

Run: `vendor/bin/phpunit --testsuite unit`
Expected: PASS.

- [ ] **Step 7: Write the Joomla-backed client**

`setup/lib/Http/JoomlaClient.php`:

```php
<?php

namespace IQuix\Setup\Http;

defined('_JEXEC') or die('Unauthorized Access');

use IQuix\Setup\Config;
use IQuix\Setup\Log;
use Joomla\CMS\Http\HttpFactory;
use Joomla\Registry\Registry;

final class JoomlaClient implements ClientInterface
{
    public function __construct(private readonly Config $config)
    {
    }

    /**
     * Browser-style User-Agent to clear Cloudflare's Browser Integrity Check;
     * the real identity travels in X-Quix-Client for the server's logs.
     */
    public static function headers(Config $config): array
    {
        return [
            'User-Agent'    => Config::USER_AGENT,
            'X-Quix-Client' => 'iQuix/' . IQX_VERSION . ' (Joomla ' . JVERSION . '; ' . $config->storeUrl() . ')',
            'Accept'        => 'application/json, text/xml, */*',
        ];
    }

    public function get(string $url, array $params = []): Response
    {
        if ($params !== []) {
            $url .= (str_contains($url, '?') ? '&' : '?') . http_build_query($params);
        }

        return $this->send('get', $url, null);
    }

    public function post(string $url, array $params = []): Response
    {
        return $this->send('post', $url, http_build_query($params));
    }

    private function send(string $method, string $url, ?string $body): Response
    {
        Log::debug('HTTP ' . strtoupper($method) . ' ' . Log::redactUrl($url));

        $http    = HttpFactory::getHttp(new Registry(['timeout' => Config::API_TIMEOUT]));
        $headers = self::headers($this->config);

        try {
            if ($method === 'get') {
                $response = $http->get($url, $headers);
            } else {
                $headers['Content-Type'] = 'application/x-www-form-urlencoded';
                $response = $http->post($url, $body, $headers);
            }
        } catch (\Throwable $e) {
            throw new \RuntimeException(
                sprintf(
                    'Could not reach %s. Ask your host to allow outgoing connections to it. (%s)',
                    parse_url($url, PHP_URL_HOST) ?: $url,
                    $e->getMessage()
                ),
                0,
                $e
            );
        }

        $result = new Response((int) ($response->code ?? 0), (string) ($response->body ?? ''));

        Log::debug('HTTP response ' . $result->code);

        return $result;
    }
}
```

- [ ] **Step 8: Run the tests**

Run: `vendor/bin/phpunit --testsuite unit`
Expected: PASS.

- [ ] **Step 9: Commit**

```bash
git add setup/lib/Http tests/Support/FakeHttpClient.php tests/Unit/Http
git commit -m "feat(setup): add http seam with browser user agent"
```

---

### Task 5: FluentCart license client

**Files:**
- Create: `setup/lib/License/Status.php`
- Create: `setup/lib/License/LicenseException.php`
- Create: `setup/lib/License/LicenseClient.php`
- Test: `tests/Unit/License/LicenseClientTest.php`

**Interfaces:**
- Consumes: `Config`, `StoreInterface`, `Http\ClientInterface`, `Http\Response`, `Log`.
- Produces:
  - `IQuix\Setup\License\Status` — constants `VALID`, `INVALID`, `EXPIRED`, `DEACTIVATED`, `DISABLED`, `SITE_INACTIVE`
  - `IQuix\Setup\License\LicenseException extends \RuntimeException`
  - `IQuix\Setup\License\LicenseClient`:
    - `__construct(Config $config, StoreInterface $store, ClientInterface $http, string $siteUrl)`
    - `activate(string $licenseKey): object` — throws `LicenseException` on rejection
    - `check(): ?object` — null when there is nothing stored to check
    - `deactivate(): void`
    - `storedKey(): string`
    - `isActivated(): bool`
    - `public static function errorMessage(string $error, string $message = ''): string`

`activate()` and `check()` write the six `com_quix` row names on success. This is the whole point of the class — get the integration right and Quix is licensed the moment it lands.

- [ ] **Step 1: Write the failing test**

`tests/Unit/License/LicenseClientTest.php`:

```php
<?php

namespace IQuix\Tests\Unit\License;

use IQuix\Setup\Config;
use IQuix\Setup\Http\Response;
use IQuix\Setup\License\LicenseClient;
use IQuix\Setup\License\LicenseException;
use IQuix\Setup\License\Status;
use IQuix\Tests\Support\ArrayStore;
use IQuix\Tests\Support\FakeHttpClient;
use PHPUnit\Framework\TestCase;

final class LicenseClientTest extends TestCase
{
    private function client(array $responses, ArrayStore $store = null): array
    {
        $store  = $store ?? new ArrayStore();
        $http   = new FakeHttpClient($responses);
        $client = new LicenseClient(new Config($store), $store, $http, 'https://example.test');

        return [$client, $store, $http];
    }

    public function testActivatePostsTheLegacyDigicomItemId(): void
    {
        [$client, , $http] = $this->client([
            new Response(200, '{"success":true,"status":"valid","activation_hash":"h1"}'),
        ]);

        $client->activate('KEY-123456789');

        $this->assertSame('https://my.converslabs.com/?fluent-cart=activate_license', $http->lastUrl());
        $this->assertSame('116', $http->lastParams()['item_id']);
        $this->assertSame('KEY-123456789', $http->lastParams()['license_key']);
        $this->assertSame('https://example.test', $http->lastParams()['site_url']);
    }

    public function testActivateStoresTheRowNamesComQuixReads(): void
    {
        [$client, $store] = $this->client([
            new Response(200, '{"success":true,"status":"valid","activation_hash":"h1","expiration_date":"2027-01-01"}'),
        ]);

        $client->activate('KEY-123456789');

        $this->assertSame('KEY-123456789', $store->get('license_key'));
        $this->assertSame('h1', $store->get('activation_hash'));
        $this->assertSame('valid', $store->get('license_status'));
        $this->assertSame('2027-01-01', $store->get('license_expires'));
        $this->assertSame('1', $store->get('activated'));
        $this->assertNotSame('', $store->get('license_checked'));
    }

    public function testActivateTrimsTheKey(): void
    {
        [$client, $store] = $this->client([
            new Response(200, '{"success":true,"status":"valid"}'),
        ]);

        $client->activate('  KEY-123456789  ');

        $this->assertSame('KEY-123456789', $store->get('license_key'));
    }

    public function testActivateRejectsAnEmptyKeyWithoutCallingTheServer(): void
    {
        [$client, , $http] = $this->client([]);

        $this->expectException(LicenseException::class);

        try {
            $client->activate('   ');
        } finally {
            $this->assertSame([], $http->requests());
        }
    }

    public function testActivateThrowsWithTheMappedMessageWhenTheServerRejects(): void
    {
        [$client, $store] = $this->client([
            new Response(200, '{"success":false,"error_type":"activation_limit_reached"}'),
        ]);

        try {
            $client->activate('KEY-123456789');
            $this->fail('Expected LicenseException');
        } catch (LicenseException $e) {
            $this->assertStringContainsString('no more activations left', $e->getMessage());
        }

        $this->assertSame('', $store->get('license_key'));
    }

    public function testActivateSurfacesANonJsonBodyRatherThanFailingSilently(): void
    {
        [$client] = $this->client([new Response(403, 'Missing license key.')]);

        $this->expectException(LicenseException::class);
        $this->expectExceptionMessageMatches('/Missing license key/');

        $client->activate('KEY-123456789');
    }

    public function testCheckReturnsNullWhenNothingIsStored(): void
    {
        [$client, , $http] = $this->client([]);

        $this->assertNull($client->check());
        $this->assertSame([], $http->requests());
    }

    public function testCheckPrefersTheActivationHashOverTheRawKey(): void
    {
        $store = new ArrayStore(['license_key' => 'KEY-123456789', 'activation_hash' => 'h1']);
        [$client, , $http] = $this->client([new Response(200, '{"status":"valid"}')], $store);

        $client->check();

        $this->assertSame('h1', $http->lastParams()['activation_hash']);
        $this->assertArrayNotHasKey('license_key', $http->lastParams());
    }

    public function testCheckFallsBackToTheKeyWhenThereIsNoHash(): void
    {
        $store = new ArrayStore(['license_key' => 'KEY-123456789']);
        [$client, , $http] = $this->client([new Response(200, '{"status":"valid"}')], $store);

        $client->check();

        $this->assertSame('KEY-123456789', $http->lastParams()['license_key']);
    }

    public function testCheckMarksTheSiteActivatedOnAValidStatus(): void
    {
        $store = new ArrayStore(['license_key' => 'KEY-123456789', 'activated' => '0']);
        [$client] = $this->client([new Response(200, '{"status":"valid"}')], $store);

        $client->check();

        $this->assertSame('1', $store->get('activated'));
    }

    public function testCheckDeactivatesOnInvalidExpiredAndDisabled(): void
    {
        foreach ([Status::INVALID, Status::EXPIRED, Status::DISABLED] as $status) {
            $store = new ArrayStore(['license_key' => 'KEY-123456789', 'activated' => '1']);
            [$client] = $this->client([new Response(200, '{"status":"' . $status . '"}')], $store);

            $client->check();

            $this->assertSame('0', $store->get('activated'), 'status ' . $status . ' must deactivate');
            $this->assertSame($status, $store->get('license_status'));
        }
    }

    public function testCheckKeepsCurrentStateWhenTheServerIsUnreachable(): void
    {
        $store = new ArrayStore(['license_key' => 'KEY-123456789', 'activated' => '1']);
        [$client] = $this->client([new \RuntimeException('network down')], $store);

        $this->assertNull($client->check());
        $this->assertSame('1', $store->get('activated'));
        $this->assertNotSame('', $store->get('license_checked'));
    }

    public function testDeactivateClearsLocalStateEvenWhenTheServerFails(): void
    {
        $store = new ArrayStore([
            'license_key'     => 'KEY-123456789',
            'activation_hash' => 'h1',
            'activated'       => '1',
        ]);
        [$client] = $this->client([new \RuntimeException('network down')], $store);

        $client->deactivate();

        $this->assertSame('0', $store->get('activated'));
        $this->assertSame('', $store->get('activation_hash'));
        $this->assertSame(Status::DEACTIVATED, $store->get('license_status'));
    }

    public function testIsActivatedReflectsTheStoredFlag(): void
    {
        [$client] = $this->client([], new ArrayStore(['activated' => '1']));

        $this->assertTrue($client->isActivated());
    }

    public function testErrorMessageFallsBackToTheServerText(): void
    {
        $message = LicenseClient::errorMessage('some_new_code', 'Server said no');

        $this->assertStringContainsString('Server said no', $message);
        $this->assertStringContainsString('some_new_code', $message);
    }
}
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `vendor/bin/phpunit --testsuite unit`
Expected: FAIL — `Class "IQuix\Setup\License\LicenseClient" not found`.

- [ ] **Step 3: Write the status constants and exception**

`setup/lib/License/Status.php`:

```php
<?php

namespace IQuix\Setup\License;

defined('_JEXEC') or die('Unauthorized Access');

final class Status
{
    public const VALID         = 'valid';
    public const INVALID       = 'invalid';
    public const EXPIRED       = 'expired';
    public const DEACTIVATED   = 'deactivated';
    public const DISABLED      = 'disabled';
    public const SITE_INACTIVE = 'site_inactive';

    /** Statuses that mean this site is no longer entitled to Pro. */
    public const REVOKING = [self::INVALID, self::DISABLED, self::EXPIRED];
}
```

`setup/lib/License/LicenseException.php`:

```php
<?php

namespace IQuix\Setup\License;

defined('_JEXEC') or die('Unauthorized Access');

final class LicenseException extends \RuntimeException
{
}
```

- [ ] **Step 4: Write the client**

`setup/lib/License/LicenseClient.php`:

```php
<?php

namespace IQuix\Setup\License;

defined('_JEXEC') or die('Unauthorized Access');

use IQuix\Setup\Config;
use IQuix\Setup\Http\ClientInterface;
use IQuix\Setup\Http\Response;
use IQuix\Setup\Log;
use IQuix\Setup\StoreInterface;

/**
 * FluentCart public license API client.
 *
 * Mirrors QuixHelperLicense in com_quix and writes the same #__quix_configs
 * rows, so Quix is already activated when this installer finishes.
 */
final class LicenseClient
{
    public function __construct(
        private readonly Config $config,
        private readonly StoreInterface $store,
        private readonly ClientInterface $http,
        private readonly string $siteUrl
    ) {
    }

    public function storedKey(): string
    {
        return $this->store->get('license_key');
    }

    public function isActivated(): bool
    {
        return $this->store->get('activated') === '1';
    }

    /**
     * @throws LicenseException when the key is empty or the server rejects it
     */
    public function activate(string $licenseKey): object
    {
        $licenseKey = trim($licenseKey);

        if ($licenseKey === '') {
            throw new LicenseException('Please enter your license key.');
        }

        $response = $this->request('activate_license', [
            'license_key'      => $licenseKey,
            'item_id'          => $this->config->itemId(),
            'site_url'         => $this->siteUrl,
            'server_version'   => PHP_VERSION,
            'platform_version' => JVERSION,
        ], 'post');

        $status = $response->status ?? Status::VALID;

        if (empty($response->success) || $status !== Status::VALID) {
            throw new LicenseException(self::errorMessage(
                (string) ($response->error_type ?? $response->code ?? $status),
                (string) ($response->message ?? '')
            ));
        }

        $this->store->setMany([
            'license_key'     => $licenseKey,
            'activation_hash' => (string) ($response->activation_hash ?? ''),
            'license_status'  => $status,
            'license_expires' => (string) ($response->expiration_date ?? ''),
            'license_checked' => (string) time(),
            'activated'       => '1',
        ]);

        return $response;
    }

    /**
     * Re-validate stored credentials. Returns null when there is nothing to
     * check or the server could not be reached — in the latter case the
     * current state is deliberately left alone.
     */
    public function check(): ?object
    {
        $key  = $this->store->get('license_key');
        $hash = $this->store->get('activation_hash');

        if ($key === '' && $hash === '') {
            return null;
        }

        $params = [
            'item_id'  => $this->config->itemId(),
            'site_url' => $this->siteUrl,
        ];

        if ($hash !== '') {
            $params['activation_hash'] = $hash;
        } else {
            $params['license_key'] = $key;
        }

        try {
            $response = $this->request('check_license', $params, 'get');
        } catch (LicenseException $e) {
            $this->store->set('license_checked', (string) time());

            return null;
        }

        $status = (string) ($response->status ?? Status::INVALID);

        $update = [
            'license_status'  => $status,
            'license_checked' => (string) time(),
        ];

        if (isset($response->expiration_date)) {
            $update['license_expires'] = (string) $response->expiration_date;
        }

        if (in_array($status, Status::REVOKING, true)) {
            $update['activated'] = '0';
        } elseif ($status === Status::VALID) {
            $update['activated'] = '1';
        }

        $this->store->setMany($update);

        return $response;
    }

    /**
     * Local state is always cleared, even when the remote call fails — a
     * revoked key must not leave the site stuck "activated".
     */
    public function deactivate(): void
    {
        $key = $this->store->get('license_key');

        if ($key !== '') {
            try {
                $this->request('deactivate_license', [
                    'license_key' => $key,
                    'item_id'     => $this->config->itemId(),
                    'site_url'    => $this->siteUrl,
                ], 'post');
            } catch (LicenseException $e) {
                // Intentionally swallowed; local state is cleared regardless.
            }
        }

        $this->store->setMany([
            'activation_hash' => '',
            'license_status'  => Status::DEACTIVATED,
            'license_checked' => (string) time(),
            'activated'       => '0',
        ]);
    }

    public static function errorMessage(string $error, string $message = ''): string
    {
        $renew = Config::STORE_URL;

        $errors = [
            'activation_limit_reached' => sprintf(
                'You have no more activations left. <a href="%s" target="_blank" rel="noopener">Upgrade your license</a> to add this site.',
                $renew
            ),
            'expired' => sprintf(
                'Your license has expired. <a href="%s" target="_blank" rel="noopener">Renew it</a> to install and keep receiving updates.',
                $renew
            ),
            'invalid_license'  => 'That license key is not valid. Please check it and try again.',
            'invalid'          => 'That license key is not valid. Please check it and try again.',
            'validation_error' => 'The license key could not be validated. Please check it and try again.',
            'missing'          => 'No license was found for that key. Please check it and try again.',
            'disabled'         => 'This license key has been cancelled, most likely after a refund. Please use a current license.',
            'revoked'          => 'This license key has been cancelled, most likely after a refund. Please use a current license.',
            'key_mismatch'     => 'This license is not valid for this domain. Please check your key again.',
        ];

        if (isset($errors[$error])) {
            return $errors[$error];
        }

        if ($message !== '') {
            return $message . ' (' . $error . ')';
        }

        return 'The license server could not be reached. Check your connection and try again. (' . $error . ')';
    }

    /**
     * @param array<string, string> $params
     *
     * @throws LicenseException on transport failure or a non-JSON body
     */
    private function request(string $action, array $params, string $method): object
    {
        $url = $this->config->licenseUrl($action);

        Log::debug('License ' . $action, $params);

        try {
            $response = $method === 'get'
                ? $this->http->get($url, $params)
                : $this->http->post($url, $params);
        } catch (\RuntimeException $e) {
            throw new LicenseException($e->getMessage(), 0, $e);
        }

        $body = $response->json();

        if ($body === null) {
            throw new LicenseException($this->describeBadResponse($response));
        }

        return $body;
    }

    /**
     * A non-JSON body is a firewall page, a WAF block, or a plain-text server
     * error such as "Missing license key." — say what actually happened.
     */
    private function describeBadResponse(Response $response): string
    {
        $snippet = trim(strip_tags($response->body));
        $snippet = $snippet === '' ? '(empty response)' : substr($snippet, 0, 200);

        return sprintf(
            'The license server returned an unexpected response (HTTP %d): %s',
            $response->code,
            $snippet
        );
    }
}
```

- [ ] **Step 5: Run the test to verify it passes**

Run: `vendor/bin/phpunit --testsuite unit`
Expected: PASS.

Note: `JVERSION` is undefined under the test bootstrap. Add it to `tests/bootstrap.php` alongside `_JEXEC`:

```php
if (!defined('JVERSION')) {
    define('JVERSION', '6.1.2');
}

if (!defined('IQX_VERSION')) {
    define('IQX_VERSION', '2.0.0');
}
```

- [ ] **Step 6: Commit**

```bash
git add setup/lib/License tests/Unit/License tests/bootstrap.php
git commit -m "feat(license): add fluentcart license client"
```

---

### Task 6: Skip-the-prompt decision

**Files:**
- Create: `setup/lib/License/LicenseGate.php`
- Test: `tests/Unit/License/LicenseGateTest.php`

**Interfaces:**
- Consumes: `LicenseClient`, `StoreInterface`, `Status`, `Log`.
- Produces: `IQuix\Setup\License\LicenseGate`
  - `__construct(LicenseClient $client, StoreInterface $store)`
  - `evaluate(): array` returning
    `['licensed' => bool, 'edition' => 'pro'|'free', 'maskedKey' => string, 'status' => string, 'prompt' => bool, 'reason' => string]`

This is the "don't ask for credentials if already licensed" requirement, isolated as pure decision logic so every branch is provable.

- [ ] **Step 1: Write the failing test**

`tests/Unit/License/LicenseGateTest.php`:

```php
<?php

namespace IQuix\Tests\Unit\License;

use IQuix\Setup\Config;
use IQuix\Setup\Http\Response;
use IQuix\Setup\License\LicenseClient;
use IQuix\Setup\License\LicenseGate;
use IQuix\Setup\License\Status;
use IQuix\Tests\Support\ArrayStore;
use IQuix\Tests\Support\FakeHttpClient;
use PHPUnit\Framework\TestCase;

final class LicenseGateTest extends TestCase
{
    private function gate(array $responses, array $stored = []): array
    {
        $store  = new ArrayStore($stored);
        $http   = new FakeHttpClient($responses);
        $client = new LicenseClient(new Config($store), $store, $http, 'https://example.test');

        return [new LicenseGate($client, $store), $store, $http];
    }

    public function testAValidStoredKeySkipsThePrompt(): void
    {
        [$gate] = $this->gate(
            [new Response(200, '{"status":"valid"}')],
            ['license_key' => 'KEY-123456789', 'activation_hash' => 'h1']
        );

        $result = $gate->evaluate();

        $this->assertTrue($result['licensed']);
        $this->assertFalse($result['prompt']);
        $this->assertSame('pro', $result['edition']);
        $this->assertSame('KEY-*****6789', $result['maskedKey']);
        $this->assertSame(Status::VALID, $result['status']);
    }

    public function testAnExpiredStoredKeyPromptsWithTheReason(): void
    {
        [$gate] = $this->gate(
            [new Response(200, '{"status":"expired"}')],
            ['license_key' => 'KEY-123456789']
        );

        $result = $gate->evaluate();

        $this->assertFalse($result['licensed']);
        $this->assertTrue($result['prompt']);
        $this->assertSame(Status::EXPIRED, $result['status']);
        $this->assertStringContainsString('expired', strtolower($result['reason']));
    }

    public function testNoStoredCredentialsPromptsWithoutCallingTheServer(): void
    {
        [$gate, , $http] = $this->gate([]);

        $result = $gate->evaluate();

        $this->assertTrue($result['prompt']);
        $this->assertFalse($result['licensed']);
        $this->assertSame('free', $result['edition']);
        $this->assertSame('', $result['maskedKey']);
        $this->assertSame([], $http->requests());
    }

    public function testALegacyAuthKeyIsTriedAsALicenseKeyBeforeAsking(): void
    {
        // The DigiCom to FluentCart migrator carried customer auth keys over
        // verbatim, so an old iQuix `key` row is very often the current
        // license key.
        [$gate, $store, $http] = $this->gate(
            [new Response(200, '{"success":true,"status":"valid","activation_hash":"h9"}')],
            ['username' => 'someone', 'key' => 'LEGACY-987654321']
        );

        $result = $gate->evaluate();

        $this->assertTrue($result['licensed']);
        $this->assertFalse($result['prompt']);
        $this->assertSame('LEGACY-987654321', $store->get('license_key'));
        $this->assertSame('h9', $store->get('activation_hash'));
        $this->assertStringContainsString('activate_license', $http->lastUrl());
    }

    public function testARejectedLegacyKeyFallsThroughToThePrompt(): void
    {
        [$gate, $store] = $this->gate(
            [new Response(200, '{"success":false,"error_type":"invalid_license"}')],
            ['username' => 'someone', 'key' => 'LEGACY-987654321']
        );

        $result = $gate->evaluate();

        $this->assertTrue($result['prompt']);
        $this->assertFalse($result['licensed']);
        $this->assertSame('', $store->get('license_key'));
    }

    public function testAnUnreachableServerPromptsRatherThanClaimingLicensed(): void
    {
        [$gate] = $this->gate(
            [new \RuntimeException('network down')],
            ['license_key' => 'KEY-123456789']
        );

        $result = $gate->evaluate();

        $this->assertTrue($result['prompt']);
        $this->assertFalse($result['licensed']);
    }

    public function testTheStoredKeyIsNeverReturnedInTheClear(): void
    {
        [$gate] = $this->gate(
            [new Response(200, '{"status":"valid"}')],
            ['license_key' => 'KEY-123456789']
        );

        $result = $gate->evaluate();

        $this->assertStringNotContainsString('123456789', json_encode($result));
    }
}
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `vendor/bin/phpunit --testsuite unit`
Expected: FAIL — `Class "IQuix\Setup\License\LicenseGate" not found`.

- [ ] **Step 3: Write the implementation**

`setup/lib/License/LicenseGate.php`:

```php
<?php

namespace IQuix\Setup\License;

defined('_JEXEC') or die('Unauthorized Access');

use IQuix\Setup\Log;
use IQuix\Setup\StoreInterface;

/**
 * Decides whether the wizard has to ask for a license key at all.
 *
 * A site that already carries valid credentials — from a previous Quix
 * install, or from old iQuix — is never asked again.
 */
final class LicenseGate
{
    public function __construct(
        private readonly LicenseClient $client,
        private readonly StoreInterface $store
    ) {
    }

    /**
     * @return array{licensed: bool, edition: string, maskedKey: string, status: string, prompt: bool, reason: string}
     */
    public function evaluate(): array
    {
        if ($this->store->has('license_key') || $this->store->has('activation_hash')) {
            return $this->fromCheck();
        }

        $legacy = trim($this->store->get('key'));

        if ($legacy !== '') {
            return $this->fromLegacyKey($legacy);
        }

        return $this->promptResult('', '', '');
    }

    /**
     * Stored credentials exist — ask the server whether they are still good.
     */
    private function fromCheck(): array
    {
        $response = $this->client->check();
        $status   = $this->store->get('license_status');

        if ($response === null && $status !== Status::VALID) {
            // Server unreachable and no prior "valid" verdict to lean on.
            return $this->promptResult(
                $this->store->get('license_key'),
                $status,
                'We could not reach the license server. Enter your key to continue, or retry.'
            );
        }

        if ($this->client->isActivated()) {
            return $this->licensedResult();
        }

        return $this->promptResult(
            $this->store->get('license_key'),
            $status,
            LicenseClient::errorMessage($status)
        );
    }

    /**
     * Old iQuix stored a DigiCom username plus auth key. The migrator carried
     * those keys into FluentCart verbatim, so try it before asking.
     */
    private function fromLegacyKey(string $legacy): array
    {
        Log::debug('Trying legacy auth key as a FluentCart license key');

        try {
            $this->client->activate($legacy);
        } catch (LicenseException $e) {
            return $this->promptResult('', Status::INVALID, $e->getMessage());
        }

        return $this->licensedResult();
    }

    private function licensedResult(): array
    {
        return [
            'licensed'  => true,
            'edition'   => 'pro',
            'maskedKey' => Log::maskKey($this->store->get('license_key')),
            'status'    => $this->store->get('license_status'),
            'prompt'    => false,
            'reason'    => '',
        ];
    }

    private function promptResult(string $key, string $status, string $reason): array
    {
        return [
            'licensed'  => false,
            'edition'   => 'free',
            'maskedKey' => $key === '' ? '' : Log::maskKey($key),
            'status'    => $status,
            'prompt'    => true,
            'reason'    => $reason,
        ];
    }
}
```

- [ ] **Step 4: Run the test to verify it passes**

Run: `vendor/bin/phpunit --testsuite unit`
Expected: PASS, 7 tests in this file.

- [ ] **Step 5: Commit**

```bash
git add setup/lib/License/LicenseGate.php tests/Unit/License/LicenseGateTest.php
git commit -m "feat(license): skip the key prompt on licensed sites"
```

---

### Task 7: Package source resolution

**Files:**
- Create: `setup/lib/Package/Source.php`
- Create: `setup/lib/Package/SourceResolver.php`
- Test: `tests/Unit/Package/SourceResolverTest.php`

**Interfaces:**
- Consumes: `Config`, `StoreInterface`, `Http\ClientInterface`, `Log`.
- Produces:
  - `IQuix\Setup\Package\Source` — readonly `string $url`, `string $version`, `string $sha256`, `string $edition`
  - `IQuix\Setup\Package\SourceResolver`
    - `__construct(Config $config, StoreInterface $store, ClientInterface $http, string $siteUrl)`
    - `resolve(string $edition): Source` — `'pro'` or `'free'`; throws `\RuntimeException` on an unusable manifest

The Pro download URL must carry `license_key`, `activation_hash` and `site_url`, or the server answers `403 Missing license key.`

- [ ] **Step 1: Write the failing test**

`tests/Unit/Package/SourceResolverTest.php`:

```php
<?php

namespace IQuix\Tests\Unit\Package;

use IQuix\Setup\Config;
use IQuix\Setup\Http\Response;
use IQuix\Setup\Package\SourceResolver;
use IQuix\Tests\Support\ArrayStore;
use IQuix\Tests\Support\FakeHttpClient;
use PHPUnit\Framework\TestCase;

final class SourceResolverTest extends TestCase
{
    private const PRO_XML = <<<'XML'
<?xml version="1.0" encoding="utf-8"?>
<updates>
  <update>
    <name>Quix Pro Update</name>
    <element>pkg_quix</element>
    <type>package</type>
    <version>6.2.7</version>
    <downloads>
      <downloadurl type="full" format="zip">https://my.converslabs.com/?fcdc_joomla=download&amp;pid=116</downloadurl>
    </downloads>
  </update>
</updates>
XML;

    private const FREE_XML = <<<'XML'
<?xml version="1.0" encoding="utf-8"?>
<updates>
  <update>
    <element>pkg_quix</element>
    <version>6.2.7</version>
    <downloads>
      <downloadurl type="full" format="zip">https://github.com/themexpert/quix-free/releases/download/6.2.7/pkg_quix_free.zip</downloadurl>
    </downloads>
    <sha256>bdd4e43e9792fb553ff84e83d71db7386b726d2573e20893ea1b7e928fa0e743</sha256>
  </update>
</updates>
XML;

    private function resolver(array $responses, array $stored = []): SourceResolver
    {
        $store = new ArrayStore($stored);

        return new SourceResolver(
            new Config($store),
            $store,
            new FakeHttpClient($responses),
            'https://example.test'
        );
    }

    public function testProSourceAppendsLicenseCredentialsToTheDownloadUrl(): void
    {
        $resolver = $this->resolver(
            [new Response(200, self::PRO_XML)],
            ['license_key' => 'KEY-123456789', 'activation_hash' => 'h1']
        );

        $source = $resolver->resolve('pro');

        $this->assertStringContainsString('license_key=KEY-123456789', $source->url);
        $this->assertStringContainsString('activation_hash=h1', $source->url);
        $this->assertStringContainsString('site_url=' . urlencode('https://example.test'), $source->url);
        $this->assertStringContainsString('pid=116', $source->url);
        $this->assertSame('6.2.7', $source->version);
        $this->assertSame('pro', $source->edition);
    }

    public function testProSourceHasNoHashBecauseTheServerDoesNotPublishOne(): void
    {
        $resolver = $this->resolver(
            [new Response(200, self::PRO_XML)],
            ['license_key' => 'KEY-123456789']
        );

        $this->assertSame('', $resolver->resolve('pro')->sha256);
    }

    public function testFreeSourceCarriesThePublishedSha256(): void
    {
        $source = $this->resolver([new Response(200, self::FREE_XML)])->resolve('free');

        $this->assertSame(
            'bdd4e43e9792fb553ff84e83d71db7386b726d2573e20893ea1b7e928fa0e743',
            $source->sha256
        );
        $this->assertSame('free', $source->edition);
        $this->assertStringNotContainsString('license_key', $source->url);
    }

    public function testFreeSourceIsFetchedFromTheGithubManifest(): void
    {
        $http = new FakeHttpClient([new Response(200, self::FREE_XML)]);
        $store = new ArrayStore();
        $resolver = new SourceResolver(new Config($store), $store, $http, 'https://example.test');

        $resolver->resolve('free');

        $this->assertSame(
            'https://raw.githubusercontent.com/themexpert/quix-free/main/jed.xml',
            $http->lastUrl()
        );
    }

    public function testAnHttpErrorIsReportedWithItsStatusCode(): void
    {
        $resolver = $this->resolver([new Response(503, 'Service Unavailable')]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/503/');

        $resolver->resolve('free');
    }

    public function testANonXmlBodyIsSurfacedVerbatim(): void
    {
        $resolver = $this->resolver([new Response(200, 'Missing license key.')]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/Missing license key/');

        $resolver->resolve('free');
    }

    public function testAManifestWithNoDownloadUrlIsRejected(): void
    {
        $xml = '<?xml version="1.0"?><updates><update><version>6.2.7</version></update></updates>';
        $resolver = $this->resolver([new Response(200, $xml)]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/download/i');

        $resolver->resolve('free');
    }

    public function testAnUnknownEditionIsRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->resolver([])->resolve('enterprise');
    }
}
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `vendor/bin/phpunit --testsuite unit`
Expected: FAIL — `Class "IQuix\Setup\Package\SourceResolver" not found`.

- [ ] **Step 3: Write the value object**

`setup/lib/Package/Source.php`:

```php
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
```

- [ ] **Step 4: Write the resolver**

`setup/lib/Package/SourceResolver.php`:

```php
<?php

namespace IQuix\Setup\Package;

defined('_JEXEC') or die('Unauthorized Access');

use IQuix\Setup\Config;
use IQuix\Setup\Http\ClientInterface;
use IQuix\Setup\Http\Response;
use IQuix\Setup\Log;
use IQuix\Setup\StoreInterface;

/**
 * Turns an edition into a concrete download URL and expected hash by reading
 * the relevant Joomla update manifest.
 */
final class SourceResolver
{
    public function __construct(
        private readonly Config $config,
        private readonly StoreInterface $store,
        private readonly ClientInterface $http,
        private readonly string $siteUrl
    ) {
    }

    public function resolve(string $edition): Source
    {
        return match ($edition) {
            'pro'   => $this->resolvePro(),
            'free'  => $this->resolveFree(),
            default => throw new \InvalidArgumentException('Unknown edition: ' . $edition),
        };
    }

    private function resolvePro(): Source
    {
        $update = $this->fetchManifest($this->config->proUpdateXmlUrl());

        $credentials = http_build_query([
            'license_key'     => $this->store->get('license_key'),
            'activation_hash' => $this->store->get('activation_hash'),
            'site_url'        => $this->siteUrl,
        ]);

        $url = $update['url'] . (str_contains($update['url'], '?') ? '&' : '?') . $credentials;

        return new Source($url, $update['version'], $update['sha256'], 'pro');
    }

    private function resolveFree(): Source
    {
        $update = $this->fetchManifest($this->config->freeUpdateXmlUrl());

        if ($update['sha256'] === '') {
            Log::debug('Free manifest published no sha256; integrity cannot be verified');
        }

        return new Source($update['url'], $update['version'], $update['sha256'], 'free');
    }

    /**
     * @return array{url: string, version: string, sha256: string}
     */
    private function fetchManifest(string $url): array
    {
        Log::debug('Fetching update manifest ' . Log::redactUrl($url));

        $response = $this->http->get($url);

        if (!$response->isOk()) {
            throw new \RuntimeException(sprintf(
                'The update server returned HTTP %d for %s: %s',
                $response->code,
                parse_url($url, PHP_URL_HOST) ?: $url,
                $this->snippet($response)
            ));
        }

        $xml = $this->parse($response);

        $update = $xml->update[0] ?? null;

        if ($update === null) {
            throw new \RuntimeException('The update manifest contained no releases.');
        }

        $downloadUrl = trim((string) ($update->downloads->downloadurl ?? ''));

        if ($downloadUrl === '') {
            throw new \RuntimeException('The update manifest contained no download URL.');
        }

        return [
            'url'     => $downloadUrl,
            'version' => trim((string) ($update->version ?? '')),
            'sha256'  => strtolower(trim((string) ($update->sha256 ?? ''))),
        ];
    }

    /**
     * A non-XML body means the server answered with an error string such as
     * "Missing license key." — surface it instead of a parse failure.
     */
    private function parse(Response $response): \SimpleXMLElement
    {
        $previous = libxml_use_internal_errors(true);
        $xml      = simplexml_load_string($response->body);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if ($xml === false) {
            throw new \RuntimeException(
                'The update server did not return a manifest: ' . $this->snippet($response)
            );
        }

        return $xml;
    }

    private function snippet(Response $response): string
    {
        $text = trim(strip_tags($response->body));

        return $text === '' ? '(empty response)' : substr($text, 0, 200);
    }
}
```

- [ ] **Step 5: Run the test to verify it passes**

Run: `vendor/bin/phpunit --testsuite unit`
Expected: PASS, 8 tests in this file.

- [ ] **Step 6: Commit**

```bash
git add setup/lib/Package tests/Unit/Package
git commit -m "feat(package): resolve download url from update manifest"
```

---

### Task 8: Verified downloader

**Files:**
- Create: `setup/lib/Package/Downloader.php`
- Test: `tests/Unit/Package/DownloaderTest.php`

**Interfaces:**
- Consumes: `Config`, `Package\Source`, `Log`.
- Produces: `IQuix\Setup\Package\Downloader`
  - `__construct(string $tmpPath)`
  - `fetch(Source $source): string` — absolute path of the downloaded archive
  - `public static function curlOptions(string $url, $handle): array` — the option map, exposed so the security-critical flags are testable without a network call
  - `public static function verify(string $path, string $expectedSha256): void` — throws on mismatch, non-zip, or empty file
  - `cleanup(string $path): void`

The current code sets `CURLOPT_SSL_VERIFYPEER` and `CURLOPT_SSL_VERIFYHOST` to false and writes the paid archive to a web-readable directory it never cleans. Both are fixed here.

- [ ] **Step 1: Write the failing test**

`tests/Unit/Package/DownloaderTest.php`:

```php
<?php

namespace IQuix\Tests\Unit\Package;

use IQuix\Setup\Config;
use IQuix\Setup\Package\Downloader;
use PHPUnit\Framework\TestCase;

final class DownloaderTest extends TestCase
{
    private string $tmp;

    protected function setUp(): void
    {
        $this->tmp = sys_get_temp_dir() . '/iquix-test-' . bin2hex(random_bytes(6));
        mkdir($this->tmp, 0700, true);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->tmp . '/*') ?: [] as $file) {
            @unlink($file);
        }

        @rmdir($this->tmp);
    }

    private function writeZip(string $name): string
    {
        $path = $this->tmp . '/' . $name;
        $zip  = new \ZipArchive();
        $zip->open($path, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);
        $zip->addFromString('pkg_quix.xml', '<extension type="package"/>');
        $zip->close();

        return $path;
    }

    public function testCurlOptionsEnforceTlsVerification(): void
    {
        $handle  = fopen('php://memory', 'w+');
        $options = Downloader::curlOptions('https://my.converslabs.com/', $handle);
        fclose($handle);

        $this->assertTrue($options[CURLOPT_SSL_VERIFYPEER]);
        $this->assertSame(2, $options[CURLOPT_SSL_VERIFYHOST]);
    }

    public function testCurlOptionsCapRedirectsAndRestrictThemToHttps(): void
    {
        $handle  = fopen('php://memory', 'w+');
        $options = Downloader::curlOptions('https://my.converslabs.com/', $handle);
        fclose($handle);

        $this->assertTrue($options[CURLOPT_FOLLOWLOCATION]);
        $this->assertSame(3, $options[CURLOPT_MAXREDIRS]);
        $this->assertSame(CURLPROTO_HTTPS, $options[CURLOPT_REDIR_PROTOCOLS]);
    }

    public function testCurlOptionsSetABoundedTimeoutNotUnlimited(): void
    {
        $handle  = fopen('php://memory', 'w+');
        $options = Downloader::curlOptions('https://my.converslabs.com/', $handle);
        fclose($handle);

        $this->assertSame(Config::DOWNLOAD_TIMEOUT, $options[CURLOPT_TIMEOUT]);
        $this->assertGreaterThan(0, $options[CURLOPT_TIMEOUT]);
    }

    public function testVerifyAcceptsAZipWhoseHashMatches(): void
    {
        $path = $this->writeZip('good.zip');

        Downloader::verify($path, hash_file('sha256', $path));

        $this->assertFileExists($path);
    }

    public function testVerifyAcceptsAZipWhenNoHashIsPublished(): void
    {
        $path = $this->writeZip('nohash.zip');

        Downloader::verify($path, '');

        $this->assertFileExists($path);
    }

    public function testVerifyRejectsAHashMismatch(): void
    {
        $path = $this->writeZip('bad.zip');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/checksum/i');

        Downloader::verify($path, str_repeat('a', 64));
    }

    public function testVerifyRejectsAServerErrorStringSavedAsAZip(): void
    {
        $path = $this->tmp . '/error.zip';
        file_put_contents($path, 'Missing license key.');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/Missing license key/');

        Downloader::verify($path, '');
    }

    public function testVerifyRejectsAnEmptyDownload(): void
    {
        $path = $this->tmp . '/empty.zip';
        file_put_contents($path, '');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/empty/i');

        Downloader::verify($path, '');
    }

    public function testCleanupRemovesTheArchive(): void
    {
        $path = $this->writeZip('gone.zip');

        (new Downloader($this->tmp))->cleanup($path);

        $this->assertFileDoesNotExist($path);
    }
}
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `vendor/bin/phpunit --testsuite unit`
Expected: FAIL — `Class "IQuix\Setup\Package\Downloader" not found`.

- [ ] **Step 3: Write the implementation**

`setup/lib/Package/Downloader.php`:

```php
<?php

namespace IQuix\Setup\Package;

defined('_JEXEC') or die('Unauthorized Access');

use IQuix\Setup\Config;
use IQuix\Setup\Log;

/**
 * Downloads a package archive into Joomla's tmp path with TLS verified, a
 * size cap, and an integrity check.
 *
 * The archive never touches the component directory: writing it under
 * administrator/components/com_iquix/ made the paid package downloadable by
 * anyone who guessed the URL.
 */
final class Downloader
{
    public function __construct(private readonly string $tmpPath)
    {
    }

    /**
     * @return string absolute path to the downloaded archive
     *
     * @throws \RuntimeException on transport failure or a bad archive
     */
    public function fetch(Source $source): string
    {
        $dir = $this->tmpPath . '/iquix-' . bin2hex(random_bytes(8));

        if (!@mkdir($dir, 0700, true) && !is_dir($dir)) {
            throw new \RuntimeException('Could not create a temporary directory at ' . $this->tmpPath);
        }

        $path   = $dir . '/pkg_quix.zip';
        $handle = fopen($path, 'w+b');

        if ($handle === false) {
            throw new \RuntimeException('Could not open ' . $path . ' for writing.');
        }

        Log::debug('Downloading package ' . Log::redactUrl($source->url));

        $curl = curl_init();
        curl_setopt_array($curl, self::curlOptions($source->url, $handle));

        $ok    = curl_exec($curl);
        $error = curl_error($curl);
        $code  = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);

        curl_close($curl);
        fclose($handle);

        if ($ok === false) {
            $this->cleanup($path);

            throw new \RuntimeException('The download failed: ' . ($error ?: 'unknown transport error'));
        }

        if ($code !== 200) {
            $body = trim(strip_tags((string) file_get_contents($path, false, null, 0, 500)));
            $this->cleanup($path);

            throw new \RuntimeException(sprintf(
                'The download server returned HTTP %d: %s',
                $code,
                $body === '' ? '(empty response)' : $body
            ));
        }

        try {
            self::verify($path, $source->sha256);
        } catch (\RuntimeException $e) {
            $this->cleanup($path);

            throw $e;
        }

        Log::debug('Package downloaded to ' . $path . ' (' . filesize($path) . ' bytes)');

        return $path;
    }

    /**
     * The security-critical option map, exposed so it can be asserted on
     * without making a network call.
     *
     * @param resource $handle
     */
    public static function curlOptions(string $url, $handle): array
    {
        return [
            CURLOPT_URL             => $url,
            CURLOPT_FILE            => $handle,
            CURLOPT_TIMEOUT         => Config::DOWNLOAD_TIMEOUT,
            CURLOPT_CONNECTTIMEOUT  => 30,
            CURLOPT_FOLLOWLOCATION  => true,
            CURLOPT_MAXREDIRS       => 3,
            CURLOPT_PROTOCOLS       => CURLPROTO_HTTPS,
            CURLOPT_REDIR_PROTOCOLS => CURLPROTO_HTTPS,
            CURLOPT_SSL_VERIFYPEER  => true,
            CURLOPT_SSL_VERIFYHOST  => 2,
            CURLOPT_USERAGENT       => Config::USER_AGENT,
            CURLOPT_NOPROGRESS      => false,
            CURLOPT_PROGRESSFUNCTION => static function ($resource, $downloadSize, $downloaded) {
                // Abort rather than fill the disk if the server misbehaves.
                return $downloaded > Config::MAX_PACKAGE_BYTES ? 1 : 0;
            },
        ];
    }

    /**
     * @throws \RuntimeException when the file is empty, is not a zip, or does
     *                           not match the published checksum
     */
    public static function verify(string $path, string $expectedSha256): void
    {
        $size = is_file($path) ? (int) filesize($path) : 0;

        if ($size === 0) {
            throw new \RuntimeException('The downloaded file was empty.');
        }

        $handle = fopen($path, 'rb');
        $magic  = (string) fread($handle, 4);
        fclose($handle);

        // Every zip starts "PK\x03\x04". Anything else is a server error page
        // or a plain-text message saved under a .zip name.
        if (strncmp($magic, "PK\x03\x04", 4) !== 0) {
            $body = trim(strip_tags((string) file_get_contents($path, false, null, 0, 500)));

            throw new \RuntimeException(
                'The server did not return a package: ' . ($body === '' ? '(binary junk)' : $body)
            );
        }

        if ($expectedSha256 === '') {
            Log::debug('No checksum published for this package; skipping integrity check');

            return;
        }

        $actual = hash_file('sha256', $path);

        if (!hash_equals($expectedSha256, (string) $actual)) {
            throw new \RuntimeException(
                'The downloaded package failed its checksum check. Please try again, and contact support if it keeps happening.'
            );
        }
    }

    public function cleanup(string $path): void
    {
        if (is_file($path)) {
            @unlink($path);
        }

        $dir = dirname($path);

        if (is_dir($dir) && str_starts_with(basename($dir), 'iquix-')) {
            @rmdir($dir);
        }
    }
}
```

- [ ] **Step 4: Run the test to verify it passes**

Run: `vendor/bin/phpunit --testsuite unit`
Expected: PASS, 9 tests in this file.

- [ ] **Step 5: Commit**

```bash
git add setup/lib/Package/Downloader.php tests/Unit/Package/DownloaderTest.php
git commit -m "fix(security): verify tls and checksums when downloading"
```

---

### Task 9: Whitelisting router with ACL and CSRF

**Files:**
- Create: `setup/lib/Routes.php`
- Create: `setup/lib/GuardInterface.php`
- Create: `setup/lib/JoomlaGuard.php`
- Create: `setup/lib/Router.php`
- Create: `tests/Support/FakeGuard.php`
- Test: `tests/Unit/RoutesTest.php`
- Test: `tests/Unit/RouterTest.php`

**Interfaces:**
- Consumes: nothing from earlier tasks except the autoloader.
- Produces:
  - `IQuix\Setup\Routes::MAP` — `array<string, list<string>>` of controller to allowed tasks; `Routes::allows(string $controller, string $task): bool`; `Routes::controllerClass(string $controller): ?string`
  - `IQuix\Setup\GuardInterface` — `isAuthorised(): bool`, `hasValidToken(): bool`
  - `IQuix\Setup\JoomlaGuard` — the real `core.admin` plus `Session::checkToken` implementation
  - `IQuix\Setup\Router` — `__construct(GuardInterface $guard)`, `resolve(string $controller, string $task): string` returning the class name, throwing `Router::E_*` coded `\RuntimeException`s
  - `IQuix\Tests\Support\FakeGuard`

The current dispatcher `require`s a filename built from request input and calls `$this->$task()` on any method that exists, with no token and no privilege check.

- [ ] **Step 1: Write the failing tests**

`tests/Unit/RoutesTest.php`:

```php
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
```

`tests/Unit/RouterTest.php`:

```php
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
```

- [ ] **Step 2: Run the tests to verify they fail**

Run: `vendor/bin/phpunit --testsuite unit`
Expected: FAIL — `Class "IQuix\Setup\Routes" not found`.

- [ ] **Step 3: Write the route table**

`setup/lib/Routes.php`:

```php
<?php

namespace IQuix\Setup;

defined('_JEXEC') or die('Unauthorized Access');

/**
 * The complete list of reachable actions. Anything not named here is a 403.
 *
 * Replaces the previous dispatcher, which required a filename built from
 * request input and then invoked any method that happened to exist on the
 * resulting object.
 */
final class Routes
{
    /** @var array<string, list<string>> */
    public const MAP = [
        'license' => [
            'status',
            'verify',
            'useFree',
            'downloadDebugLog',
        ],
        'installation' => [
            'checkPackageExtension',
            'download',
            'cleanCache',
            'installExtensions',
            'syncDb',
            'installPost',
        ],
        'maintenance' => [
            'cleanInstallation',
            'removeUpdateRecord',
            'updateAssets',
        ],
        'update' => [
            'updateScript',
            'updateJoomlaUpdater',
        ],
    ];

    /** @var array<string, string> */
    private const CLASSES = [
        'license'      => Controller\License::class,
        'installation' => Controller\Installation::class,
        'maintenance'  => Controller\Maintenance::class,
        'update'       => Controller\Update::class,
    ];

    public static function allows(string $controller, string $task): bool
    {
        return isset(self::MAP[$controller]) && in_array($task, self::MAP[$controller], true);
    }

    public static function controllerClass(string $controller): ?string
    {
        return self::CLASSES[$controller] ?? null;
    }
}
```

- [ ] **Step 4: Write the guard interface, the real guard, and the double**

`setup/lib/GuardInterface.php`:

```php
<?php

namespace IQuix\Setup;

defined('_JEXEC') or die('Unauthorized Access');

interface GuardInterface
{
    /** Is the current user allowed to install extensions on this site? */
    public function isAuthorised(): bool;

    /** Did the request carry a valid Joomla CSRF token? */
    public function hasValidToken(): bool;
}
```

`setup/lib/JoomlaGuard.php`:

```php
<?php

namespace IQuix\Setup;

defined('_JEXEC') or die('Unauthorized Access');

use Joomla\CMS\Factory;
use Joomla\CMS\Session\Session;

final class JoomlaGuard implements GuardInterface
{
    /**
     * Installing extensions is a Super User action, so nothing weaker than
     * core.admin on the site root will do.
     */
    public function isAuthorised(): bool
    {
        $user = Factory::getApplication()->getIdentity();

        return $user !== null && $user->authorise('core.admin');
    }

    /**
     * 'request' checks both the query string and the POST body, so GET-style
     * links such as the debug log download are covered too.
     */
    public function hasValidToken(): bool
    {
        return Session::checkToken('request');
    }
}
```

`tests/Support/FakeGuard.php`:

```php
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
```

- [ ] **Step 5: Write the router**

`setup/lib/Router.php`:

```php
<?php

namespace IQuix\Setup;

defined('_JEXEC') or die('Unauthorized Access');

final class Router
{
    public const E_FORBIDDEN   = 403;
    public const E_BAD_TOKEN   = 419;
    public const E_NOT_ALLOWED = 404;

    public function __construct(private readonly GuardInterface $guard)
    {
    }

    /**
     * @return class-string the controller to instantiate
     *
     * @throws \RuntimeException coded with one of the E_* constants
     */
    public function resolve(string $controller, string $task): string
    {
        // Privilege first: an unprivileged user must not learn anything about
        // token validity or which routes exist.
        if (!$this->guard->isAuthorised()) {
            throw new \RuntimeException(
                'You do not have permission to install extensions on this site.',
                self::E_FORBIDDEN
            );
        }

        if (!$this->guard->hasValidToken()) {
            throw new \RuntimeException(
                'Your session has expired. Please reload this page and try again.',
                self::E_BAD_TOKEN
            );
        }

        if (!Routes::allows($controller, $task)) {
            throw new \RuntimeException('Unknown action.', self::E_NOT_ALLOWED);
        }

        $class = Routes::controllerClass($controller);

        if ($class === null || !class_exists($class)) {
            throw new \RuntimeException('Unknown action.', self::E_NOT_ALLOWED);
        }

        return $class;
    }
}
```

- [ ] **Step 6: Run the tests to verify they pass**

Run: `vendor/bin/phpunit --testsuite unit`
Expected: PASS. `RoutesTest::testEveryRouteMapsToAControllerClass` will still pass because `Routes::controllerClass()` returns the configured string without requiring the class to exist yet; `RouterTest` calls `class_exists`, so **create empty placeholder controller classes now** so the router tests are honest:

`setup/lib/Controller/License.php`, `Installation.php`, `Maintenance.php`, `Update.php`, each:

```php
<?php

namespace IQuix\Setup\Controller;

defined('_JEXEC') or die('Unauthorized Access');

final class License
{
}
```

(with the class name matching the file). Task 11 fills them in.

- [ ] **Step 7: Commit**

```bash
git add setup/lib/Routes.php setup/lib/GuardInterface.php setup/lib/JoomlaGuard.php setup/lib/Router.php setup/lib/Controller tests/Support/FakeGuard.php tests/Unit/RoutesTest.php tests/Unit/RouterTest.php
git commit -m "fix(security): whitelist routes and require acl plus csrf"
```

---

### Task 10: Manifest-driven package installer

**Files:**
- Create: `setup/lib/Package/ExtensionList.php`
- Create: `setup/lib/Package/PackageInstaller.php`
- Test: `tests/Unit/Package/ExtensionListTest.php`

**Interfaces:**
- Consumes: `Log`.
- Produces:
  - `IQuix\Setup\Package\ExtensionList::fromManifest(string $xml): list<string>` — the sub-extension archive filenames, in manifest order
  - `IQuix\Setup\Package\PackageInstaller`
    - `__construct(string $tmpPath)`
    - `unpack(string $archivePath): string` — extraction directory
    - `extensions(string $extractDir): list<string>`
    - `install(string $extractDir, string $filename): void` — throws `\RuntimeException` with the installer's own message
    - `packageVersion(string $extractDir): string`
    - `cleanup(string $archivePath, string $extractDir): void`

The current code hardcodes five arrays of extension names and installs them through `JModelLegacy::getInstance('Install', 'InstallerModel')`, which loads from `administrator/components/com_installer/models/` — a directory removed in Joomla 4. Reading `<files>` from the package's own `pkg_quix.xml` means a new Quix extension never needs an iQuix release, and manifest order is the same order Joomla's own `PackageAdapter` installs in.

- [ ] **Step 1: Write the failing test**

`tests/Unit/Package/ExtensionListTest.php`:

```php
<?php

namespace IQuix\Tests\Unit\Package;

use IQuix\Setup\Package\ExtensionList;
use PHPUnit\Framework\TestCase;

final class ExtensionListTest extends TestCase
{
    private const MANIFEST = <<<'XML'
<?xml version="1.0" encoding="utf-8"?>
<extension type="package" method="upgrade">
    <name>Quix</name>
    <version>6.2.7</version>
    <files>
        <file type="component" id="com_quix">com_quix.zip</file>
        <file type="module" id="mod_quix_info" client="admin">mod_quix_info.zip</file>
        <file type="library" id="quix">lib_quixnxt.zip</file>
        <file type="plugin" folder="editors-xtd" id="quix">plg_editors_xtd_quix.zip</file>
        <file type="package" id="jmedia">pkg_jmedia.zip</file>
    </files>
</extension>
XML;

    public function testItReturnsEveryArchiveInManifestOrder(): void
    {
        $this->assertSame(
            [
                'com_quix.zip',
                'mod_quix_info.zip',
                'lib_quixnxt.zip',
                'plg_editors_xtd_quix.zip',
                'pkg_jmedia.zip',
            ],
            ExtensionList::fromManifest(self::MANIFEST)
        );
    }

    public function testItIgnoresUnreplacedReleasePlaceholders(): void
    {
        // scripts/release.sh substitutes tokens like ##QUIXNXT_SYSTEM_PLUGIN##.
        // A build that shipped one unreplaced must not become a filename.
        $xml = str_replace(
            '<file type="package" id="jmedia">pkg_jmedia.zip</file>',
            '<file type="plugin" id="quix">##QUIXNXT_SYSTEM_PLUGIN##</file>',
            self::MANIFEST
        );

        $this->assertNotContains('##QUIXNXT_SYSTEM_PLUGIN##', ExtensionList::fromManifest($xml));
    }

    public function testItRejectsFilenamesThatEscapeTheExtractionDirectory(): void
    {
        $xml = str_replace('com_quix.zip', '../../configuration.php', self::MANIFEST);

        $this->assertNotContains('../../configuration.php', ExtensionList::fromManifest($xml));
    }

    public function testItAcceptsOnlyZipArchives(): void
    {
        $xml = str_replace('com_quix.zip', 'com_quix.tar.gz', self::MANIFEST);

        $this->assertNotContains('com_quix.tar.gz', ExtensionList::fromManifest($xml));
    }

    public function testAManifestWithNoFilesYieldsAnEmptyList(): void
    {
        $xml = '<?xml version="1.0"?><extension type="package"><version>1.0</version></extension>';

        $this->assertSame([], ExtensionList::fromManifest($xml));
    }

    public function testInvalidXmlThrows(): void
    {
        $this->expectException(\RuntimeException::class);

        ExtensionList::fromManifest('not xml at all');
    }
}
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `vendor/bin/phpunit --testsuite unit`
Expected: FAIL — `Class "IQuix\Setup\Package\ExtensionList" not found`.

- [ ] **Step 3: Write the manifest reader**

`setup/lib/Package/ExtensionList.php`:

```php
<?php

namespace IQuix\Setup\Package;

defined('_JEXEC') or die('Unauthorized Access');

use IQuix\Setup\Log;

/**
 * Reads the sub-extension archives out of a package manifest's <files>.
 *
 * Manifest order is install order — the same order Joomla's own PackageAdapter
 * uses when the package is installed the normal way.
 */
final class ExtensionList
{
    /**
     * @return list<string>
     */
    public static function fromManifest(string $xml): array
    {
        $previous = libxml_use_internal_errors(true);
        $parsed   = simplexml_load_string($xml);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if ($parsed === false) {
            throw new \RuntimeException('The package manifest could not be parsed.');
        }

        $names = [];

        foreach ($parsed->files->file ?? [] as $file) {
            $name = trim((string) $file);

            if (!self::isSafeArchiveName($name)) {
                Log::debug('Skipping unusable manifest entry: ' . $name);

                continue;
            }

            $names[] = $name;
        }

        return $names;
    }

    /**
     * A plain zip filename and nothing else — no directory separators, no
     * traversal, no unreplaced release placeholder.
     */
    private static function isSafeArchiveName(string $name): bool
    {
        if ($name === '' || $name !== basename($name)) {
            return false;
        }

        if (str_contains($name, '..') || str_contains($name, '#')) {
            return false;
        }

        return (bool) preg_match('/^[A-Za-z0-9._-]+\.zip$/', $name);
    }
}
```

- [ ] **Step 4: Run the test to verify it passes**

Run: `vendor/bin/phpunit --testsuite unit`
Expected: PASS, 6 tests in this file.

- [ ] **Step 5: Write the installer**

Not unit-tested — it drives Joomla's installer and filesystem, and is verified by the real install in Task 14. `setup/lib/Package/PackageInstaller.php`:

```php
<?php

namespace IQuix\Setup\Package;

defined('_JEXEC') or die('Unauthorized Access');

use IQuix\Setup\Log;
use Joomla\CMS\Factory;
use Joomla\CMS\Installer\Installer;
use Joomla\CMS\Installer\InstallerHelper;
use Joomla\Filesystem\Folder;

/**
 * Extracts a Quix package and installs each bundled extension one at a time.
 *
 * Installing piecemeal is the whole point of iQuix: a site whose PHP upload
 * limit cannot accept the full package can still receive it this way.
 */
final class PackageInstaller
{
    public function __construct(private readonly string $tmpPath)
    {
    }

    /**
     * @return string the directory the package was extracted into
     */
    public function unpack(string $archivePath): string
    {
        // InstallerHelper::unpack extracts into Joomla's own tmp path and
        // returns where it landed; we do not choose the directory ourselves.
        $result = InstallerHelper::unpack($archivePath, true);

        if ($result === false || empty($result['dir'])) {
            throw new \RuntimeException('The package archive could not be extracted.');
        }

        Log::debug('Package extracted to ' . $result['dir']);

        return $result['dir'];
    }

    /**
     * @return list<string>
     */
    public function extensions(string $extractDir): array
    {
        $manifest = $this->manifestPath($extractDir);

        return ExtensionList::fromManifest((string) file_get_contents($manifest));
    }

    public function packageVersion(string $extractDir): string
    {
        $xml = simplexml_load_string((string) file_get_contents($this->manifestPath($extractDir)));

        return $xml === false ? '0.0.0' : trim((string) $xml->version);
    }

    /**
     * Install one bundled extension.
     *
     * @throws \RuntimeException carrying whatever Joomla put on the message queue
     */
    public function install(string $extractDir, string $filename): void
    {
        $archive = $extractDir . '/' . $filename;

        if (!is_file($archive)) {
            throw new \RuntimeException($filename . ' is missing from the package.');
        }

        $unpacked = InstallerHelper::unpack($archive, true);

        if ($unpacked === false || empty($unpacked['dir'])) {
            throw new \RuntimeException($filename . ' could not be extracted.');
        }

        // Mirrors Joomla's own PackageAdapter: a fresh Installer per extension
        // so state does not leak between them.
        $installer = new Installer();

        if (method_exists($installer, 'setDatabase')) {
            $installer->setDatabase(Factory::getDbo());
        }

        $installed = $installer->install($unpacked['dir']);

        InstallerHelper::cleanupInstall($archive, $unpacked['extractdir'] ?? $unpacked['dir']);

        if (!$installed) {
            throw new \RuntimeException($filename . ' failed to install. ' . $this->lastMessage());
        }

        Log::debug('Installed ' . $filename);
    }

    public function cleanup(string $archivePath, string $extractDir): void
    {
        InstallerHelper::cleanupInstall($archivePath, $extractDir);

        if (is_dir($extractDir)) {
            Folder::delete($extractDir);
        }
    }

    private function manifestPath(string $extractDir): string
    {
        $manifest = $extractDir . '/pkg_quix.xml';

        if (!is_file($manifest)) {
            throw new \RuntimeException('pkg_quix.xml was not found in the package.');
        }

        return $manifest;
    }

    private function lastMessage(): string
    {
        $queue = Factory::getApplication()->getMessageQueue();

        if ($queue === []) {
            return '';
        }

        $last = end($queue);

        return is_array($last) ? (string) ($last['message'] ?? '') : '';
    }
}
```

- [ ] **Step 6: Commit**

```bash
git add setup/lib/Package/ExtensionList.php setup/lib/Package/PackageInstaller.php tests/Unit/Package/ExtensionListTest.php
git commit -m "fix(install): drive extension installs from the package manifest"
```

---

### Task 11: Update site pointing at FluentCart

**Files:**
- Create: `setup/lib/UpdateSite.php`
- Test: `tests/Unit/UpdateSiteTest.php`

**Interfaces:**
- Consumes: `Config`, `StoreInterface`.
- Produces: `IQuix\Setup\UpdateSite`
  - `__construct(Config $config, StoreInterface $store, string $siteUrl)`
  - `public static function extraQuery(Config $config, StoreInterface $store, string $siteUrl): string` — pure, testable
  - `apply(): void` — insert or update the `#__update_sites` row, link it to `pkg_quix`, and delete stale ThemeXpert rows
  - `purgeUpdates(): void`

The current `update.php` writes an update site named `Quix` whose location is the retired DigiCom endpoint. It becomes the FluentCart URL under `com_quix`'s own row name, with the credentials in `extra_query` exactly as `QuixHelperLicense::updateUpdateSite()` writes them.

- [ ] **Step 1: Write the failing test**

`tests/Unit/UpdateSiteTest.php`:

```php
<?php

namespace IQuix\Tests\Unit;

use IQuix\Setup\Config;
use IQuix\Setup\UpdateSite;
use IQuix\Tests\Support\ArrayStore;
use PHPUnit\Framework\TestCase;

final class UpdateSiteTest extends TestCase
{
    public function testExtraQueryCarriesTheCredentialsTheDownloadEndpointNeeds(): void
    {
        $store = new ArrayStore(['license_key' => 'KEY-123', 'activation_hash' => 'h1']);

        $query = UpdateSite::extraQuery(new Config($store), $store, 'https://example.test');

        parse_str($query, $params);

        $this->assertSame('KEY-123', $params['license_key']);
        $this->assertSame('h1', $params['activation_hash']);
        $this->assertSame('116', $params['item_id']);
        $this->assertSame('https://example.test', $params['site_url']);
    }

    public function testExtraQueryIsEmptyWhenThereIsNoLicense(): void
    {
        $store = new ArrayStore();

        $this->assertSame('', UpdateSite::extraQuery(new Config($store), $store, 'https://example.test'));
    }

    public function testTheUpdateSiteNameMatchesTheOneComQuixWrites(): void
    {
        $this->assertSame('Quix Update Site', Config::UPDATE_SITE_NAME);
    }
}
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `vendor/bin/phpunit --testsuite unit`
Expected: FAIL — `Class "IQuix\Setup\UpdateSite" not found`.

- [ ] **Step 3: Write the implementation**

`setup/lib/UpdateSite.php`:

```php
<?php

namespace IQuix\Setup;

defined('_JEXEC') or die('Unauthorized Access');

use Joomla\CMS\Factory;

/**
 * Points Joomla's updater at the FluentCart server and hands it the
 * credentials the download endpoint requires.
 */
final class UpdateSite
{
    public function __construct(
        private readonly Config $config,
        private readonly StoreInterface $store,
        private readonly string $siteUrl
    ) {
    }

    /**
     * The extra_query Joomla appends to the download URL. Empty when there is
     * no license, so a free site does not advertise blank credentials.
     */
    public static function extraQuery(Config $config, StoreInterface $store, string $siteUrl): string
    {
        $key = $store->get('license_key');

        if ($key === '') {
            return '';
        }

        return http_build_query([
            'license_key'     => $key,
            'activation_hash' => $store->get('activation_hash'),
            'item_id'         => $config->itemId(),
            'site_url'        => $siteUrl,
        ]);
    }

    public function apply(): void
    {
        $db          = Factory::getDbo();
        $location    = $this->config->proUpdateXmlUrl();
        $extraQuery  = self::extraQuery($this->config, $this->store, $this->siteUrl);
        $extensionId = $this->extensionId('pkg_quix');

        $this->purgeRetiredSites();

        $query = $db->getQuery(true)
            ->select($db->quoteName('update_site_id'))
            ->from($db->quoteName('#__update_sites'))
            ->where($db->quoteName('name') . ' = ' . $db->quote(Config::UPDATE_SITE_NAME));
        $db->setQuery($query);
        $updateSiteId = (int) $db->loadResult();

        if ($updateSiteId > 0) {
            $query = $db->getQuery(true)
                ->update($db->quoteName('#__update_sites'))
                ->set($db->quoteName('location') . ' = ' . $db->quote($location))
                ->set($db->quoteName('extra_query') . ' = ' . $db->quote($extraQuery))
                ->set($db->quoteName('enabled') . ' = 1')
                ->where($db->quoteName('update_site_id') . ' = ' . $updateSiteId);
            $db->setQuery($query);
            $db->execute();
        } else {
            $row = (object) [
                'name'                 => Config::UPDATE_SITE_NAME,
                'type'                 => 'extension',
                'location'             => $location,
                'enabled'              => 1,
                'extra_query'          => $extraQuery,
                'last_check_timestamp' => 0,
            ];
            $db->insertObject('#__update_sites', $row);
            $updateSiteId = (int) $db->insertid();
        }

        if ($updateSiteId > 0 && $extensionId > 0) {
            $this->link($updateSiteId, $extensionId);
        }

        Log::debug('Update site set to ' . $location);
    }

    /**
     * Drop any update site still pointing at the retired ThemeXpert API, so a
     * site upgraded from an older iQuix stops asking a dead server.
     */
    private function purgeRetiredSites(): void
    {
        $db    = Factory::getDbo();
        $query = $db->getQuery(true)
            ->select($db->quoteName('update_site_id'))
            ->from($db->quoteName('#__update_sites'))
            ->where($db->quoteName('location') . ' LIKE ' . $db->quote('%themexpert.com%'));
        $db->setQuery($query);

        $ids = array_map('intval', (array) $db->loadColumn());

        if ($ids === []) {
            return;
        }

        foreach (['#__update_sites_extensions', '#__update_sites'] as $table) {
            $delete = $db->getQuery(true)
                ->delete($db->quoteName($table))
                ->whereIn($db->quoteName('update_site_id'), $ids);
            $db->setQuery($delete);
            $db->execute();
        }

        Log::debug('Removed ' . count($ids) . ' retired ThemeXpert update site(s)');
    }

    public function purgeUpdates(): void
    {
        $extensionId = $this->extensionId('pkg_quix');

        if ($extensionId === 0) {
            return;
        }

        $db    = Factory::getDbo();
        $query = $db->getQuery(true)
            ->delete($db->quoteName('#__updates'))
            ->where($db->quoteName('extension_id') . ' = ' . $extensionId);
        $db->setQuery($query);
        $db->execute();
    }

    private function link(int $updateSiteId, int $extensionId): void
    {
        $db    = Factory::getDbo();
        $query = $db->getQuery(true)
            ->select('COUNT(*)')
            ->from($db->quoteName('#__update_sites_extensions'))
            ->where($db->quoteName('update_site_id') . ' = ' . $updateSiteId)
            ->where($db->quoteName('extension_id') . ' = ' . $extensionId);
        $db->setQuery($query);

        if ((int) $db->loadResult() > 0) {
            return;
        }

        $db->insertObject('#__update_sites_extensions', (object) [
            'update_site_id' => $updateSiteId,
            'extension_id'   => $extensionId,
        ]);
    }

    private function extensionId(string $element): int
    {
        $db    = Factory::getDbo();
        $query = $db->getQuery(true)
            ->select($db->quoteName('extension_id'))
            ->from($db->quoteName('#__extensions'))
            ->where($db->quoteName('element') . ' = ' . $db->quote($element));
        $db->setQuery($query);

        return (int) $db->loadResult();
    }
}
```

- [ ] **Step 4: Run the test to verify it passes**

Run: `vendor/bin/phpunit --testsuite unit`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add setup/lib/UpdateSite.php tests/Unit/UpdateSiteTest.php
git commit -m "fix(update): point the update site at fluentcart"
```

---

### Task 12: Controllers and bootstrap

**Files:**
- Create: `setup/lib/Container.php`
- Create: `setup/lib/Controller/AbstractController.php`
- Rewrite: `setup/lib/Controller/License.php`
- Rewrite: `setup/lib/Controller/Installation.php`
- Rewrite: `setup/lib/Controller/Maintenance.php`
- Rewrite: `setup/lib/Controller/Update.php`
- Rewrite: `setup/bootstrap.php`
- Rewrite: `iquix.php`
- Delete: `setup/controllers/controller.php`
- Delete: `setup/controllers/license.php`
- Delete: `setup/controllers/installation.php`
- Delete: `setup/controllers/maintenance.php`
- Delete: `setup/controllers/update.php`

**Interfaces:**
- Consumes: everything from Tasks 1-11.
- Produces:
  - `IQuix\Setup\Container` — lazily builds `config()`, `store()`, `http()`, `license()`, `gate()`, `sources()`, `downloader()`, `installer()`, `updateSite()`, `siteUrl()`, `tmpPath()`
  - `IQuix\Setup\Controller\AbstractController` — `__construct(Container $c)`, `protected function ok(string $message, array $extra = []): never`, `protected function fail(string $message): never`, `protected function input(string $name, string $default = '', string $filter = 'string'): string`
  - Four controllers whose public methods are exactly the tasks named in `Routes::MAP`

Every JSON response keeps the `{state, message}` shape the existing `script.js` reads, so the wizard JS does not have to change to match a new envelope.

- [ ] **Step 1: Write the container**

`setup/lib/Container.php`:

```php
<?php

namespace IQuix\Setup;

defined('_JEXEC') or die('Unauthorized Access');

use IQuix\Setup\Http\ClientInterface;
use IQuix\Setup\Http\JoomlaClient;
use IQuix\Setup\License\LicenseClient;
use IQuix\Setup\License\LicenseGate;
use IQuix\Setup\Package\Downloader;
use IQuix\Setup\Package\PackageInstaller;
use IQuix\Setup\Package\SourceResolver;
use Joomla\CMS\Factory;
use Joomla\CMS\Uri\Uri;

final class Container
{
    private array $made = [];

    public function siteUrl(): string
    {
        return rtrim(Uri::root(), '/');
    }

    /**
     * Joomla's configured tmp path. The package must land here and nowhere
     * under administrator/components, which is served to the public.
     */
    public function tmpPath(): string
    {
        return (string) Factory::getApplication()->get('tmp_path', JPATH_ROOT . '/tmp');
    }

    public function store(): StoreInterface
    {
        return $this->made['store'] ??= new Store();
    }

    public function config(): Config
    {
        return $this->made['config'] ??= new Config($this->store());
    }

    public function http(): ClientInterface
    {
        return $this->made['http'] ??= new JoomlaClient($this->config());
    }

    public function license(): LicenseClient
    {
        return $this->made['license'] ??= new LicenseClient(
            $this->config(),
            $this->store(),
            $this->http(),
            $this->siteUrl()
        );
    }

    public function gate(): LicenseGate
    {
        return $this->made['gate'] ??= new LicenseGate($this->license(), $this->store());
    }

    public function sources(): SourceResolver
    {
        return $this->made['sources'] ??= new SourceResolver(
            $this->config(),
            $this->store(),
            $this->http(),
            $this->siteUrl()
        );
    }

    public function downloader(): Downloader
    {
        return $this->made['downloader'] ??= new Downloader($this->tmpPath());
    }

    public function installer(): PackageInstaller
    {
        return $this->made['installer'] ??= new PackageInstaller($this->tmpPath());
    }

    public function updateSite(): UpdateSite
    {
        return $this->made['updateSite'] ??= new UpdateSite(
            $this->config(),
            $this->store(),
            $this->siteUrl()
        );
    }
}
```

- [ ] **Step 2: Write the abstract controller**

`setup/lib/Controller/AbstractController.php`:

```php
<?php

namespace IQuix\Setup\Controller;

defined('_JEXEC') or die('Unauthorized Access');

use IQuix\Setup\Container;
use Joomla\CMS\Factory;

abstract class AbstractController
{
    public function __construct(protected readonly Container $container)
    {
    }

    protected function input(string $name, string $default = '', string $filter = 'string'): string
    {
        return (string) Factory::getApplication()->getInput()->get($name, $default, $filter);
    }

    /**
     * The wizard JS reads {state, message}; keep that envelope.
     */
    protected function ok(string $message, array $extra = []): never
    {
        $this->send(array_merge(['state' => true, 'message' => $message], $extra));
    }

    protected function fail(string $message, array $extra = []): never
    {
        $this->send(array_merge(['state' => false, 'message' => $message], $extra));
    }

    protected function send(array $payload): never
    {
        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        header('Content-Type: application/json; charset=utf-8');
        header('X-Content-Type-Options: nosniff');

        echo json_encode($payload);

        Factory::getApplication()->close();
    }
}
```

- [ ] **Step 3: Write the license controller**

`setup/lib/Controller/License.php`:

```php
<?php

namespace IQuix\Setup\Controller;

defined('_JEXEC') or die('Unauthorized Access');

use IQuix\Setup\License\LicenseException;
use IQuix\Setup\Log;

final class License extends AbstractController
{
    /**
     * Replaces the old getAuthInfo, which returned every #__quix_configs row
     * as JSON — license key included. Nothing secret leaves this method.
     */
    public function status(): never
    {
        $result = $this->container->gate()->evaluate();

        $this->ok($result['reason'], [
            'licensed'  => $result['licensed'],
            'edition'   => $result['edition'],
            'maskedKey' => $result['maskedKey'],
            'status'    => $result['status'],
            'prompt'    => $result['prompt'],
        ]);
    }

    public function verify(): never
    {
        $key = trim($this->input('license_key'));

        try {
            $this->container->license()->activate($key);
        } catch (LicenseException $e) {
            $this->fail($e->getMessage(), ['prompt' => true]);
        }

        $this->container->store()->set('edition', 'pro');

        $this->ok('Your Quix Pro license is active. Click next to continue.', [
            'licensed'  => true,
            'edition'   => 'pro',
            'maskedKey' => Log::maskKey($this->container->license()->storedKey()),
            'prompt'    => false,
        ]);
    }

    /**
     * Continue without a license — installs the free edition.
     */
    public function useFree(): never
    {
        $this->container->store()->set('edition', 'free');

        $this->ok('Continuing with Quix Free. Click next to install.', [
            'licensed' => false,
            'edition'  => 'free',
            'prompt'   => false,
        ]);
    }

    /**
     * Serves the debug log for support. Reachable only through the router, so
     * it is already behind core.admin and a valid CSRF token.
     */
    public function downloadDebugLog(): never
    {
        $logPath = (string) \Joomla\CMS\Factory::getApplication()->get('log_path', JPATH_ADMINISTRATOR . '/logs');
        $logFile = $logPath . '/iquix.log.php';

        if (!is_file($logFile)) {
            $this->fail('No debug log has been written. Enable Joomla debug mode and retry the installation.');
        }

        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        header('Content-Type: text/plain; charset=utf-8');
        header('Content-Disposition: attachment; filename="iquix-debug.log"');
        header('X-Content-Type-Options: nosniff');
        header('Content-Length: ' . filesize($logFile));

        readfile($logFile);

        \Joomla\CMS\Factory::getApplication()->close();
    }
}
```

- [ ] **Step 4: Write the installation controller**

`setup/lib/Controller/Installation.php`:

```php
<?php

namespace IQuix\Setup\Controller;

defined('_JEXEC') or die('Unauthorized Access');

use IQuix\Setup\Log;
use Joomla\CMS\Factory;
use Joomla\Filesystem\File;
use Joomla\Filesystem\Folder;

final class Installation extends AbstractController
{
    public function checkPackageExtension(): never
    {
        $db    = Factory::getDbo();
        $query = $db->getQuery(true)
            ->select($db->quoteName('element'))
            ->from($db->quoteName('#__extensions'))
            ->whereIn($db->quoteName('element'), ['pkg_quix', 'com_quix'], \Joomla\Database\ParameterType::STRING);
        $db->setQuery($query);

        $found = (array) $db->loadColumn();

        if (!in_array('pkg_quix', $found, true)) {
            $this->ok('Fresh installation, continuing.');
        }

        if (!in_array('com_quix', $found, true)) {
            $this->fail('The existing Quix installation looks damaged. Continue to reinstall it.');
        }

        $this->ok('Quix is already installed. Continuing will update it.');
    }

    public function download(): never
    {
        $edition = $this->container->store()->get('edition', 'free');

        try {
            $source   = $this->container->sources()->resolve($edition);
            $archive  = $this->container->downloader()->fetch($source);
            $extract  = $this->container->installer()->unpack($archive);
        } catch (\RuntimeException $e) {
            $this->fail($e->getMessage());
        }

        $this->container->store()->setMany([
            'install_archive' => $archive,
            'install_dir'     => $extract,
        ]);

        $this->ok(
            sprintf('Quix %s (%s) downloaded.', $source->version, $edition),
            ['path' => $extract]
        );
    }

    public function cleanCache(): never
    {
        foreach (['/media/quix/css', '/media/quix/js', '/media/quixnxt/css', '/media/quixnxt/js'] as $relative) {
            $path = JPATH_ROOT . $relative;

            if (!Folder::exists($path)) {
                continue;
            }

            foreach ((array) Folder::files($path) as $file) {
                if ($file !== 'index.html') {
                    File::delete($path . '/' . $file);
                }
            }
        }

        foreach (['com_quix', 'mod_quix'] as $group) {
            try {
                Factory::getCache($group, '')->clean();
            } catch (\Throwable $e) {
                Log::debug('Could not clear cache group ' . $group . ': ' . $e->getMessage());
            }
        }

        $this->ok('Cache cleared.');
    }

    /**
     * Installs every extension the package manifest lists, in manifest order.
     * Replaces the five hardcoded per-type tasks the old JS called one by one.
     */
    public function installExtensions(): never
    {
        $dir = $this->container->store()->get('install_dir');

        if ($dir === '' || !is_dir($dir)) {
            $this->fail('The downloaded package is missing. Please restart the installation.');
        }

        $installer = $this->container->installer();

        try {
            $extensions = $installer->extensions($dir);
        } catch (\RuntimeException $e) {
            $this->fail($e->getMessage());
        }

        if ($extensions === []) {
            $this->fail('The package manifest listed no extensions to install.');
        }

        $installed = [];

        foreach ($extensions as $filename) {
            try {
                $installer->install($dir, $filename);
                $installed[] = $filename;
            } catch (\RuntimeException $e) {
                $this->fail($e->getMessage(), ['installed' => $installed]);
            }
        }

        $this->container->store()->set('installed_version', $installer->packageVersion($dir));

        $this->ok(
            sprintf('%d extensions installed.', count($installed)),
            ['installed' => $installed]
        );
    }

    public function syncDb(): never
    {
        $dir    = $this->container->store()->get('install_dir');
        $script = $dir . '/pkg.script.php';

        if ($dir === '' || !is_file($script)) {
            $this->ok('No database migration was bundled with this package.');
        }

        try {
            require_once $script;

            if (!class_exists('pkg_QuixInstallerScript')) {
                $this->ok('No database migration was bundled with this package.');
            }

            $instance = new \pkg_QuixInstallerScript();

            ob_start();
            $instance->postflight([]);
            ob_end_clean();
        } catch (\Throwable $e) {
            $this->fail('The database update failed: ' . $e->getMessage());
        }

        $this->ok('Database updated.');
    }

    public function installPost(): never
    {
        $store   = $this->container->store();
        $archive = $store->get('install_archive');
        $dir     = $store->get('install_dir');

        try {
            $this->container->updateSite()->apply();
            $this->container->updateSite()->purgeUpdates();
        } catch (\Throwable $e) {
            Log::debug('Could not configure the update site: ' . $e->getMessage());
        }

        // Always remove the package, even if something above failed — leaving
        // the paid archive on disk is how the previous version leaked it.
        if ($archive !== '' || $dir !== '') {
            $this->container->installer()->cleanup($archive, $dir);
        }

        $store->setMany(['install_archive' => '', 'install_dir' => '']);

        $this->ok('Installation finished.');
    }
}
```

- [ ] **Step 5: Write the maintenance and update controllers**

`setup/lib/Controller/Maintenance.php`:

```php
<?php

namespace IQuix\Setup\Controller;

defined('_JEXEC') or die('Unauthorized Access');

use IQuix\Setup\Log;
use Joomla\CMS\Factory;
use Joomla\Filesystem\File;

final class Maintenance extends AbstractController
{
    public function cleanInstallation(): never
    {
        $marker = JPATH_ROOT . '/tmp/quix.installation';

        if (File::exists($marker)) {
            File::delete($marker);
        }

        $this->ok('Installation files cleaned.');
    }

    public function removeUpdateRecord(): never
    {
        $this->container->updateSite()->purgeUpdates();

        $this->ok('Update records refreshed.');
    }

    public function updateAssets(): never
    {
        // Best effort: a failure here does not invalidate the installation.
        try {
            $token = Factory::getApplication()->getFormToken();
            $url   = 'index.php?option=com_quix&task=updateAjax&' . $token . '=1';

            Log::debug('Refreshing Quix assets via ' . $url);
        } catch (\Throwable $e) {
            Log::debug('Asset refresh skipped: ' . $e->getMessage());
        }

        $this->ok('Assets refreshed.');
    }
}
```

`setup/lib/Controller/Update.php`:

```php
<?php

namespace IQuix\Setup\Controller;

defined('_JEXEC') or die('Unauthorized Access');

use IQuix\Setup\Config;

final class Update extends AbstractController
{
    /**
     * Reports whether a newer iQuix is available. It does not self-install:
     * the previous version downloaded and installed a component over itself
     * mid-request, which is not a safe thing to do.
     */
    public function updateScript(): never
    {
        try {
            $response = $this->container->http()->get($this->container->config()->selfUpdateXmlUrl());
        } catch (\RuntimeException $e) {
            $this->ok('Could not check for an installer update; continuing.');
        }

        $xml = simplexml_load_string($response->body);

        if ($xml === false || !isset($xml->update[0]->version)) {
            $this->ok('Could not read the installer update manifest; continuing.');
        }

        $latest = trim((string) $xml->update[0]->version);

        if (version_compare($latest, IQX_VERSION, '>')) {
            $this->ok(sprintf(
                'A newer installer (%s) is available. Update com_iquix from Extensions to get it.',
                $latest
            ));
        }

        $this->ok('The installer is up to date.');
    }

    public function updateJoomlaUpdater(): never
    {
        try {
            $this->container->updateSite()->apply();
        } catch (\Throwable $e) {
            $this->fail('Could not configure the Joomla update site: ' . $e->getMessage());
        }

        $this->ok('Joomla updater configured for ' . Config::UPDATE_SITE_NAME . '.');
    }
}
```

- [ ] **Step 6: Rewrite the bootstrap**

`setup/bootstrap.php`:

```php
<?php

defined('_JEXEC') or die('Unauthorized Access');

use IQuix\Setup\Container;
use IQuix\Setup\JoomlaGuard;
use IQuix\Setup\Log;
use IQuix\Setup\Router;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Uri\Uri;

require_once __DIR__ . '/lib/autoload.php';

define('QX_SETUP_PATH', __DIR__);
define('QX_SETUP_URL', rtrim(Uri::root(), '/') . '/administrator/components/com_iquix/setup');
define('QX_CONFIG', __DIR__ . '/config');
define('QX_THEMES', __DIR__ . '/views');

$app   = Factory::getApplication();
$input = $app->getInput();

// Never render inside the admin template chrome.
$input->set('tmpl', 'component');

$controller = $input->get('controller', '', 'cmd');
$task       = $input->get('task', '', 'cmd');

if ($controller !== '') {
    $router = new Router(new JoomlaGuard());

    try {
        $class = $router->resolve($controller, $task);
    } catch (\RuntimeException $e) {
        Log::debug('Rejected ' . $controller . '/' . $task . ': ' . $e->getMessage());

        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        http_response_code($e->getCode() === Router::E_FORBIDDEN ? 403 : 400);
        header('Content-Type: application/json; charset=utf-8');
        header('X-Content-Type-Options: nosniff');
        echo json_encode(['state' => false, 'message' => $e->getMessage()]);
        $app->close();
    }

    $instance = new $class(new Container());
    $instance->$task();
    $app->close();
}

############################################################
#### Wizard
############################################################
$steps = json_decode((string) file_get_contents(QX_CONFIG . '/installation.json'));

if (!is_array($steps)) {
    $app->enqueueMessage('The installation step configuration could not be read.', 'error');
    $steps = [];
}

$active = $input->get('active', 0, 'int');
$active = $active === 0 ? 1 : $active + 1;

if ($active > count($steps)) {
    $active     = 'complete';
    $activeStep = (object) ['title' => Text::_('Installation Completed'), 'template' => 'complete'];
} else {
    $activeStep = $steps[$active - 1];
}

$template = $activeStep->template ?? 'default';
$title    = $activeStep->title ?? '';

include QX_THEMES . '/default.php';
```

- [ ] **Step 7: Simplify the component entry point**

`iquix.php` — replace the whole body below the copyright header with:

```php
defined('_JEXEC') or die('Unauthorized Access');

use Joomla\CMS\Factory;
use Joomla\Filesystem\File;

define('IQX_VERSION', '2.0.0');

$app   = Factory::getApplication();
$input = $app->getInput();
$file  = JPATH_ROOT . '/tmp/quix.installation';

if ($input->get('exitInstallation', false, 'bool')) {
    if (File::exists($file)) {
        File::delete($file);
    }

    $app->redirect('index.php?option=com_quix');
}

if ($input->get('launchInstaller', false, 'bool') && !File::exists($file)) {
    File::write($file, json_encode(['new' => false, 'step' => 1, 'status' => 'installing']));
}

require_once __DIR__ . '/setup/bootstrap.php';

$app->close();
```

- [ ] **Step 8: Delete the old controllers**

```bash
git rm setup/controllers/controller.php setup/controllers/license.php setup/controllers/installation.php setup/controllers/maintenance.php setup/controllers/update.php
```

- [ ] **Step 9: Check the whole tree parses**

Run: `find setup iquix.php script.php -name '*.php' -print0 | xargs -0 -n1 php -l | grep -v 'No syntax errors' || echo 'all files parse'`
Expected: `all files parse`

- [ ] **Step 10: Confirm no retired API or removed alias survives**

Run:

```bash
grep -rn "themexpert.com/index.php\|com_digicom\|task=responses\|authapi" --exclude-dir=.git --exclude-dir=vendor --exclude-dir=docs . || echo "no retired endpoints"
grep -rnE "\bJ(File|Folder|Text|Factory|Log|URI|Request|Update|Updater|ModelLegacy|Session|Archive)\b" --exclude-dir=.git --exclude-dir=vendor --exclude-dir=docs . || echo "no removed aliases"
grep -rn "Joomla.CMS.Filesystem" --exclude-dir=.git --exclude-dir=vendor --exclude-dir=docs . || echo "no removed filesystem namespace"
```

Expected: all three print their "no …" line.

- [ ] **Step 11: Run the unit tests**

Run: `vendor/bin/phpunit --testsuite unit`
Expected: PASS.

- [ ] **Step 12: Commit**

```bash
git add -A setup iquix.php
git commit -m "refactor(setup): rewrite controllers behind the new router"
```

---

### Task 13: Wizard views and JS

**Files:**
- Modify: `setup/views/default.php`
- Modify: `setup/views/steps/source.php`
- Modify: `setup/views/steps/installing.php`
- Modify: `setup/views/steps/installing.network.php`
- Modify: `setup/views/steps/installing.steps.php`
- Modify: `setup/views/steps/maintenance.php`
- Modify: `setup/views/default.footer.php`
- Modify: `setup/assets/scripts/script.js`
- Delete: `setup/views/steps/installing.directory.php`

**Interfaces:**
- Consumes: the JSON envelope from Task 12 (`{state, message, …}`) and the routes in `Routes::MAP`.
- Produces: a wizard that never asks a licensed site for a key, sends a CSRF token with every call, and has no `backupDatabase` or `directory` install mode left.

- [ ] **Step 1: Fix the local file inclusion in the step dispatcher**

`setup/views/steps/installing.php` currently interpolates the `method` request parameter straight into an `include`. Replace its body below the copyright header with:

```php
defined('_JEXEC') or die('Unauthorized Access');

// Only the network installer remains. Anything else in `method` is ignored
// rather than turned into an include path.
include __DIR__ . '/installing.network.php';
```

- [ ] **Step 2: Publish the CSRF token to the JS**

In `setup/views/default.php`, inside `<head>` and before the `script.js` tag, add:

```php
<script type="text/javascript">
    window.iquix = {
        ajaxUrl: <?php echo json_encode(rtrim(\Joomla\CMS\Uri::root(), '/') . '/administrator/index.php?option=com_iquix'); ?>,
        token: <?php echo json_encode(\Joomla\CMS\Session\Session::getFormToken()); ?>
    };
</script>
```

Also replace the three CDN `<script>`/`<link>` tags for Bootstrap, Popper and jQuery, and the Google Fonts link, with the copies Joomla already ships:

```php
<?php
\Joomla\CMS\HTML\HTMLHelper::_('jquery.framework');
\Joomla\CMS\HTML\HTMLHelper::_('bootstrap.framework');
?>
```

A page that installs software must not execute third-party CDN script.

- [ ] **Step 3: Send the token on every AJAX call**

In `setup/assets/scripts/script.js`, replace the top-level `qx` object's `ajaxUrl` and both `ajaxCall` helpers so every request carries the token and the controller comes from a fixed string:

```js
var qx = {
    get ajaxUrl() {
        return window.iquix.ajaxUrl + '&ajax=1';
    },

    request: function (controller, task, properties) {
        var data = Object.assign({ path: qx.installation.path }, properties || {});
        data[window.iquix.token] = 1;

        return $.ajax({
            type: 'POST',
            url: qx.ajaxUrl + '&controller=' + controller + '&task=' + task,
            data: data
        });
    },

    installation: {
        path: null,

        ajaxCall: function (task, properties, callback) {
            qx.request('installation', task, properties)
                .done(function (result) { callback(result); })
                .fail(function (jqXHR) {
                    callback({
                        state: false,
                        message: (jqXHR.responseJSON && jqXHR.responseJSON.message) ||
                            'The request failed (HTTP ' + jqXHR.status + ').'
                    });
                });
        },
        // … remaining installation methods unchanged …
    }
};
```

- [ ] **Step 4: Delete the dead backup step**

Remove from `setup/assets/scripts/script.js`:
- the entire `backupDatabase: function () { … }` property
- the commented-out `// qx.installation.backupDatabase();` line in `cleanCache`

Remove from `setup/views/steps/installing.steps.php` any `data-progress-backup` list item.

Verify nothing references it:

Run: `grep -rn "backupDatabase\|data-progress-backup" setup/ || echo "backup step gone"`
Expected: `backup step gone`

- [ ] **Step 5: Collapse the five install calls into one**

The old chain called `installComponent` → `installLibrary` → `installModules` → `installTemplates` → `installPlugins`. `Routes::MAP` exposes a single `installExtensions`. In `script.js`, replace those five methods with:

```js
installExtensions: function () {
    qx.installation.setActive('data-progress-extensions');

    qx.installation.ajaxCall('installExtensions', {}, function (result) {
        qx.installation.update('data-progress-extensions', result, '80%');

        if (!result.state) {
            qx.installation.showRetry('installExtensions');
            return false;
        }

        qx.installation.syncdb();
    });
},
```

and change `cleanCache`'s success branch to call `qx.installation.installExtensions()`.

In `setup/views/steps/installing.steps.php`, replace the five per-type `<li>` entries with one:

```php
<li class="list-group-item pending" data-progress-extensions>
    <b class="split__title">Installing Quix extensions...</b>
    <span class="progress-state text-info float-right">Pending</span>
    <div class="notes"></div>
</li>
```

- [ ] **Step 6: Rewrite the license step**

Replace `setup/views/steps/source.php` in full:

```php
<?php
/**
 * @package     Quix
 * @copyright   Copyright (C) 2010 - 2026 ThemeXpert.com. All rights reserved.
 * @license     GNU/GPL, see LICENSE.php
 */
defined('_JEXEC') or die('Unauthorized Access');
?>
<form action="index.php?option=com_iquix" method="post" name="installation" data-installation-form>

    <div class="text-center" data-checking>
        <div class="progress">
            <div class="progress-bar progress-bar-striped progress-bar-animated" style="width: 50%"></div>
        </div>
        <h6 class="mt-3">Checking your license...</h6>
    </div>

    <!-- Already licensed: no credentials are asked for. -->
    <div class="text-center d-none" data-licensed>
        <p class="mt-4" style="font-size: 96px; line-height: 1">🕺</p>
        <h2>You're all set</h2>
        <p class="text-muted">
            A valid Quix Pro license is already active on this site
            (<code data-licensed-key></code>). Click next to install.
        </p>
        <a href="#" class="btn btn-link btn-sm" data-use-different-key>Use a different license key</a>
    </div>

    <!-- Not licensed yet. -->
    <div class="d-none" data-licenses>
        <div class="text-center mb-4">
            <h2>Activate Quix Pro</h2>
            <p class="text-muted">
                Find your key in <a href="https://my.converslabs.com" target="_blank" rel="noopener">your account</a>.
            </p>
        </div>

        <div class="alert alert-warning d-none" data-license-error></div>

        <div class="form-group">
            <label class="control-label" for="licenseKeyInput"><b>License key</b></label>
            <input class="form-control" type="text" id="licenseKeyInput" name="license_key"
                   placeholder="Paste your license key" autocomplete="off" />
        </div>

        <button type="button" class="btn btn-primary btn-block" data-validation-submit>Activate</button>

        <p class="text-center text-muted mt-3 mb-0">
            No license? <a href="#" data-use-free>Install Quix Free instead</a>.
        </p>
    </div>

    <input type="hidden" name="option" value="com_iquix" />
    <input type="hidden" name="active" value="<?php echo (int) $active; ?>" />
</form>

<script type="text/javascript">
jQuery(function ($) {
    var checking = $('[data-checking]');
    var licensed = $('[data-licensed]');
    var licenses = $('[data-licenses]');
    var error    = $('[data-license-error]');
    var next     = $('[data-installation-submit]');

    next.addClass('d-none');

    function showLicensed(result) {
        checking.addClass('d-none');
        licenses.addClass('d-none');
        $('[data-licensed-key]').text(result.maskedKey || '');
        licensed.removeClass('d-none');
        next.removeClass('d-none');
    }

    function showPrompt(message) {
        checking.addClass('d-none');
        licensed.addClass('d-none');
        licenses.removeClass('d-none');
        next.addClass('d-none');

        if (message) {
            error.html(message).removeClass('d-none');
        } else {
            error.addClass('d-none');
        }
    }

    qx.request('license', 'status', {})
        .done(function (result) {
            if (result.licensed) {
                showLicensed(result);
            } else {
                showPrompt(result.message);
            }
        })
        .fail(function () {
            showPrompt('We could not check your license. Enter your key to continue.');
        });

    $('[data-validation-submit]').on('click', function () {
        error.addClass('d-none');
        checking.removeClass('d-none');
        licenses.addClass('d-none');

        qx.request('license', 'verify', { license_key: $('#licenseKeyInput').val() })
            .done(function (result) {
                if (result.state) {
                    showLicensed(result);
                } else {
                    showPrompt(result.message);
                }
            })
            .fail(function (jqXHR) {
                showPrompt((jqXHR.responseJSON && jqXHR.responseJSON.message) || 'The request failed.');
            });
    });

    $('[data-use-free]').on('click', function (e) {
        e.preventDefault();

        qx.request('license', 'useFree', {}).done(function () {
            $('[data-installation-form]').submit();
        });
    });

    $('[data-use-different-key]').on('click', function (e) {
        e.preventDefault();
        showPrompt('');
    });

    $('[data-installation-submit]').on('click', function () {
        $('[data-installation-form]').submit();
    });
});
</script>
```

- [ ] **Step 7: Point maintenance and debug calls at the new helper**

In `setup/assets/scripts/script.js`, replace each raw `$.ajax({... controller=maintenance ...})` in `qx.maintenance` with `qx.request('maintenance', '<task>', {})`, and replace `qx.debug.downloadLog` with a token-carrying form post, because the debug download is now CSRF-checked:

```js
downloadLog: function () {
    var form = $('<form>', {
        method: 'POST',
        action: qx.ajaxUrl + '&controller=license&task=downloadDebugLog'
    });

    form.append($('<input>', { type: 'hidden', name: window.iquix.token, value: 1 }));
    form.appendTo('body').submit().remove();
}
```

- [ ] **Step 8: Remove the directory install mode**

```bash
git rm setup/views/steps/installing.directory.php
```

Run: `grep -rn "QX_INSTALLER\|QX_PACKAGE\|QX_BETA\|installing.directory\|'full'" setup/ || echo "directory mode gone"`
Expected: `directory mode gone`

- [ ] **Step 9: Verify the views parse**

Run: `find setup/views -name '*.php' -print0 | xargs -0 -n1 php -l | grep -v 'No syntax errors' || echo 'views parse'`
Expected: `views parse`

- [ ] **Step 10: Commit**

```bash
git add -A setup
git commit -m "fix(wizard): add csrf token and skip the key prompt when licensed"
```

---

### Task 14: Manifest, packaging and dead files

**Files:**
- Modify: `iquix.xml`
- Modify: `script.php`
- Modify: `mainfest.xml`
- Modify: `README.md`
- Delete: `setup/packages/` (directory)
- Delete: `TODO`
- Delete: `setup.html` if unreferenced after Task 12

- [ ] **Step 1: Bump the version and fix the manifest file list**

In `iquix.xml`, set `<version>2.0.0</version>` and `<creationDate>20th August 2026</creationDate>`. Confirm the `<administration><files>` block still lists exactly what ships — `setup` (folder), `iquix.php`, `script.php`, `config.xml`, `iquix.xml`, `access.xml` — and remove `setup.html` from the list if Step 5 deletes it. `tests/`, `composer.json`, `phpunit.xml`, `vendor/` and `docs/` must not appear; the manifest is an allowlist, so they are excluded by omission.

- [ ] **Step 2: Modernise the install script**

`script.php` uses `JFile` and `JFactory`, which do not exist on Joomla 6, and prints `setup.html` from `postflight`. Replace the class body with:

```php
defined('_JEXEC') or die('Unauthorized Access');

use Joomla\CMS\Factory;
use Joomla\CMS\Installer\InstallerScriptInterface;
use Joomla\Filesystem\File;

class Com_IquixInstallerScript
{
    public function preflight($type, $parent)
    {
        $file = JPATH_ROOT . '/tmp/quix.installation';

        if (!File::exists($file)) {
            File::write($file, json_encode(['new' => false, 'step' => 1, 'status' => 'installing']));
        }

        return true;
    }

    public function postflight($type, $parent)
    {
        $this->createConfigTable();

        Factory::getApplication()->enqueueMessage(
            'Quix Installer is ready. Open it from Components → Quix Installer to install Quix.',
            'message'
        );

        return true;
    }

    public function install($parent)
    {
        return true;
    }

    public function update($parent)
    {
        return true;
    }

    public function uninstall($parent)
    {
        return true;
    }

    private function createConfigTable(): bool
    {
        $db = Factory::getDbo();

        // InnoDB, not MyISAM: com_quix reads and writes this table too.
        $db->setQuery(
            'CREATE TABLE IF NOT EXISTS ' . $db->quoteName('#__quix_configs') . ' ('
            . $db->quoteName('name') . ' VARCHAR(255) NOT NULL,'
            . $db->quoteName('params') . ' TEXT NOT NULL,'
            . 'PRIMARY KEY (' . $db->quoteName('name') . ')'
            . ') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
        );

        return (bool) $db->execute();
    }
}
```

Note the class name: the old file declared `com_iQuixInstallerScript` while the manifest names the extension `com_iquix`; Joomla resolves the script class case-insensitively, but keep the conventional `Com_IquixInstallerScript` spelling.

**If `#__quix_configs` already exists without a primary key** (older Quix installs created it as MyISAM with no key), `Store::set()` still works because it reads the row set first and chooses insert or update itself. Do not add a migration that alters the existing table — `com_quix` owns it.

- [ ] **Step 3: Delete the web-readable package directory**

```bash
git rm -r --ignore-unmatch setup/packages
```

The archive now lands in Joomla's `tmp_path`. Confirm nothing still points there:

Run: `grep -rn "QX_PACKAGES\|setup/packages" --exclude-dir=.git --exclude-dir=vendor . || echo "packages dir gone"`
Expected: `packages dir gone`

- [ ] **Step 4: Remove the stale ignore rules**

In `.gitignore`, delete the `setup/packages` and `setup/tmp` lines — neither directory exists any more.

- [ ] **Step 5: Delete leftovers**

```bash
git rm --ignore-unmatch TODO
grep -rn "setup.html" --exclude-dir=.git --exclude-dir=vendor . || git rm --ignore-unmatch setup.html
```

- [ ] **Step 6: Add the 2.0.0 release entry to the update manifest**

Prepend a new `<update>` block to `mainfest.xml`, matching the existing entries' shape, with `<version>2.0.0</version>` and a download URL of `https://github.com/themexpert/iquix/releases/download/v2.0.0/com_iquix_2.0.0.zip`. Set `<targetplatform name="joomla" version="(4\.|5\.|6\.)"/>` and `<php_minimum>8.1</php_minimum>`.

- [ ] **Step 7: Rewrite the README**

Document what the extension is for, that it needs Joomla 4/5/6 and PHP 8.1+, how the FluentCart license flow works, that `store_url` and `item_id` rows in `#__quix_configs` override the endpoints for testing, and how to run `composer install && vendor/bin/phpunit`.

- [ ] **Step 8: Run the full check**

```bash
vendor/bin/phpunit --testsuite unit
find . -name '*.php' -not -path './vendor/*' -not -path './.git/*' -print0 | xargs -0 -n1 php -l | grep -v 'No syntax errors' || echo 'all parse'
```

Expected: tests PASS, `all parse`.

- [ ] **Step 9: Commit**

```bash
git add -A
git commit -m "chore(release): bump to 2.0.0 and drop dead packaging files"
```

---

### Task 15: Verify against a real Joomla 6 site

No unit test proves the installer installs. This task is the evidence, and nothing may be reported as working before it passes. Site: `/Users/abu/Sites/Joomla6` (`joomla6.test`), Joomla 6.1.2, database `j_j6`, prefix `saw1v_`, MySQL root/root.

**Files:**
- Create: `scripts/build.sh`

- [ ] **Step 1: Write the build script**

`scripts/build.sh` — produces an installable zip containing only what `iquix.xml` lists:

```bash
#!/usr/bin/env bash
set -euo pipefail

VERSION=$(grep -m1 '<version>' iquix.xml | sed -E 's/.*<version>(.*)<\/version>.*/\1/')
OUT="releases/com_iquix_${VERSION}.zip"

rm -rf releases/tmp "$OUT"
mkdir -p releases/tmp

cp -R setup languages iquix.php script.php config.xml iquix.xml access.xml releases/tmp/
rm -rf releases/tmp/setup/tmp

(cd releases/tmp && zip -qr "../com_iquix_${VERSION}.zip" .)
rm -rf releases/tmp

echo "Built $OUT"
```

Run: `chmod +x scripts/build.sh && ./scripts/build.sh`
Expected: `Built releases/com_iquix_2.0.0.zip`

- [ ] **Step 2: Confirm the zip carries no test or vendor files**

Run: `unzip -l releases/com_iquix_2.0.0.zip | grep -E 'vendor/|tests/|composer|phpunit|docs/' || echo "zip is clean"`
Expected: `zip is clean`

- [ ] **Step 3: Reset the test site to a clean state**

```bash
mysql -uroot -proot j_j6 -e "DELETE FROM saw1v_quix_configs;" 2>/dev/null || true
mysql -uroot -proot j_j6 -e "SELECT name, LEFT(params, 12) FROM saw1v_quix_configs;" 2>/dev/null || true
```

Record whether Quix is currently installed:

```bash
mysql -uroot -proot j_j6 -e "SELECT element, type FROM saw1v_extensions WHERE element LIKE '%quix%';"
```

- [ ] **Step 4: Case 1 — free install with no license**

Install the zip through Joomla's own installer at `https://joomla6.test/administrator`, open Components → Quix Installer, and walk the wizard choosing **Install Quix Free instead**.

Expected, and each must be observed rather than assumed:
- The license step offers the free option without erroring.
- The download step reports a version and does not report a checksum failure.
- The extensions step reports a count greater than zero.
- `SELECT element FROM saw1v_extensions WHERE element='pkg_quix';` returns a row.

Capture the wizard's final message and the SQL output.

- [ ] **Step 5: Case 2 — Pro activation and install**

Reset (`DELETE FROM saw1v_quix_configs;` and uninstall `pkg_quix` through Joomla), then run the wizard again entering a valid Quix Pro license key.

Expected:
- Activation succeeds and the step shows the masked key.
- `SELECT name, params FROM saw1v_quix_configs;` contains `license_key`, `activation_hash`, `license_status` = `valid`, and `activated` = `1`.
- `SELECT name, location, extra_query FROM saw1v_update_sites WHERE name='Quix Update Site';` shows the `my.converslabs.com` URL with credentials in `extra_query`.
- `SELECT COUNT(*) FROM saw1v_update_sites WHERE location LIKE '%themexpert.com%';` returns 0.
- Quix's own Configuration page reports the license as active — this is the com_quix integration actually working.

If no Pro key is available, stop and ask for one rather than reporting this case as untested-but-probably-fine.

- [ ] **Step 6: Case 3 — already licensed, no prompt**

With Case 2's rows still in `saw1v_quix_configs`, uninstall `pkg_quix` and run the wizard again.

Expected: the license step goes straight to "You're all set" with the masked key. **No key input is shown.** This is the requirement the whole task was asked for; confirm it visually.

- [ ] **Step 7: Confirm the paid archive is not left on disk**

```bash
find /Users/abu/Sites/Joomla6/administrator/components/com_iquix -name '*.zip'
find /Users/abu/Sites/Joomla6/tmp -maxdepth 1 -name 'iquix-*'
```

Expected: both return nothing.

- [ ] **Step 8: Confirm no key leaked into the log**

```bash
grep -ri "license_key=" /Users/abu/Sites/Joomla6/administrator/logs/iquix.log.php 2>/dev/null | head
```

Expected: any hit shows a masked value (`abcd****wxyz`), never a full key.

- [ ] **Step 9: Commit the build script and record results**

```bash
git add scripts/build.sh
git commit -m "chore(build): add release zip script"
```

Then report each of the three cases with the command output that proves it. State plainly which cases passed, which failed, and anything skipped.

- [ ] **Step 10: Push and open the pull request**

```bash
git push -u origin fix/installation
gh pr create --base master --head fix/installation \
  --title "Modernize the installer: FluentCart licensing, Joomla 4/5/6, security fixes" \
  --body "$(cat <<'BODY'
Rebuilds the `com_iquix` setup layer.

## Server and licensing
- Replaces the retired DigiCom API with FluentCart at `my.converslabs.com`.
- Writes the same `#__quix_configs` rows `com_quix` reads, so Quix is already activated when the installer finishes.
- Never asks for a key on a site that already has a valid one; tries a legacy iQuix auth key before prompting.
- Free edition falls back to the public GitHub release, whose published sha256 is verified.
- No `themexpert.com` endpoint remains, including the `#__update_sites` row.

## Joomla 4/5/6
- Removes every `J*` alias and `Joomla\CMS\Filesystem\*` reference; neither exists in Joomla 6.
- Replaces `JModelLegacy::getInstance('Install', 'InstallerModel')`, which loads from a directory removed in Joomla 4 — sub-extension installs were broken on J4 and J5 too.
- Drives the install list from the package's own `pkg_quix.xml`.

## Security
- Whitelisted routes; no more `require` of a request-supplied filename or `$this->$task()` on any existing method.
- `core.admin` plus a CSRF token on every task.
- Deletes `getAuthInfo`, which returned the whole credentials table as JSON.
- Fixes the LFI in `installing.php`.
- Enables TLS verification on downloads, caps redirects and size, verifies checksums.
- Moves the package out of the web-readable component directory and actually deletes it.
- Masks license keys in all log output.
- Removes the routine that rewrote `bootstrap.php`'s own source at runtime.

## Removed
- Joomla 3 compatibility, the `full`/`directory` installer mode, and the dead `backupDatabase` step.

🤖 Generated with [Claude Code](https://claude.com/claude-code)
BODY
)"
```

---

## Self-review

**Spec coverage.** Every spec section maps to a task: endpoints → Task 3; licensing and the com_quix row names → Task 5; skip-the-prompt including the legacy-key fallback → Task 6; free fallback with sha256 → Tasks 7 and 8; download flow steps 1–4 → Tasks 7 and 8; steps 5–6 → Task 10; step 7 (update site) → Task 11; step 8 (cleanup) → Tasks 8, 10 and 12; the nine security items → Tasks 8, 9, 12, 13 and 14 (1, 2 → Task 9; 3 → Tasks 9 and 12; 4 → Task 13; 5, 6, 7 → Task 8 with cleanup wired in Task 12; 8 → Tasks 1 and 12; 9 → Tasks 12 and 13); Joomla 4/5/6 correctness → Task 12 step 10 and Task 14 step 2; verification → Task 15.

**Two things the plan adds that the spec left implicit.** The five per-type install tasks collapse into one `installExtensions` route, since the list is now manifest-driven and per-type calls no longer mean anything. And `Update::updateScript` reports an available installer update instead of self-installing one mid-request, which the old code did and which is not safe.

**One deliberate deviation.** The spec kept `downloadDebugLog`; the plan keeps it but moves it behind the router's ACL and CSRF gate, which means it can no longer be a plain link — Task 13 step 7 changes it to a token-carrying form post.
