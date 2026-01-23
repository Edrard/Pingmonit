<?php

namespace Edrard\Pingmonit;

use Carbon\Carbon;
use Edrard\Pingmonit\Contracts\NotifierInterface;
use Edrard\Pingmonit\Contracts\PingServiceInterface;
use Edrard\Pingmonit\State\HostStateRepository;
use edrard\Log\MyLog;

class PingMonitor
{
    private $state;
    private $pingService;
    private $notifier;
    private $config;

    public function __construct(HostStateRepository $state, PingServiceInterface $pingService, NotifierInterface $notifier = null, array $config = [])
    {
        $this->state = $state;
        $this->pingService = $pingService;
        $this->notifier = $notifier;
        $this->config = $config;
    }

    public function checkHosts(array $ips, array $options = [])
    {
        $disableState = (bool) ($options['disable_state'] ?? false);

        $timeout = (int) ($this->config['ping_timeout'] ?? 5);
        $maxFailures = (int) ($this->config['max_failures'] ?? 3);

        foreach ($ips as $target) {
            if (is_string($target)) {
                $ip = $target;
                $hostname = '';
                $sendEmail = true;
            } elseif (is_array($target)) {
                $ip = (string) ($target['ip'] ?? '');
                $hostname = (string) ($target['name'] ?? '');
                $sendEmail = (bool) ($target['send_email'] ?? true);
            } else {
                continue;
            }

            if ($ip === '') {
                continue;
            }

            $nowIso = Carbon::now()->toIso8601String();

            $entry = $this->state->get($ip) ?? [];
            $prevStatus = $entry['status'] ?? 'unknown';
            $prevFailures = (int) ($entry['failures'] ?? 0);
            $warningStart = $entry['warning_start'] ?? null;
            $lastWarning = $entry['last_warning'] ?? null;

            if (!is_string($warningStart) || $warningStart === '') {
                $warningStart = null;
            }
            if (!is_string($lastWarning) || $lastWarning === '') {
                $lastWarning = null;
            }

            $warningDates = $entry['warning_dates'] ?? null;
            if ($warningStart === null && $lastWarning === null && is_array($warningDates) && $warningDates !== []) {
                $first = reset($warningDates);
                $last = end($warningDates);
                $warningStart = is_string($first) && $first !== '' ? $first : null;
                $lastWarning = is_string($last) && $last !== '' ? $last : null;
            }

            $latency = $this->pingService->ping($ip, $timeout);
            $isUp = ($latency !== null);

            MyLog::info("Ping {$ip}: " . ($isUp ? 'OK' : 'FAIL'));

            $newStatus = $prevStatus;
            $newFailures = $prevFailures;
            $criticalSince = $entry['critical_since'] ?? null;
            $lastStatusChangeAt = $entry['last_status_change_at'] ?? null;

            if ($isUp) {
                $newStatus = 'good';
                $newFailures = 0;

                if ($prevStatus !== 'good') {
                    $lastStatusChangeAt = $nowIso;
                }

                $warningStart = null;
                $lastWarning = null;
                $criticalSince = null;
            } else {
                if ($prevFailures > $maxFailures) {
                    $newFailures = $prevFailures;
                } else {
                    $newFailures = $prevFailures + 1;
                }

                if ($warningStart === null) {
                    $warningStart = $nowIso;
                }
                $lastWarning = $nowIso;

                if ($newFailures > $maxFailures) {
                    $newStatus = 'critical';
                    if ($prevStatus !== 'critical') {
                        $lastStatusChangeAt = $nowIso;
                        $criticalSince = $nowIso;
                    }
                } else {
                    $newStatus = 'warning';
                    if ($prevStatus !== 'warning') {
                        $lastStatusChangeAt = $nowIso;
                    }
                }
            }

            if ($this->notifier !== null && $sendEmail) {
                if ($prevStatus === 'warning' && $newStatus === 'critical') {
                    MyLog::info("Email: {$ip} transitioned to CRITICAL");
                    $this->notifier->notifyDown($ip, $hostname, $newFailures);
                }

                if ($prevStatus === 'critical' && $newStatus === 'good') {
                    MyLog::info("Email: {$ip} recovered to GOOD");
                    $this->notifier->notifyUp($ip, $hostname, 0);
                }
            }

            $this->state->set($ip, [
                'ip' => $ip,
                'status' => $newStatus,
                'failures' => $newFailures,
                'last_test_at' => $nowIso,
                'last_status_change_at' => $lastStatusChangeAt,
                'warning_start' => $warningStart,
                'last_warning' => $lastWarning,
                'critical_since' => $criticalSince,
                'latency_ms' => $isUp ? (float) $latency : null,
            ]);

            MyLog::info("State {$ip}: {$prevStatus} -> {$newStatus} (failures={$newFailures}, max_failures={$maxFailures})");
        }

        if (!$disableState) {
            $this->state->save();
            MyLog::info('State saved');
        } else {
            MyLog::info('State saving disabled');
        }
    }
}