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

        if ($result['licensed'] === true) {
            $this->container->store()->set('edition', 'pro');
        }

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
