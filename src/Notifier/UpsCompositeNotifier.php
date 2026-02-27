<?php

namespace Edrard\Pingmonit\Notifier;

use Edrard\Pingmonit\Contracts\UpsNotifierInterface;

class UpsCompositeNotifier implements UpsNotifierInterface
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

    public function notifyCritical($ip, $name, array $metrics)
    {
        foreach ($this->notifiers as $notifier) {
            if ($notifier instanceof UpsNotifierInterface) {
                // Check notifier type and corresponding flag
                if (($notifier instanceof UpsEmailNotifierAdapter && !$this->sendEmail) ||
                    ($notifier instanceof UpsTelegramNotifierAdapter && !$this->sendTelegram)) {
                    continue;
                }
                $notifier->notifyCritical($ip, $name, $metrics);
            }
        }
    }

    public function notifyRecovered($ip, $name, $downtimeSeconds, array $metrics)
    {
        foreach ($this->notifiers as $notifier) {
            if ($notifier instanceof UpsNotifierInterface) {
                // Check notifier type and corresponding flag
                if (($notifier instanceof UpsEmailNotifierAdapter && !$this->sendEmail) ||
                    ($notifier instanceof UpsTelegramNotifierAdapter && !$this->sendTelegram)) {
                    continue;
                }
                $notifier->notifyRecovered($ip, $name, (int) $downtimeSeconds, $metrics);
            }
        }
    }
}
