<?php

namespace Edrard\Pingmonit\Notifier;

use Edrard\Pingmonit\Contracts\UpsNotifierInterface;
use edrard\Log\MyLog;
use Longman\TelegramBot\Request;
use Longman\TelegramBot\Telegram;

class UpsTelegramNotifierAdapter implements UpsNotifierInterface
{
    private $chatId;

    public function __construct($apiKey, $botUsername, $chatId)
    {
        $this->chatId = $chatId;

        $telegram = new Telegram((string) $apiKey, (string) $botUsername);
        Request::initialize($telegram);

        MyLog::info('Telegram UPS notifier initialized (bot=' . (string) $botUsername . ', chat_id=' . (string) $chatId . ')');
    }

    public function notifyCritical($ip, $name, array $metrics)
    {
        $displayName = $ip;
        if (is_string($name) && $name !== '' && $name !== $ip) {
            $displayName = $ip . ' (' . $name . ')';
        }

        $text = "UPS critical: {$displayName}\n";
        $text .= 'Capacity (%): ' . ($metrics['capacity'] ?? 'n/a') . "\n";
        $text .= 'Runtime (sec): ' . ($metrics['runtime_seconds'] ?? 'n/a') . "\n";
        $text .= 'Time on battery (sec): ' . ($metrics['time_on_battery_seconds'] ?? 'n/a') . "\n";
        $text .= 'Battery status: ' . ($metrics['battery_status'] ?? 'n/a') . "\n";
        $text .= 'Time: ' . date('Y-m-d H:i:s');

        MyLog::info('Telegram send (UPS CRITICAL): ' . $displayName);
        $response = Request::sendMessage([
            'chat_id' => $this->chatId,
            'text' => $text,
        ]);

        if (is_object($response) && method_exists($response, 'isOk') && $response->isOk()) {
            MyLog::info('Telegram send ok');
        } else {
            $desc = '';
            if (is_object($response) && method_exists($response, 'getDescription')) {
                $desc = (string) $response->getDescription();
            }
            MyLog::warning('Telegram send failed' . ($desc !== '' ? (': ' . $desc) : ''));
        }
    }

    public function notifyRecovered($ip, $name, $downtimeSeconds, array $metrics)
    {
        $displayName = $ip;
        if (is_string($name) && $name !== '' && $name !== $ip) {
            $displayName = $ip . ' (' . $name . ')';
        }

        $text = "UPS recovered: {$displayName}\n";
        $text .= 'Capacity (%): ' . ($metrics['capacity'] ?? 'n/a') . "\n";
        $text .= 'Runtime (sec): ' . ($metrics['runtime_seconds'] ?? 'n/a') . "\n";
        $text .= 'Time on battery (sec): ' . ($metrics['time_on_battery_seconds'] ?? 'n/a') . "\n";
        $text .= 'Battery status: ' . ($metrics['battery_status'] ?? 'n/a') . "\n";
        $text .= 'Downtime (sec): ' . (int) $downtimeSeconds . "\n";
        $text .= 'Time: ' . date('Y-m-d H:i:s');

        MyLog::info('Telegram send (UPS UP): ' . $displayName);
        $response = Request::sendMessage([
            'chat_id' => $this->chatId,
            'text' => $text,
        ]);

        if (is_object($response) && method_exists($response, 'isOk') && $response->isOk()) {
            MyLog::info('Telegram send ok');
        } else {
            $desc = '';
            if (is_object($response) && method_exists($response, 'getDescription')) {
                $desc = (string) $response->getDescription();
            }
            MyLog::warning('Telegram send failed' . ($desc !== '' ? (': ' . $desc) : ''));
        }
    }
}
