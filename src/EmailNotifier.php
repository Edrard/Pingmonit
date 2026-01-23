<?php

namespace Edrard\Pingmonit;

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as PHPMailerException;
use edrard\Log\MyLog;
use Edrard\Exceptions\EmailException;

/**
 * Sends email notifications using PHPMailer
 */
class EmailNotifier
{
    private $mailer;
    private $config;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->config = Config::get('email');
        $this->initializeMailer();
    }

    /**
     * Initialize PHPMailer
     */
    private function initializeMailer()
    {
        $this->mailer = new PHPMailer(true);

        try {
            // Server settings
            $this->mailer->isSMTP();
            $this->mailer->Host = $this->config['smtp_host'];
            $this->mailer->SMTPAuth = true;
            $this->mailer->Username = $this->config['smtp_username'];
            $this->mailer->Password = $this->config['smtp_password'];
            $this->mailer->SMTPSecure = $this->config['smtp_encryption'];
            $this->mailer->Port = $this->config['smtp_port'];

            // Sender
            $this->mailer->setFrom($this->config['from_email'], $this->config['from_name']);

            // Encoding
            $this->mailer->CharSet = 'UTF-8';
            $this->mailer->Encoding = 'base64';

        } catch (PHPMailerException $e) {
            throw new EmailException('PHPMailer initialization failed: ' . $e->getMessage(), 'error');
        }
    }

    /**
     * Send a message to all configured recipients
     *
     * @param string $subject Subject
     * @param string $body Body
     * @param array $additionalEmails Additional recipients
     * @return bool
     */
    public function sendNotification($subject, $body, $additionalEmails = [])
    {
        MyLog::debug("Attempting to send email. Subject: {$subject}");
        try {
            // Clear previous recipients
            $this->mailer->clearAddresses();

            // Add recipients
            $emails = array_merge(Config::get('emails', []), $additionalEmails);

            if (empty($emails)) {
                MyLog::info('No email recipients configured');
                return false;
            }

            foreach ($emails as $email) {
                if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    $this->mailer->addAddress($email);
                } else {
                    MyLog::warning("Invalid email format: {$email}");
                }
            }

            // Message
            $this->mailer->Subject = $this->config['subject_prefix'] . $subject;
            $this->mailer->Body = $body;
            $this->mailer->AltBody = strip_tags($body); // Plain text fallback

            // Send
            $result = $this->mailer->send();

            if ($result) {
                MyLog::info("Email sent successfully. Subject: {$subject}");
            } else {
                MyLog::error('Email send error: ' . $this->mailer->ErrorInfo);
            }

            return $result;

        } catch (Exception $e) {
            MyLog::error('Email send error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Send a notification that a server is down
     *
     * @param string $ip Server IP
     * @param string $hostname Host name (optional)
     * @param int $failureCount Failure count
     * @return bool
     */
    public function sendServerDownNotification($ip, $hostname = '', $failureCount = 1)
    {
        $displayName = $ip;
        if (is_string($hostname) && $hostname !== '' && $hostname !== $ip) {
            $displayName = $ip . ' (' . $hostname . ')';
        }
        $subject = "Server down: {$displayName}";
        $body = "Server {$displayName} is down.\n";
        $body .= "Failure count: {$failureCount}\n";
        $body .= 'Time: ' . date('Y-m-d H:i:s') . "\n";

        return $this->sendNotification($subject, $body);
    }

    /**
     * Send a notification that a server has recovered
     *
     * @param string $ip Server IP
     * @param string $hostname Host name (optional)
     * @param int $downtime Downtime in seconds
     * @return bool
     */
    public function sendServerUpNotification($ip, $hostname = '', $downtime = 0)
    {
        $displayName = $ip;
        if (is_string($hostname) && $hostname !== '' && $hostname !== $ip) {
            $displayName = $ip . ' (' . $hostname . ')';
        }
        $subject = "Server recovered: {$displayName}";
        $body = "Server {$displayName} is reachable again.\n";

        if ($downtime > 0) {
            $hours = floor($downtime / 3600);
            $minutes = floor(($downtime % 3600) / 60);
            $seconds = $downtime % 60;
            $body .= "Downtime: {$hours}h {$minutes}m {$seconds}s\n";
        }

        $body .= 'Time: ' . date('Y-m-d H:i:s') . "\n";

        return $this->sendNotification($subject, $body);
    }

    /**
     * Test email settings
     *
     * @param string $testEmail Test recipient
     * @return bool
     */
    public function sendTestEmail($testEmail = null)
    {
        $subject = 'PingMonit test message';
        $body = "This is a test message from PingMonit.\n";
        $body .= "If you received this email, your settings are working.\n";
        $body .= 'Sent at: ' . date('Y-m-d H:i:s');

        $additionalEmails = $testEmail ? [$testEmail] : [];

        return $this->sendNotification($subject, $body, $additionalEmails);
    }
}