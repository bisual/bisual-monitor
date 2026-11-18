# bisual/bisual-monitor

Laravel package that reports **scheduler** check-ins (`start` / `end` / `error`) to Bisual **external status monitors**, similar in spirit to [Sentry Crons](https://docs.sentry.io/platforms/php/guides/laravel/crons/).

## Requirements

- PHP 8.2+
- Laravel 10–13

## Enabling (install)

No changes to `routes/console.php` are required to turn reporting on. After install + env, **every scheduled task** is reported automatically (`Schedule::command`, `Schedule::call`, etc.). Manual `php artisan …` runs are **not** reported.

```bash
composer require bisual/bisual-monitor
php artisan vendor:publish --tag=bisual-monitor-config
```

`.env`:

```env
BISUAL_MONITOR_ENABLED=true
BISUAL_MONITOR_COMPANY_UUID=your-company-uuid
BISUAL_MONITOR_COMPANY_PRODUCT_UUID=your-product-uuid
# Optional override for local / staging API:
# BISUAL_MONITOR_ENDPOINT=http://localhost:8000/api/v1/companies/{company_uuid}/company-products/{company_product_uuid}/status-monitors/exec
```

Also ensure on the Bisual product:

1. The product has an **active contract**.
2. The server IP is on the company **IP allowlist** (required in production when allowlists are configured).

`config/bisual-monitor.php` is optional after publish (timeouts, slug prefix, endpoint).

## How it works

1. `ScheduledTaskStarting` → POST `action=start` (`slug` = artisan command name, or callback description).
2. `ScheduledTaskFinished` (foreground, exit `0`) → POST `action=end`.
3. `ScheduledTaskFailed` → POST `action=error` (exception message + stack trace).
4. `ScheduledBackgroundTaskFinished` → `end` or `error` from the background exit code.
5. Command stdout is included when the schedule event writes output to a file (`sendOutputTo` / `appendOutputTo` / email capture). Default `/dev/null` yields no output.
6. Bisual auto-creates an **external** monitor for the slug on first ingest (unique per company product).
7. Failures talking to Bisual **never** fail your scheduled task.

## Disable reporting for a scheduled command

Same idea as Sentry’s `->sentryMonitor()`, but to **opt out** (monitoring is on by default):

```php
use Illuminate\Support\Facades\Schedule;

Schedule::command(SendEmailsCommand::class)
    ->everyHour()
    ->disableBisualMonitor();
```

This sets a flag on the schedule event (no env prefix). Typical use on the Bisual Platform itself: disable the monitor **exec** / **evaluate** commands to avoid recursion.

### Disable by command name (same process)

```php
use Bisual\Monitor\Concerns\DisablesBisualMonitor;

final class SensitiveCommand extends Command
{
    use DisablesBisualMonitor;

    public function handle(): int
    {
        $this->disableBisualMonitor();

        // ...
        return self::SUCCESS;
    }
}
```

Prefer `->disableBisualMonitor()` on the schedule definition for scheduled tasks. The trait / `disableCommand()` only affects reporting when called in the **same process** that runs `schedule:run` / `schedule:finish` before the task events fire.

```php
app(\Bisual\Monitor\BisualMonitorClient::class)->disableCommand('company-product-status-monitor:exec');
```

## Manual reporting

```php
$client = app(\Bisual\Monitor\BisualMonitorClient::class);
$client->reportStart('my:job');
try {
    // work…
    $client->reportEnd('my:job', 'done');
} catch (\Throwable $e) {
    $client->reportError('my:job', $e->getMessage(), null, $e->getTraceAsString());
    throw $e;
}
```

## Security notes

- The Bisual ingest endpoint validates **company IP allowlists** (production) and **active contract** on the product.
- Prefer reporting from servers whose IPs are allowlisted on the company products.

## Version

`0.1.2`
