<?php

namespace Edrard\Pingmonit;

use Console_CommandLine;

class ConsoleApp
{
    public function parseOptions(array $argv)
    {
        $parser = $this->buildParser();
        $result = $parser->parse($argv);
        return $result->options;
    }

    private function buildParser()
    {
        $parser = new Console_CommandLine();
        $parser->description = 'PingMonit CLI runner';

        $parser->addOption('disable_state', [
            'long_name' => '--disable_state',
            'description' => 'Do not write state_file',
            'action' => 'StoreTrue',
        ]);

        $parser->addOption('disable_email', [
            'long_name' => '--disable_email',
            'description' => 'Do not send email notifications',
            'action' => 'StoreTrue',
        ]);

        $parser->addOption('disable_telegram', [
            'long_name' => '--disable_telegram',
            'description' => 'Disable Telegram notifications',
            'action' => 'StoreTrue',
        ]);

        $parser->addOption('ip', [
            'long_name' => '--ip',
            'description' => 'Test only one IP/host',
            'action' => 'StoreString',
        ]);

        $parser->addOption('ups_ip', [
            'long_name' => '--ups_ip',
            'description' => 'Test only one UPS by IP',
            'action' => 'StoreString',
        ]);

        $parser->addOption('disable_lock', [
            'long_name' => '--disable_lock',
            'description' => 'Disable lock (allow concurrent runs)',
            'action' => 'StoreTrue',
        ]);

        $parser->addOption('lockfile', [
            'long_name' => '--lockfile',
            'description' => 'Custom lock file path',
            'action' => 'StoreString',
        ]);

        $parser->addOption('logs_std', [
            'long_name' => '--logs_std',
            'description' => 'Write logs to stdout instead of log files',
            'action' => 'StoreTrue',
        ]);

        return $parser;
    }
}
