<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SpraContactApiClient
{
    private const CATEGORIES_CACHE_KEY = 'spra_contact_categories';

    /**
     * @return array<int, array{id: string, name: string}>
     */
    public function getCategories(): array
    {
        $cached = Cache::get(self::CATEGORIES_CACHE_KEY);

        if (is_array($cached)) {
            return $cached;
        }

        [$base, $key] = $this->credentials();

        if (! $base || ! $key) {
            return [];
        }

        $response = Http::withHeaders(['X-Api-Key' => $key])
            ->timeout(10)
            ->get("{$base}/categories");

        if (! $response->successful()) {
            Log::error('[Spra Contact API] カテゴリ取得失敗', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return [];
        }

        $categories = $response->json('categories') ?? [];

        Cache::put(self::CATEGORIES_CACHE_KEY, $categories, now()->addHour());

        return $categories;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function submit(array $payload): bool
    {
        [$base, $key] = $this->credentials();

        if (! $base || ! $key) {
            Log::error('[Spra Contact API] SPRA_CONTACT_API_BASE / SPRA_CONTACT_API_KEY が未定義です。');

            return false;
        }

        $response = Http::withHeaders(['X-Api-Key' => $key])
            ->timeout(10)
            ->post($base, $payload);

        if (! $response->successful()) {
            Log::error('[Spra Contact API] 送信失敗', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return false;
        }

        return true;
    }

    /**
     * @return array{0: ?string, 1: ?string}
     */
    private function credentials(): array
    {
        return [
            config('services.spra.contact_api_base'),
            config('services.spra.contact_api_key'),
        ];
    }
}
