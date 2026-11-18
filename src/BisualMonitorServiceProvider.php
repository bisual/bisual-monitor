<?php

declare(strict_types=1);

namespace Bisual\Monitor;

use Illuminate\Console\Events\ScheduledBackgroundTaskFinished;
use Illuminate\Console\Events\ScheduledTaskFailed;
use Illuminate\Console\Events\ScheduledTaskFinished;
use Illuminate\Console\Events\ScheduledTaskStarting;
use Illuminate\Console\Scheduling\Event as SchedulingEvent;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

final class BisualMonitorServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/bisual-monitor.php', 'bisual-monitor');

        $this->app->singleton(BisualMonitorClient::class, function ($app): BisualMonitorClient {
            return new BisualMonitorClient(
                enabled: (bool) config('bisual-monitor.enabled', true),
                company_uuid: (string) config('bisual-monitor.company_uuid', ''),
                company_product_uuid: (string) config('bisual-monitor.company_product_uuid', ''),
                endpoint_template: (string) config('bisual-monitor.endpoint'),
                timeout_seconds: (int) config('bisual-monitor.timeout_seconds', 5),
                slug_prefix: (string) config('bisual-monitor.default_slug_prefix', ''),
            );
        });
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/bisual-monitor.php' => config_path('bisual-monitor.php'),
            ], 'bisual-monitor-config');
        }

        $this->registerScheduleMacros();

        Event::listen(ScheduledTaskStarting::class, [ReportScheduledCommandCheckIn::class, 'handleStarting']);
        Event::listen(ScheduledTaskFinished::class, [ReportScheduledCommandCheckIn::class, 'handleFinished']);
        Event::listen(ScheduledTaskFailed::class, [ReportScheduledCommandCheckIn::class, 'handleFailed']);
        Event::listen(ScheduledBackgroundTaskFinished::class, [ReportScheduledCommandCheckIn::class, 'handleBackgroundFinished']);
    }

    private function registerScheduleMacros(): void
    {
        if (SchedulingEvent::hasMacro('disableBisualMonitor')) {
            return;
        }

        SchedulingEvent::macro('disableBisualMonitor', function (): SchedulingEvent {
            /** @var SchedulingEvent $this */
            $this->bisualMonitorDisabled = true;

            return $this;
        });
    }
}
