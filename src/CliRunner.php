<?php

namespace Edrard\Pingmonit;

use edrard\Log\MyLog;
use Edrard\Pingmonit\Lock\FlockLock;
use Edrard\Pingmonit\Notifier\CompositeNotifier;
use Edrard\Pingmonit\Notifier\EmailNotifierAdapter;
use Edrard\Pingmonit\Notifier\TelegramNotifierAdapter;
use Edrard\Pingmonit\Ping\JjgPingService;
use Edrard\Pingmonit\Report\HtmlIndexReportGenerator;
use Edrard\Pingmonit\State\HostStateRepository;
use Edrard\Pingmonit\State\JsonStateStore;

class CliRunner
{
    public function run(array $options)
    {
        $disableState = (bool) ($options['disable_state'] ?? false);
        $disableEmail = (bool) ($options['disable_email'] ?? false);
        $disableTelegram = (bool) ($options['disable_telegram'] ?? false);
        $disableLock = (bool) ($options['disable_lock'] ?? false);
        $singleIp = $options['ip'] ?? null;
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
                    ];
                } else {
                    $targets[] = [
                        'ip' => $singleIp,
                        'name' => '',
                        'send_email' => true,
                    ];
                }
            } else {
                foreach ($configuredIps as $ip => $cfg) {
                    if (is_int($ip) && is_string($cfg) && $cfg !== '') {
                        $targets[] = [
                            'ip' => $cfg,
                            'name' => '',
                            'send_email' => true,
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
                            ];
                        }
                        continue;
                    }

                    if (is_array($cfg)) {
                        $targets[] = [
                            'ip' => $ip,
                            'name' => (string) ($cfg['name'] ?? ''),
                            'send_email' => (bool) ($cfg['send_email'] ?? true),
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
            if (!$disableEmail) {
                $notifiers[] = new EmailNotifierAdapter(new EmailNotifier());
            }

            $telegramConfig = Config::get('telegram', []);
            $telegramEnabled = (bool) ($telegramConfig['enabled'] ?? false);
            if ($telegramEnabled && !$disableTelegram) {
                $apiKey = (string) ($telegramConfig['api_key'] ?? '');
                $botUsername = (string) ($telegramConfig['bot_username'] ?? '');
                $chatId = $telegramConfig['chat_id'] ?? '';

                if ($apiKey !== '' && $botUsername !== '' && $chatId !== '') {
                    $notifiers[] = new TelegramNotifierAdapter($apiKey, $botUsername, $chatId);
                } else {
                    MyLog::warning('Telegram enabled but api_key/bot_username/chat_id not configured');
                }
            }

            if (count($notifiers) === 1) {
                $notifier = $notifiers[0];
            } elseif (count($notifiers) > 1) {
                $notifier = new CompositeNotifier($notifiers);
            }

            $pingService = new JjgPingService();

            $monitor = new PingMonitor($state, $pingService, $notifier, [
                'ping_timeout' => $timeout,
                'max_failures' => $maxFailures,
            ]);

            $monitor->checkHosts($targets, [
                'disable_state' => $disableState,
            ]);

            $webRefreshSeconds = (int) Config::get('web_refresh_seconds', 0);
            $reportGenerator = new HtmlIndexReportGenerator(__DIR__ . '/../public/index.html', $webRefreshSeconds);
            $reportGenerator->generate($targets, $state);

            MyLog::info('PingMonit CLI finished');
        } finally {
            if ($lock !== null) {
                $lock->release();
            }
        }
    }
}
