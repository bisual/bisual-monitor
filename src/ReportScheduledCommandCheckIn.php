<?php

declare(strict_types=1);

namespace Bisual\Monitor;

use Illuminate\Console\Events\ScheduledBackgroundTaskFinished;
use Illuminate\Console\Events\ScheduledTaskFailed;
use Illuminate\Console\Events\ScheduledTaskFinished;
use Illuminate\Console\Events\ScheduledTaskStarting;
use Illuminate\Console\Scheduling\CallbackEvent;
use Illuminate\Console\Scheduling\Event as SchedulingEvent;

final class ReportScheduledCommandCheckIn
{
    public function __construct(private readonly BisualMonitorClient $client) {}

    public function handleStarting(ScheduledTaskStarting $event): void
    {
        $task = $event->task;
        if ($this->shouldSkip($task)) {
            return;
        }

        $this->client->reportStart($this->resolveSlug($task));
    }

    public function handleFinished(ScheduledTaskFinished $event): void
    {
        $task = $event->task;
        if ($this->shouldSkip($task) || $task->runInBackground) {
            return;
        }

        if ($task->skippedBecauseOverlapping) {
            $this->client->reportEnd(
                $this->resolveSlug($task),
                'Skipped: overlapping run in progress',
            );

            return;
        }

        // Foreground non-zero exit: ScheduledTaskFailed follows with the exception.
        if ((int) $task->exitCode !== 0) {
            return;
        }

        $this->client->reportEnd(
            $this->resolveSlug($task),
            $this->resolveTaskOutput($task),
        );
    }

    public function handleFailed(ScheduledTaskFailed $event): void
    {
        $task = $event->task;
        if ($this->shouldSkip($task)) {
            return;
        }

        $exception = $event->exception;

        $this->client->reportError(
            $this->resolveSlug($task),
            $exception->getMessage(),
            $this->resolveTaskOutput($task),
            $exception->__toString(),
        );
    }

    public function handleBackgroundFinished(ScheduledBackgroundTaskFinished $event): void
    {
        $task = $event->task;
        if ($this->shouldSkip($task)) {
            return;
        }

        $slug = $this->resolveSlug($task);
        $output = $this->resolveTaskOutput($task);
        $exit_code = (int) ($task->exitCode ?? 0);

        if ($exit_code !== 0) {
            $this->client->reportError(
                $slug,
                "Scheduled command failed with exit code {$exit_code}",
                $output,
                null,
            );

            return;
        }

        $this->client->reportEnd($slug, $output);
    }

    private function shouldSkip(SchedulingEvent $task): bool
    {
        if ($this->isTaskMonitorDisabled($task)) {
            return true;
        }

        $name = $this->resolveMonitorName($task);

        return $name === '' || $this->client->isCommandDisabled($name);
    }

    private function isTaskMonitorDisabled(SchedulingEvent $task): bool
    {
        return (bool) ($task->bisualMonitorDisabled ?? false);
    }

    private function resolveSlug(SchedulingEvent $task): string
    {
        return $this->client->resolveSlug($this->resolveMonitorName($task));
    }

    private function resolveMonitorName(SchedulingEvent $task): string
    {
        if ($task instanceof CallbackEvent) {
            if (is_string($task->description) && $task->description !== '') {
                return $task->description;
            }

            return $task->getSummaryForDisplay();
        }

        $command = (string) ($task->command ?? '');
        if ($command !== '' && preg_match("/\bartisan\b(?:['\"]|\s)+(.+)$/i", $command, $matches) === 1) {
            $rest = trim($matches[1]);
            if (preg_match("/^['\"]([^'\"]+)['\"]|^(\S+)/", $rest, $name_matches) === 1) {
                $name = $name_matches[1] !== '' ? $name_matches[1] : ($name_matches[2] ?? '');
                if ($name !== '') {
                    return $name;
                }
            }
        }

        if (is_string($task->description) && $task->description !== '') {
            return $task->description;
        }

        return $task->getSummaryForDisplay();
    }

    private function resolveTaskOutput(SchedulingEvent $task): ?string
    {
        $path = $task->output;
        if (! is_string($path) || $path === '' || $path === '/dev/null' || strtoupper($path) === 'NUL') {
            return null;
        }

        if (! is_file($path)) {
            return null;
        }

        $contents = @file_get_contents($path);
        if (! is_string($contents)) {
            return null;
        }

        $contents = trim($contents);

        return $contents === '' ? null : $contents;
    }
}
