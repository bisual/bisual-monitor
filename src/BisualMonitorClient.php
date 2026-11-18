<?php

declare(strict_types=1);

namespace Bisual\Monitor;

use Illuminate\Support\Facades\Http;
use Throwable;

final class BisualMonitorClient
{
    private const string INGEST_PATH = '/api/v1/companies/{company_uuid}/company-products/{company_product_uuid}/status-monitors/exec';

    /** @var array<string, true> */
    private array $disabled_commands = [];

    public function __construct(
        private bool $enabled,
        private string $company_uuid,
        private string $company_product_uuid,
        private string $host,
        private int $timeout_seconds,
        private string $slug_prefix,
    ) {}

    public function disableCommand(string $command_name): void
    {
        $this->disabled_commands[$command_name] = true;
    }

    public function isCommandDisabled(string $command_name): bool
    {
        return isset($this->disabled_commands[$command_name]);
    }

    public function reportStart(string $slug): void
    {
        $this->post($slug, 'start');
    }

    public function reportEnd(string $slug, ?string $output = null): void
    {
        $this->post($slug, 'end', [
            'output' => $output ?? 'OK',
        ]);
    }

    public function reportError(string $slug, string $error, ?string $output = null, ?string $error_stacktrace = null): void
    {
        $this->post($slug, 'error', [
            'output' => $output ?? $error,
            'error' => $error,
            // Backend validates required_if:action,error — fall back to the error message when no throwable was captured.
            'error_stacktrace' => $error_stacktrace ?? $error,
        ]);
    }

    public function resolveSlug(string $command_name): string
    {
        $slug = $command_name;
        if ($this->slug_prefix !== '') {
            $slug = rtrim($this->slug_prefix, ':').':'.$command_name;
        }

        return $slug;
    }

    /**
     * @param  array<string, mixed>  $extra
     */
    private function post(string $slug, string $action, array $extra = []): void
    {
        if (! $this->enabled || $this->company_uuid === '' || $this->company_product_uuid === '' || $this->host === '') {
            return;
        }

        $url = rtrim($this->host, '/').str_replace(
            ['{company_uuid}', '{company_product_uuid}'],
            [$this->company_uuid, $this->company_product_uuid],
            self::INGEST_PATH,
        );

        try {
            Http::timeout($this->timeout_seconds)
                ->acceptJson()
                ->asJson()
                ->post($url, array_merge([
                    'slug' => $slug,
                    'action' => $action,
                ], $extra));
        } catch (Throwable) {
            // Never break the host command because reporting failed.
        }
    }
}
