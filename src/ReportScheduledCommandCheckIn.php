<?php

declare(strict_types=1);

namespace Bisual\Monitor;

use Illuminate\Console\Events\CommandFinished;
use Illuminate\Console\Events\CommandStarting;
use Throwable;

final class ReportScheduledCommandCheckIn
{
    /** @var array<string, true> */
    private static array $started = [];

    public function __construct(private readonly BisualMonitorClient $client) {}

    public function handleStarting(CommandStarting $event): void
    {
        $name = (string) $event->command;
        if ($name === '' || $this->shouldSkip($name)) {
            return;
        }

        self::$started[$name] = true;
        $this->client->reportStart($this->client->resolveSlug($name));
    }

    public function handleFinished(CommandFinished $event): void
    {
        $name = (string) $event->command;
        if ($name === '' || $this->shouldSkip($name) || ! isset(self::$started[$name])) {
            return;
        }

        unset(self::$started[$name]);
        $slug = $this->client->resolveSlug($name);

        if ($event->exitCode === 0) {
            $this->client->reportEnd($slug, 'Command finished successfully');

            return;
        }

        $this->client->reportError(
            $slug,
            "Command exited with code {$event->exitCode}",
            null,
            null,
        );
    }

    private function shouldSkip(string $command_name): bool
    {
        if ($this->client->isCommandDisabled($command_name)) {
            return true;
        }

        // Skip framework noise
        return str_starts_with($command_name, 'schedule:')
            || str_starts_with($command_name, 'queue:')
            || in_array($command_name, ['list', 'help', 'inspire'], true);
    }
}
