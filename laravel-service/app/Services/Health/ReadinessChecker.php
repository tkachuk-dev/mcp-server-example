<?php

declare(strict_types=1);

namespace App\Services\Health;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Throwable;

class ReadinessChecker
{
    private const string CACHE_PROBE_KEY = 'health:ready:probe';

    private const int CACHE_PROBE_TTL_SECONDS = 10;

    public function isReady(): bool
    {
        try {
            DB::connection()->getPdo();
            Cache::put(self::CACHE_PROBE_KEY, true, self::CACHE_PROBE_TTL_SECONDS);
            Cache::get(self::CACHE_PROBE_KEY);
            Queue::size();

            return true;
        } catch (Throwable $e) {
            // The reason stays in the logs only; the probe response never exposes it.
            Log::warning('Readiness check failed', ['exception' => $e::class, 'error' => $e->getMessage()]);

            return false;
        }
    }
}
