# bisual/bisual-monitor

Laravel package that reports Artisan command check-ins (`start` / `end` / `error`) to Bisual **external status monitors**, similar in spirit to [Sentry Crons](https://docs.sentry.io/platforms/php/guides/laravel/crons/).

## Requirements

- PHP 8.2+
- Laravel 10–13

## Installation

```bash
composer require bisual/bisual-monitor
php artisan vendor:publish --tag=bisual-monitor-config
```

## Configuration

`.env`:

```env
BISUAL_MONITOR_ENABLED=true
BISUAL_MONITOR_COMPANY_UUID=your-company-uuid
BISUAL_MONITOR_COMPANY_PRODUCT_UUID=your-product-uuid
# Optional override for local / staging API:
# BISUAL_MONITOR_ENDPOINT=http://localhost:8000/api/v1/companies/{company_uuid}/company-products/{company_product_uuid}/status-monitors/exec
```

`config/bisual-monitor.php` is published for deeper customization (timeouts, slug prefix).

## How it works

1. On `CommandStarting`, the package POSTs `action=start` with `slug` = command name.
2. On `CommandFinished` with exit code `0`, it POSTs `action=end`.
3. On non-zero exit, it POSTs `action=error`.
4. Bisual auto-creates an **external** monitor for the slug on first ingest (unique per company product).
5. Failures talking to Bisual **never** fail your command.

## Disable reporting for a command

In your command class:

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

Or anywhere in the app:

```php
app(\Bisual\Monitor\BisualMonitorClient::class)->disableCommand('company-product-status-monitor:exec');
```

Typical commands to disable on the Bisual Platform itself: the monitor **exec** / **evaluate** commands (avoid recursion).

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

`0.1.0`
