<?php

declare(strict_types=1);

namespace IllumaLaw\HybridSearch\HealthChecks;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Spatie\Health\Checks\Check;
use Spatie\Health\Checks\Result;
use Spatie\Health\Enums\Status;
use Throwable;

final class ScoutEngineCheck extends Check
{
    public function run(): Result
    {
        $driver = $this->configString('scout.driver', 'database');

        if (in_array($driver, ['database', 'collection', 'null'], true)) {
            return (new Result(Status::skipped(), "Scout driver [{$driver}] does not use a remote engine; probe skipped."))
                ->shortSummary('Skipped');
        }

        $timeout = max(1, $this->configInt('health.scout.timeout_seconds', 5));

        try {
            return match ($driver) {
                'meilisearch' => $this->checkMeilisearch($timeout),
                'typesense' => $this->checkTypesense($timeout),
                'algolia' => $this->checkAlgolia($timeout),
                default => (new Result(Status::skipped(), "Scout driver [{$driver}] has no automated health probe."))
                    ->shortSummary('Skipped'),
            };
        } catch (Throwable $e) {
            return Result::make()->failed('Scout engine check failed: '.$e->getMessage());
        }
    }

    private function checkMeilisearch(int $timeout): Result
    {
        $host = rtrim($this->configString('scout.meilisearch.host', 'http://localhost:7700'), '/');
        $key = config('scout.meilisearch.key');

        $request = Http::timeout($timeout)->acceptJson();

        if (is_string($key) && $key !== '') {
            $request = $request->withToken($key);
        }

        $response = $request->get($host.'/health');
        $meta = ['driver' => 'meilisearch', 'url' => $host.'/health', 'status' => $response->status()];

        if (! $response->successful()) {
            return Result::make()->meta($meta)->failed('Meilisearch health endpoint returned a non-success status.');
        }

        return Result::make()->meta($meta + ['body' => $response->json()])->ok('Meilisearch is reachable.');
    }

    private function checkTypesense(int $timeout): Result
    {
        $settings = config('scout.typesense.client-settings');
        $settings = is_array($settings) ? $settings : [];

        $protocol = $this->arrString($settings, 'nodes.0.protocol', 'http');
        $host = $this->arrString($settings, 'nodes.0.host', 'localhost');
        $port = $this->arrString($settings, 'nodes.0.port', '8108');
        $path = trim($this->arrString($settings, 'nodes.0.path', ''), '/');
        $base = "{$protocol}://{$host}:{$port}";
        $url = ($path !== '' ? "{$base}/{$path}" : $base).'/health';

        $response = Http::timeout($timeout)
            ->withHeaders([
                'X-TYPESENSE-API-KEY' => $this->arrString($settings, 'api_key', ''),
            ])
            ->get($url);

        $meta = ['driver' => 'typesense', 'url' => $url, 'status' => $response->status()];

        if (! $response->successful()) {
            return Result::make()->meta($meta)->failed('Typesense health endpoint returned a non-success status.');
        }

        return Result::make()->meta($meta)->ok('Typesense is reachable.');
    }

    private function checkAlgolia(int $timeout): Result
    {
        $appId = $this->configString('scout.algolia.id');
        $secret = $this->configString('scout.algolia.secret');

        if ($appId === '' || $secret === '') {
            return Result::make()->failed('Algolia application id or secret is not configured.');
        }

        $url = "https://{$appId}-dsn.algolia.net/1/keys";

        $response = Http::timeout($timeout)
            ->withHeaders([
                'X-Algolia-Application-Id' => $appId,
                'X-Algolia-API-Key' => $secret,
            ])
            ->get($url);

        $meta = ['driver' => 'algolia', 'url' => $url, 'status' => $response->status()];

        if (! $response->successful()) {
            return Result::make()->meta($meta)->failed('Algolia API check failed.');
        }

        return Result::make()->meta($meta)->ok('Algolia credentials are valid.');
    }

    private function configString(string $key, string $default = ''): string
    {
        $value = config($key);

        return is_string($value) ? $value : $default;
    }

    private function configInt(string $key, int $default = 0): int
    {
        $value = config($key);

        return is_numeric($value) ? (int) $value : $default;
    }

    /** @param array<array-key, mixed> $array */
    private function arrString(array $array, string $key, string $default = ''): string
    {
        $value = Arr::get($array, $key);

        return is_string($value) ? $value : $default;
    }
}
