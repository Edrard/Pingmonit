<?php

namespace Edrard\Pingmonit;

use edrard\Log\MyLog;
use Edrard\Pingmonit\Lock\FlockLock;
use Edrard\Pingmonit\Notifier\CompositeNotifier;
use Edrard\Pingmonit\Notifier\EmailNotifierAdapter;
use Edrard\Pingmonit\Notifier\TelegramNotifierAdapter;
use Edrard\Pingmonit\Notifier\UpsCompositeNotifier;
use Edrard\Pingmonit\Notifier\UpsEmailNotifierAdapter;
use Edrard\Pingmonit\Notifier\UpsTelegramNotifierAdapter;
use Edrard\Pingmonit\Ping\JjgPingService;
use Edrard\Pingmonit\Report\HtmlIndexReportGenerator;
use Edrard\Pingmonit\State\HostStateRepository;
use Edrard\Pingmonit\State\JsonStateStore;
use Edrard\Pingmonit\Ups\UpsMonitor;
use Edrard\Pingmonit\Ups\UpsSnmpClient;

class CliRunner
{
    public function run(array $options)
    {
        $disableState = (bool) ($options['disable_state'] ?? false);
        $disableEmail = (bool) ($options['disable_email'] ?? false);
        $disableTelegram = (bool) ($options['disable_telegram'] ?? false);
        $disableLock = (bool) ($options['disable_lock'] ?? false);
        $singleIp = $options['ip'] ?? null;
        $singleUpsIp = $options['ups_ip'] ?? null;
        $lockFile = $options['lockfile'] ?? null;

        $monitoringConfig = Config::get('monitoring', []);
        $timeout = (int) ($monitoringConfig['ping_timeout'] ?? 5);
        $maxFailures = (int) ($monitoringConfig['max_failures'] ?? 3);

        $stateFile = Config::get('state_file', __DIR__ . '/../state/system_state.json');

        $lock = null;
        if (!$disableLock) {
            if (!is_string($lockFile) || $lockFile === '') {
                $lockFile = dirname($stateFile) . '/run.lock';
            }

            $lock = new FlockLock($lockFile);
            if ($lock->acquire() !== true) {
                MyLog::warning("Another instance is running (lock: {$lockFile}). Exiting.");
                return;
            }
        }

        try {
            $targets = [];
            $configuredIps = Config::get('ips', []);

            if (is_string($singleIp) && $singleIp !== '') {
                $meta = $configuredIps[$singleIp] ?? null;
                if (is_array($meta)) {
                    $targets[] = [
                        'ip' => $singleIp,
                        'name' => (string) ($meta['name'] ?? ''),
                        'send_email' => (bool) ($meta['send_email'] ?? true),
                        'web' => (bool) ($meta['web'] ?? true),
                    ];
                } else {
                    $targets[] = [
                        'ip' => $singleIp,
                        'name' => '',
                        'send_email' => true,
                        'web' => true,
                    ];
                }
            } else {
                foreach ($configuredIps as $ip => $cfg) {
                    if (is_int($ip) && is_string($cfg) && $cfg !== '') {
                        $targets[] = [
                            'ip' => $cfg,
                            'name' => '',
                            'send_email' => true,
                            'web' => true,
                        ];
                        continue;
                    }

                    if (!is_string($ip) || $ip === '') {
                        continue;
                    }

                    if (is_bool($cfg)) {
                        if ($cfg === true) {
                            $targets[] = [
                                'ip' => $ip,
                                'name' => '',
                                'send_email' => true,
                                'send_telegram' => true,
                                'web' => true,
                            ];
                        }
                        continue;
                    }

                    if (is_array($cfg)) {
                        $targets[] = [
                            'ip' => $ip,
                            'name' => (string) ($cfg['name'] ?? ''),
                            'send_email' => (bool) ($cfg['send_email'] ?? true),
                            'send_telegram' => (bool) ($cfg['send_telegram'] ?? true),
                            'web' => (bool) ($cfg['web'] ?? true),
                        ];
                    }
                }
            }

            $targetLabels = [];
            foreach ($targets as $t) {
                $label = (string) ($t['ip'] ?? '');
                $name = (string) ($t['name'] ?? '');
                if ($name !== '') {
                    $label .= ' (' . $name . ')';
                }
                $targetLabels[] = $label;
            }

            MyLog::info('PingMonit CLI started');
            MyLog::info('Targets: ' . implode(', ', $targetLabels));
            MyLog::info('Flags: disable_state=' . ($disableState ? 'yes' : 'no') . ', disable_email=' . ($disableEmail ? 'yes' : 'no') . ', disable_telegram=' . ($disableTelegram ? 'yes' : 'no') . ', disable_lock=' . ($disableLock ? 'yes' : 'no'));

            $stateStore = new JsonStateStore($stateFile);
            $state = new HostStateRepository($stateStore);

            $notifier = null;
            $notifiers = [];
            $sendEmail = false;
            $sendTelegram = false;
            
            if (!$disableEmail) {
                $notifiers[] = new EmailNotifierAdapter(new EmailNotifier());
                $sendEmail = true;
            }

            $telegramConfig = Config::get('telegram', []);
            $telegramEnabled = (bool) ($telegramConfig['enabled'] ?? false);
            if ($telegramEnabled && !$disableTelegram) {
                $apiKey = (string) ($telegramConfig['api_key'] ?? '');
                $botUsername = (string) ($telegramConfig['bot_username'] ?? '');
                $chatId = $telegramConfig['chat_id'] ?? '';

                if ($apiKey !== '' && $botUsername !== '' && $chatId !== '') {
                    $notifiers[] = new TelegramNotifierAdapter($apiKey, $botUsername, $chatId);
                    $sendTelegram = true;
                } else {
                    MyLog::warning('Telegram enabled but api_key/bot_username/chat_id not configured');
                }
            }

            if (count($notifiers) === 1) {
                $notifier = $notifiers[0];
            } elseif (count($notifiers) > 1) {
                $notifier = new CompositeNotifier($notifiers, $sendEmail, $sendTelegram);
            }

            $pingService = new JjgPingService();

            $monitor = new PingMonitor($state, $pingService, $notifier, [
                'ping_timeout' => $timeout,
                'max_failures' => $maxFailures,
            ]);

            // Parallel processing if pcntl is available
            if (function_exists('pcntl_fork') && count($targets) > 1) {
                $this->checkHostsParallel($monitor, $state, $targets, $timeout, $maxFailures, [
                    'disable_state' => true,  // Disable state saving in child processes
                ]);
                
                // Save state once after all processes complete
                if (!$disableState) {
                    $state->save();
                    MyLog::info('State saved');
                }
            } else {
                $monitor->checkHosts($targets, [
                    'disable_state' => $disableState,
                ]);
            }

            $upsTargets = [];
            $upsConfig = Config::get('ups', []);
            if (is_array($upsConfig)) {
                foreach ($upsConfig as $ups) {
                    if (!is_array($ups)) {
                        continue;
                    }

                    $ip = (string) ($ups['ip'] ?? '');
                    if ($ip === '') {
                        continue;
                    }

                    if (is_string($singleUpsIp) && $singleUpsIp !== '' && $ip !== $singleUpsIp) {
                        continue;
                    }

                    $ups['name'] = (string) ($ups['name'] ?? '');
                    $ups['send_email'] = (bool) ($ups['send_email'] ?? true);
                    $ups['send_telegram'] = (bool) ($ups['send_telegram'] ?? true);
                    $ups['web'] = (bool) ($ups['web'] ?? true);
                    $ups['snmp_version'] = (string) ($ups['snmp_version'] ?? '2c');
                    $ups['snmp_community'] = (string) ($ups['snmp_community'] ?? 'public');

                    $upsTargets[] = $ups;
                }
            }

            $upsStateFile = Config::get('ups_state_file', __DIR__ . '/../state/ups_state.json');
            $upsStateStore = new JsonStateStore($upsStateFile);
            $upsState = new HostStateRepository($upsStateStore);

            $upsNotifier = null;
            $upsNotifiers = [];
            $upsSendEmail = false;
            $upsSendTelegram = false;
            
            if (!$disableEmail) {
                $upsNotifiers[] = new UpsEmailNotifierAdapter(new EmailNotifier());
                $upsSendEmail = true;
            }

            if ($telegramEnabled && !$disableTelegram) {
                $apiKey = (string) ($telegramConfig['api_key'] ?? '');
                $botUsername = (string) ($telegramConfig['bot_username'] ?? '');
                $chatId = $telegramConfig['chat_id'] ?? '';

                if ($apiKey !== '' && $botUsername !== '' && $chatId !== '') {
                    $upsNotifiers[] = new UpsTelegramNotifierAdapter($apiKey, $botUsername, $chatId);
                    $upsSendTelegram = true;
                }
            }

            if (count($upsNotifiers) === 1) {
                $upsNotifier = $upsNotifiers[0];
            } elseif (count($upsNotifiers) > 1) {
                $upsNotifier = new UpsCompositeNotifier($upsNotifiers, $upsSendEmail, $upsSendTelegram);
            }

            if ($upsTargets !== []) {
                $upsMonitor = new UpsMonitor($upsState, new UpsSnmpClient(), $upsNotifier);
                $upsMonitor->checkUps($upsTargets, [
                    'disable_state' => $disableState,
                ]);
            }

            $webRefreshSeconds = (int) Config::get('web_refresh_seconds', 0);
            $reportGenerator = new HtmlIndexReportGenerator(__DIR__ . '/../public/index.html', $webRefreshSeconds);
            $reportGenerator->generate($targets, $state, $upsTargets, $upsState);

            MyLog::info('PingMonit CLI finished');
        } finally {
            if ($lock !== null) {
                $lock->release();
            }
        }
    }

    /**
     * Check hosts in parallel using pcntl_fork with true shared state
     */
    private function checkHostsParallel($monitor, $state, array $targets, int $timeout, int $maxFailures, array $options = [])
    {
        $children = [];
        $maxConcurrency = 20; // Limit concurrent processes
        
        MyLog::info("Starting parallel checks for " . count($targets) . " targets (max concurrency: {$maxConcurrency})");

        foreach ($targets as $i => $target) {
            // Wait for available slot
            while (count($children) >= $maxConcurrency) {
                $finished = pcntl_wait($status);
                unset($children[array_search($finished, $children)]);
            }

            $pid = pcntl_fork();
            if ($pid == -1) {
                MyLog::error("Failed to fork process for target: " . ($target['ip'] ?? 'unknown'));
                continue;
            } elseif ($pid == 0) {
                // Child process - create new monitor with shared state
                $pingService = new JjgPingService();
                $newMonitor = new PingMonitor($state, $pingService, $monitor->getNotifier(), [
                    'ping_timeout' => $timeout,
                    'max_failures' => $maxFailures,
                ]);
                $newMonitor->checkHosts([$target], $options);
                exit(0);
            } else {
                // Parent process
                $children[] = $pid;
            }
        }

        // Wait for all remaining children
        while (count($children) > 0) {
            $finished = pcntl_wait($status);
            unset($children[array_search($finished, $children)]);
        }

        MyLog::info("All parallel checks completed");
    }
}
