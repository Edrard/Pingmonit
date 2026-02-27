<?php

namespace Edrard\Pingmonit\Notifier;

use Edrard\Pingmonit\Contracts\NotifierInterface;

class CompositeNotifier implements NotifierInterface
{
    private $notifiers;
    private $sendEmail;
    private $sendTelegram;

    public function __construct(array $notifiers, bool $sendEmail = true, bool $sendTelegram = true)
    {
        $this->notifiers = $notifiers;
        $this->sendEmail = $sendEmail;
        $this->sendTelegram = $sendTelegram;
    }

    public function notifyDown($ip, $hostname, $failureCount)
    {
        foreach ($this->notifiers as $notifier) {
            if ($notifier instanceof NotifierInterface) {
                // Check notifier type and corresponding flag
                if (($notifier instanceof EmailNotifier && !$this->sendEmail) ||
                    ($notifier instanceof TelegramNotifierAdapter && !$this->sendTelegram)) {
                    continue;
                }
                $notifier->notifyDown($ip, $hostname, $failureCount);
            }
        }
    }

    public function notifyUp($ip, $hostname, $downtimeSeconds)
    {
        foreach ($this->notifiers as $notifier) {
            if ($notifier instanceof NotifierInterface) {
                // Check notifier type and corresponding flag
                if (($notifier instanceof EmailNotifier && !$this->sendEmail) ||
                    ($notifier instanceof TelegramNotifierAdapter && !$this->sendTelegram)) {
                    continue;
                }
                $notifier->notifyUp($ip, $hostname, $downtimeSeconds);
            }
        }
    }
}
