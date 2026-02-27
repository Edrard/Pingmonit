<?php

namespace Edrard\Pingmonit\Ups;

use Carbon\Carbon;
use Edrard\Pingmonit\Contracts\UpsNotifierInterface;
use Edrard\Pingmonit\State\HostStateRepository;
use Edrard\Pingmonit\Config;
use edrard\Log\MyLog;

class UpsMonitor
{
    private $state;
    private $snmp;
    private $notifier;

    public function __construct(HostStateRepository $state, UpsSnmpClient $snmp, UpsNotifierInterface $notifier = null)
    {
        $this->state = $state;
        $this->snmp = $snmp;
        $this->notifier = $notifier;
    }

    public function checkUps(array $upsList, array $options = [])
    {
        $disableState = (bool) ($options['disable_state'] ?? false);

        foreach ($upsList as $ups) {
            if (!is_array($ups)) {
                continue;
            }

            $ip = (string) ($ups['ip'] ?? '');
            if ($ip === '') {
                continue;
            }

            $name = (string) ($ups['name'] ?? '');
            $sendEmail = (bool) ($ups['send_email'] ?? true);
            $sendTelegram = (bool) ($ups['send_telegram'] ?? true);

            $oidCapacity = (string) ($ups['oid_capacity'] ?? '');
            $oidRuntime = (string) ($ups['oid_runtime'] ?? '');
            $oidTimeOnBattery = (string) ($ups['oid_time_on_battery'] ?? '');
            $oidBatteryStatus = (string) ($ups['oid_battery_status'] ?? '');

            $nowIso = Carbon::now()->toIso8601String();

            $entry = $this->state->get($ip) ?? [];
            $prevStatus = (string) ($entry['status'] ?? 'unknown');
            $warningStart = $entry['warning_start'] ?? null;
            $lastWarning = $entry['last_warning'] ?? null;
            $criticalSince = $entry['critical_since'] ?? null;
            $lastStatusChangeAt = $entry['last_status_change_at'] ?? null;

            if (!is_string($warningStart) || $warningStart === '') {
                $warningStart = null;
            }
            if (!is_string($lastWarning) || $lastWarning === '') {
                $lastWarning = null;
            }
            if (!is_string($criticalSince) || $criticalSince === '') {
                $criticalSince = null;
            }
            if (!is_string($lastStatusChangeAt) || $lastStatusChangeAt === '') {
                $lastStatusChangeAt = null;
            }

            $capacity = null;
            $runtimeSeconds = null;
            $timeOnBatterySeconds = null;
            $batteryStatus = null;

            // Get thresholds: per-UPS override or global defaults
            $warningThreshold = (int) ($ups['thresholds']['warning'] ?? $this->getGlobalThreshold('warning', 90));
            $criticalThreshold = (int) ($ups['thresholds']['critical'] ?? $this->getGlobalThreshold('critical', 50));

            try {
                if ($oidCapacity !== '') {
                    $capacity = $this->snmp->getInt($ups, $oidCapacity);
                }
                if ($oidRuntime !== '') {
                    $runtimeSeconds = $this->snmp->getTimeTicksSeconds($ups, $oidRuntime);
                }
                if ($oidTimeOnBattery !== '') {
                    $timeOnBatterySeconds = $this->snmp->getTimeTicksSeconds($ups, $oidTimeOnBattery);
                }
                if ($oidBatteryStatus !== '') {
                    $batteryStatus = $this->snmp->getInt($ups, $oidBatteryStatus);
                }
            } catch (\Throwable $e) {
                MyLog::warning('UPS SNMP read failed for ' . $ip . ': ' . $e->getMessage());
            }

            MyLog::info('UPS ' . $ip . ' capacity=' . ($capacity === null ? 'n/a' : (string) $capacity) . ', runtime_sec=' . ($runtimeSeconds === null ? 'n/a' : (string) $runtimeSeconds) . ', time_on_battery_sec=' . ($timeOnBatterySeconds === null ? 'n/a' : (string) $timeOnBatterySeconds) . ', battery_status=' . ($batteryStatus === null ? 'n/a' : (string) $batteryStatus));

            $newStatus = $prevStatus;

            if ($capacity === null) {
                $newStatus = 'unknown';
            } elseif ($capacity < $criticalThreshold) {
                $newStatus = 'critical';
            } elseif ($capacity < $warningThreshold) {
                $newStatus = 'warning';
            } else {
                $newStatus = 'good';
            }

            MyLog::info('UPS ' . $ip . ' thresholds: warning=' . $warningThreshold . '%, critical=' . $criticalThreshold . '%');

            if ($newStatus === 'good') {
                if ($prevStatus !== 'good') {
                    $lastStatusChangeAt = $nowIso;
                }
                $warningStart = null;
                $lastWarning = null;
                $criticalSince = null;
            } elseif ($newStatus === 'warning') {
                if ($warningStart === null) {
                    $warningStart = $nowIso;
                }
                $lastWarning = $nowIso;

                if ($prevStatus !== 'warning') {
                    $lastStatusChangeAt = $nowIso;
                }

                $criticalSince = null;
            } elseif ($newStatus === 'critical') {
                if ($warningStart === null) {
                    $warningStart = $nowIso;
                }
                $lastWarning = $nowIso;

                if ($prevStatus !== 'critical') {
                    $lastStatusChangeAt = $nowIso;
                    $criticalSince = $nowIso;
                }
            }

            $metrics = [
                'capacity' => $capacity,
                'runtime_seconds' => $runtimeSeconds,
                'time_on_battery_seconds' => $timeOnBatterySeconds,
                'battery_status' => $batteryStatus,
            ];

            if ($this->notifier !== null && ($sendEmail || $sendTelegram)) {
                if ($prevStatus !== 'critical' && $newStatus === 'critical') {
                    MyLog::info('UPS notify CRITICAL: ' . $ip . ' (email=' . ($sendEmail ? 'yes' : 'no') . ', telegram=' . ($sendTelegram ? 'yes' : 'no') . ')');
                    $this->notifier->notifyCritical($ip, $name, $metrics);
                }

                if ($prevStatus === 'critical' && $newStatus !== 'critical') {
                    $downtimeSeconds = 0;
                    if (is_string($criticalSince) && $criticalSince !== '') {
                        try {
                            $downtimeSeconds = Carbon::parse($criticalSince)->diffInSeconds(Carbon::now());
                        } catch (\Throwable $e) {
                            $downtimeSeconds = 0;
                        }
                    }

                    MyLog::info('UPS notify RECOVERED: ' . $ip . ' (email=' . ($sendEmail ? 'yes' : 'no') . ', telegram=' . ($sendTelegram ? 'yes' : 'no') . ')');
                    $this->notifier->notifyRecovered($ip, $name, $downtimeSeconds, $metrics);
                }
            }

            $this->state->set($ip, [
                'ip' => $ip,
                'name' => $name,
                'status' => $newStatus,
                'last_test_at' => $nowIso,
                'last_status_change_at' => $lastStatusChangeAt,
                'warning_start' => $warningStart,
                'last_warning' => $lastWarning,
                'critical_since' => $criticalSince,
            ]);

            MyLog::info('UPS State ' . $ip . ': ' . $prevStatus . ' -> ' . $newStatus);
        }

        if (!$disableState) {
            $this->state->save();
            MyLog::info('UPS state saved');
        } else {
            MyLog::info('UPS state saving disabled');
        }
    }

    /**
     * Get global threshold value from config
     */
    private function getGlobalThreshold(string $type, int $default): int
    {
        try {
            $thresholds = Config::get('ups_thresholds', []);
            return (int) ($thresholds[$type] ?? $default);
        } catch (\Throwable $e) {
            MyLog::warning('Failed to get global UPS threshold ' . $type . ': ' . $e->getMessage());
            return $default;
        }
    }
}
