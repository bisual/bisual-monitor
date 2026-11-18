<?php

declare(strict_types=1);

namespace Bisual\Monitor\Concerns;

use Bisual\Monitor\BisualMonitorClient;

trait DisablesBisualMonitor
{
    protected function disableBisualMonitor(): void
    {
        $name = $this->getName();
        if ($name === null || $name === '') {
            return;
        }

        app(BisualMonitorClient::class)->disableCommand($name);
    }
}
