<?php

namespace App\Modules\Platform\Services;

use App\Modules\Platform\Models\FeatureFlag;
use Illuminate\Support\Facades\Cache;

/**
 * Runtime feature gating. Reads the feature_flags table once and caches
 * the result in-memory + Laravel cache for 60 seconds. Use:
 *
 *   if (app(FeatureFlagService::class)->enabled('beta.dark_mode')) { ... }
 *
 * Fallback chain when a flag is missing: db value → env var
 * (FEATURE_<UPPER_CODE>=true) → false.
 */
class FeatureFlagService
{
    private const CACHE_KEY = 'platform:feature-flags';
    private const TTL_SECONDS = 60;

    private ?array $cache = null;

    public function enabled(string $code): bool
    {
        $row = $this->all()[$code] ?? null;
        if ($row !== null) {
            return (bool) $row['enabled'];
        }
        $env = strtoupper('FEATURE_' . str_replace(['.', '-'], '_', $code));
        return filter_var(env($env, false), FILTER_VALIDATE_BOOL);
    }

    public function config(string $code): array
    {
        return $this->all()[$code]['config'] ?? [];
    }

    public function all(): array
    {
        if ($this->cache !== null) return $this->cache;

        $this->cache = Cache::remember(self::CACHE_KEY, self::TTL_SECONDS, function () {
            try {
                return FeatureFlag::query()
                    ->get(['code', 'enabled', 'config'])
                    ->keyBy('code')
                    ->map(fn($f) => [
                        'enabled' => (bool) $f->enabled,
                        'config'  => $f->config ?? [],
                    ])
                    ->toArray();
            } catch (\Throwable) {
                return [];
            }
        });

        return $this->cache;
    }

    public function flush(): void
    {
        $this->cache = null;
        Cache::forget(self::CACHE_KEY);
    }
}
