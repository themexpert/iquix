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
