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
- `php-snmp` (PHP SNMP extension) if you want to monitor UPS devices via SNMP

## Installation

```bash
git clone https://github.com/Edrard/Pingmonit.git
composer install
```

## Updating

PingMonit includes `update.sh` to update the repository to the latest `main` and refresh Composer dependencies.

Requirements:

- `git`
- `composer`

Usage:

```bash
chmod +x update.sh
./update.sh
```

Notes:

- Run it from the **repository root** (where `run.php` is located).
- The script will **stop** if you have local changes (to avoid overwriting your edits).
- If you run it as root, it will try to restore file ownership to match `run.php`.

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

### UPS monitoring (SNMP)

PingMonit can also monitor UPS devices via SNMP (requires the PHP `php-snmp` extension).

Config keys:

- `ups` List of UPS devices
- `ups_state_file` Separate JSON state file for UPS (default: `state/ups_state.json`)

Each UPS supports these fields:

- `name` Human-readable name
- `ip` UPS IP address
- `send_email` Enable/disable notifications for this UPS
- `web` Show/hide this UPS on the HTML status page
- `snmp_version` `1`, `2c`, or `3`
- `snmp_community` (v1/v2c)
- `snmp_v3_username`, `snmp_v3_auth_protocol` (`MD5`/`SHA`), `snmp_v3_auth_password`, `snmp_v3_priv_protocol` (`DES`/`AES`), `snmp_v3_priv_password`
- `oid_capacity` (main metric)
- `oid_runtime` (TimeTicks)
- `oid_time_on_battery` (TimeTicks)
- `oid_battery_status`

Status rules:

- `capacity < 100` => `warning`
- `capacity < 90` => `critical`

Notification rules:

- Send notifications only when a UPS transitions to `critical`
- Send recovery notifications when a UPS transitions from `critical` to a non-critical state

TimeTicks:

- `oid_runtime` and `oid_time_on_battery` are expected to return SNMP `TimeTicks` (1/100 sec)
- PingMonit converts them to **seconds** before sending notifications

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
- `--ups_ip=172.16.2.250` Check only one UPS target by IP
- `--logs_std` Log to stdout instead of log files

UPS test example (stdout logs, no notifications):

```bash
php run.php --ups_ip=172.16.2.250 --logs_std --disable_email --disable_telegram
```

Notes about `--ups_ip`:

- `--ups_ip` does not contain SNMP credentials or OIDs.
- It only selects one UPS entry from `config/config.php` (from the `ups` section) by matching `ip`.
- SNMP settings (`snmp_version`, `snmp_community`, SNMP v3 credentials) and OIDs are always taken from the matching UPS entry in the config.
- If there is no UPS with that `ip` in the config, UPS monitoring will be skipped.

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