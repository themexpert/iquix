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
