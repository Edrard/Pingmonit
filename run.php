<?php

require_once __DIR__ . '/vendor/autoload.php';

use Edrard\Pingmonit\ConsoleApp;
use Edrard\Pingmonit\CliRunner;
use Edrard\Pingmonit\Config;
use edrard\Log\Handlers;
use edrard\Log\MyLog;

try {
    Config::load();

    $options = (new ConsoleApp())->parseOptions($argv);

    $logsPath = Config::get('logs_path', __DIR__ . '/logs/');
    $logsStd = (bool) ($options['logs_std'] ?? false);
    if ($logsStd) {
        MyLog::init($logsPath, 'log', Handlers::stdout(), true);
    } else {
        MyLog::init($logsPath, 'log');
    }

    $runner = new CliRunner();
    $runner->run($options);
    exit(0);
} catch (Exception $e) {
    if (class_exists('edrard\\Log\\MyLog')) {
        MyLog::error('CLI error: ' . $e->getMessage());
    }
    fwrite(STDERR, $e->getMessage() . PHP_EOL);
    exit(1);
}
