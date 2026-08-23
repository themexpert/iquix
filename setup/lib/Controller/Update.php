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
