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

- PHP 8.0+
- Composer
- `pcntl` extension (for parallel processing)
- `php-snmp` extension (for UPS monitoring)

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
    'google.com' => ['send_email' => true, 'send_telegram' => true, 'web' => true, 'name' => 'Google'],
    '8.8.8.8' => ['send_email' => true, 'send_telegram' => true, 'web' => true, 'name' => 'DNS'],
    '192.168.1.1' => ['send_email' => false, 'send_telegram' => true, 'web' => true, 'name' => 'Local'],
],
```

Per-host fields:

- `name` Optional display name
- `send_email` Enable/disable email notifications for this host
- `send_telegram` Enable/disable Telegram notifications for this host  
- `web` Show/hide this host on the HTML status page

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

Example UPS config (SNMP v2c):

```php
'ups_state_file' => __DIR__ . '/../state/ups_state.json',
'ups' => [
    [
        'name' => 'UPS Server Room',
        'ip' => '172.16.2.250',  // Use ip:port for non-standard SNMP ports
        'send_email' => true,
        'send_telegram' => true,
        'web' => true,
        'snmp_version' => '2c',
        'snmp_community' => 'your-snmp-community',
        'snmp_v3_username' => '',
        'snmp_v3_auth_protocol' => 'SHA',
        'snmp_v3_auth_password' => '',
        'snmp_v3_priv_protocol' => 'AES',
        'snmp_v3_priv_password' => '',
        'oid_capacity' => '1.3.6.1.4.1.x.x.x',
        'oid_runtime' => '1.3.6.1.4.1.x.x.x',
        'oid_time_on_battery' => '1.3.6.1.4.1.x.x.x',
        'oid_battery_status' => '1.3.6.1.4.1.x.x.x',
        // Optional: override global thresholds for this UPS
        'thresholds' => [
            'warning' => 85,  // % and below (overrides global 90)
            'critical' => 40,  // % and below (overrides global 50)
        ],
    ],
    [
        'name' => 'UPS Network Closet',
        'ip' => '172.16.2.251:1161',  // Non-standard SNMP port
        'send_email' => true,
        'send_telegram' => true,
        'web' => true,
        'snmp_version' => '2c',
        'snmp_community' => 'your-snmp-community',
        'oid_capacity' => '1.3.6.1.4.1.x.x.x',
        'oid_runtime' => '1.3.6.1.4.1.x.x.x',
        'oid_time_on_battery' => '1.3.6.1.4.1.x.x.x',
        'oid_battery_status' => '1.3.6.1.4.1.x.x.x',
        // Uses global thresholds (warning=90, critical=50)
    ],
],
```

### UPS thresholds

- Global thresholds can be set in `ups_thresholds` section:
  ```php
  'ups_thresholds' => [
      'warning' => 90,  // % and below
      'critical' => 50, // % and below
  ],
  ```
- Per-UPS thresholds can be set in `thresholds` section for each UPS
- Per-UPS thresholds override global settings
- If no thresholds are specified, defaults are used (warning=90, critical=50)

### UPS notification fields

- `send_email` Enable/disable email notifications for this UPS
- `send_telegram` Enable/disable Telegram notifications for this UPS
- Both fields default to `true` if not specified
- You can disable one channel while keeping the other active

### Host notification fields

- `send_email` Enable/disable email notifications for this host
- `send_telegram` Enable/disable Telegram notifications for this host
- Both fields default to `true` if not specified
- You can disable one channel while keeping the other active

### SNMP ports

- Use standard port 161 or specify non-standard ports with `ip:port` format
- Example: `'ip' => '172.16.2.250:1161'` for port 1161

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
- `--ups_ip=172.16.2.250` Check only one UPS target by IP (supports ip:port for non-standard SNMP ports)
- `--logs_std` Log to stdout instead of log files

UPS test example (stdout logs, no notifications):

```bash
php run.php --ups_ip=172.16.2.250 --logs_std --disable_email --disable_telegram
```

Notes about `--ups_ip`:

- `--ups_ip` does not contain SNMP credentials or OIDs.
- It only selects one UPS entry from `config/config.php` (from the `ups` section) by matching `ip`.
- SNMP settings (`snmp_version`, `snmp_community`, SNMP v3 credentials) and OIDs are always taken from the matching UPS entry in the config.
- Supports `ip:port` format for non-standard SNMP ports (e.g., `--ups_ip=172.16.2.250:1161`).
- If there is no UPS with that `ip` in the config, UPS monitoring will be skipped.

## Cron example

Run every minute:

```bash
* * * * * /usr/bin/php /path/to/pingmonit/run.php >/dev/null 2>&1
```

## Parallel Processing

PingMonit supports parallel checking of hosts and UPS devices for improved performance:

### How it works
- Each host/UPS runs in a separate process
- Process IDs are shown in logs for easy identification
- All processes share the same state repository for consistency
- All processes run simultaneously, then results are collected

### Log format with process IDs
```
[2026-02-27 12:30:01] [8.8.8.8] Ping 8.8.8.8: OK
[2026-02-27 12:30:01] [1.1.1.1] Ping 1.1.1.1: FAIL
[2026-02-27 12:30:01] [UPS-172.16.2.250] UPS 172.16.2.250 capacity=85%
[2026-02-27 12:30:01] [192.168.1.1] Ping 192.168.1.1: OK
```

### Benefits
- ✅ **Faster execution** - Multiple hosts checked simultaneously
- ✅ **Clear identification** - Each log entry shows which target
- ✅ **Better debugging** - Easy to trace specific host issues
- ✅ **Maintained compatibility** - Existing configs work unchanged

### Requirements
- Linux environment with `pcntl` extension
- No configuration changes required
- Automatic fallback to sequential mode if `pcntl` not available

## Log Rotation

PingMonit supports automatic log rotation using `logrotate`:

### Setup

1. **Copy logrotate configuration:**
```bash
sudo cp logrotate.conf /etc/logrotate.d/pingmonit
```

2. **Run setup script (optional):**
```bash
sudo chmod +x setup-logrotate.sh
sudo ./setup-logrotate.sh
```

### Configuration Details

- **Retention:** 30 days
- **Rotation:** Daily
- **Compression:** Enabled (gzip)
- **Log format:** `YYYY-MM-DD.log`
- **Permissions:** `644 www-data www-data`

### Manual Testing

```bash
# Test configuration
sudo logrotate -d /etc/logrotate.d/pingmonit

# Force rotation
sudo logrotate -f /etc/logrotate.d/pingmonit --force

# Dry run
sudo logrotate -f /etc/logrotate.d/pingmonit --dry-run
```

### Log Files Location

Default log directory: `logs/` (relative to PingMonit root)

Example log files:
```
logs/
├── pingmonit-20260227.log
├── pingmonit-20260226.log
├── pingmonit-20260225.log
└── pingmonit-20260224.log.gz
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