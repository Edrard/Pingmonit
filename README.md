# PingMonit

PingMonit is a cron-first PHP CLI monitoring tool.

It pings a list of hosts (IP addresses or domain names), stores the current state in a JSON file, sends notifications only on important state transitions, and generates a minimal mobile-friendly `public/index.html` status page.

## Features

- **Cron-first execution** (single run, suitable for cron)
- **File lock** to prevent concurrent runs
- **State persistence** in JSON (`state/system_state.json` by default)
- **Notifications**
  - Email (PHPMailer)
  - Telegram (longman/telegram-bot)
- **Notification rules (important)**
  - Send **DOWN** only when a host transitions `warning -> critical`
  - Send **UP** only when a host transitions `critical -> good`
  - Do not spam while a host remains `critical`
- **Per-host notification flag** (`send_email`) (currently applied to all notifications for that host)
- **Logging**
  - Default: rotating files (MyLog)
  - Optional: stdout (`--logs_std`) for cron-friendly output
- **Static status page generation**
  - Writes `public/index.html` after each run
  - Mobile layout without scroll (one colored bar per host)
  - Shows only the “problem start time” inside **critical** bars
  - Optional auto-refresh via `web_refresh_seconds`

## Requirements

- PHP 7.0+
- Composer

## Installation

```bash
git clone 
composer install
```

## Configuration

Copy and edit:

```bash
cp config/config.php.example config/config.php
```

### `ips` format

```php
'ips' => [
    'google.com' => ['send_email' => true, 'name' => 'Google'],
    '8.8.8.8' => ['send_email' => true, 'name' => 'DNS'],
    '192.168.1.1' => ['send_email' => false, 'name' => 'Local'],
],
```

### Web auto-refresh

```php
'web_refresh_seconds' => 60,
```

Set to `0` to disable auto-refresh.

### Telegram

```php
'telegram' => [
    'enabled' => true,
    'api_key' => '123456:ABCDEF...',
    'bot_username' => 'my_pingmonit_bot',
    'chat_id' => 123456789,
],
```

## Usage (CLI)

Run once (typical cron run):

```bash
php run.php
```

Useful flags:

- `--disable_state` Disable writing `state_file`
- `--disable_email` Disable email notifications
- `--disable_telegram` Disable Telegram notifications
- `--disable_lock` Disable lock file
- `--lockfile=/path/to/run.lock` Custom lock file
- `--ip=8.8.8.8` Check only one target
- `--logs_std` Log to stdout instead of log files

## Cron example

Run every minute:

```bash
* * * * * /usr/bin/php /path/to/pingmonit/run.php >/dev/null 2>&1
```

## Status page

After each run, PingMonit generates:

- `public/index.html`

The page is designed for mobile: **no scroll**, one bar per host:

- Green: `good`
- Orange: `warning`
- Red: `critical` (shows problem start time)

## Telegram setup (what to do in Telegram)

1. Open **@BotFather** and create a new bot with `/newbot`.
2. Save the token provided by BotFather (this is `api_key`).
3. Determine `chat_id`:
   - Private chat: message the bot, then open:
     `https://api.telegram.org/bot<API_KEY>/getUpdates`
     and take `message.chat.id`.
   - Group/channel: add the bot to the group/channel, send a message, then use `getUpdates` and take `message.chat.id` (often negative for groups).

## Security notes

- Do **not** commit `config/config.php` (it contains SMTP credentials and Telegram bot token).
- Use `config/config.php.example` as a public template.