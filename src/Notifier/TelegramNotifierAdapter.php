<?php

namespace Edrard\Pingmonit\Notifier;

use Edrard\Pingmonit\Contracts\NotifierInterface;
use edrard\Log\MyLog;
use Longman\TelegramBot\Request;
use Longman\TelegramBot\Telegram;

class TelegramNotifierAdapter implements NotifierInterface
{
    private $chatId;

    public function __construct($apiKey, $botUsername, $chatId)
    {
        $this->chatId = $chatId;

        $telegram = new Telegram((string) $apiKey, (string) $botUsername);
        Request::initialize($telegram);

        MyLog::info('Telegram notifier initialized (bot=' . (string) $botUsername . ', chat_id=' . (string) $chatId . ')');
    }

    public function notifyDown($ip, $hostname, $failureCount)
    {
        $displayName = $ip;
        if (is_string($hostname) && $hostname !== '' && $hostname !== $ip) {
            $displayName = $ip . ' (' . $hostname . ')';
        }

        $text = "Server down: {$displayName}\n";
        $text .= 'Failure count: ' . (int) $failureCount . "\n";
        $text .= 'Time: ' . date('Y-m-d H:i:s');

        MyLog::info('Telegram send (DOWN): ' . $displayName);
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

    public function notifyUp($ip, $hostname, $downtimeSeconds)
    {
        $displayName = $ip;
        if (is_string($hostname) && $hostname !== '' && $hostname !== $ip) {
            $displayName = $ip . ' (' . $hostname . ')';
        }

        $text = "Server recovered: {$displayName}\n";
        if ((int) $downtimeSeconds > 0) {
            $text .= 'Downtime (sec): ' . (int) $downtimeSeconds . "\n";
        }
        $text .= 'Time: ' . date('Y-m-d H:i:s');

        MyLog::info('Telegram send (UP): ' . $displayName);
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
