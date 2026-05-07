<?php

namespace App\Services\Reviews;

use App\Models\Property;
use App\Models\Reviews\Review;
use App\Models\Reviews\ReviewSource;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * ReviewAggregator — pulls reviews from external platforms, stores them
 * in the unified `reviews` table.
 *
 * Currently supports:
 *   - Google Business Profile (via Google Business Profile API)
 *   - TripAdvisor (via TripAdvisor Content API — partner access required)
 *   - Booking.com (via Booking Connectivity API — partner access required)
 *
 * Each source is stubbed at the API-call level. To go live:
 *   1. Sign partner agreement with each platform
 *   2. Get API credentials, store via `review_sources.credentials` (encrypted)
 *   3. Flesh out the fetchFrom*() methods
 *
 * Run via scheduled command: `php artisan reviews:fetch`
 */
class ReviewAggregator
{
    public function fetchAllSources(Property $property): array
    {
        $stats = ['fetched' => 0, 'created' => 0, 'updated' => 0, 'failed' => 0];

        $sources = ReviewSource::where('property_id', $property->id)
            ->where('is_active', true)
            ->where('auto_fetch', true)
            ->get();

        foreach ($sources as $source) {
            try {
                $reviews = $this->fetchFrom($property, $source);
                foreach ($reviews as $reviewData) {
                    $existing = Review::where('source', $source->source)
                        ->where('external_review_id', $reviewData['external_id'])
                        ->first();

                    if ($existing) {
                        $existing->update($this->mapForUpdate($reviewData));
                        $stats['updated']++;
                    } else {
                        Review::create(array_merge($this->mapForCreate($property, $source, $reviewData), [
                            'tenant_id' => $property->tenant_id,
                            'property_id' => $property->id,
                            'source_id' => $source->id,
                            'source' => $source->source,
                        ]));
                        $stats['created']++;
                    }
                    $stats['fetched']++;
                }
                $source->update(['last_fetched_at' => now()]);
            } catch (\Throwable $e) {
                Log::error('ReviewAggregator failed', [
                    'property_id' => $property->id,
                    'source' => $source->source,
                    'error' => $e->getMessage(),
                ]);
                $stats['failed']++;
            }
        }

        return $stats;
    }

    private function fetchFrom(Property $property, ReviewSource $source): array
    {
        return match ($source->source) {
            ReviewSource::SOURCE_GOOGLE => $this->fetchFromGoogle($property, $source),
            ReviewSource::SOURCE_TRIPADVISOR => $this->fetchFromTripAdvisor($property, $source),
            ReviewSource::SOURCE_BOOKING_COM => $this->fetchFromBookingCom($property, $source),
            default => [],
        };
    }

    /**
     * Google Business Profile API — accounts.locations.reviews.list
     * https://developers.google.com/my-business/reference/rest/v4/accounts.locations.reviews/list
     *
     * Requires:
     *   - OAuth2 access token in $source->credentials['access_token']
     *   - Refresh token handling for long-lived access
     *   - Account ID + Location ID
     */
    private function fetchFromGoogle(Property $property, ReviewSource $source): array
    {
        $creds = $source->credentials ?? [];
        if (empty($creds['access_token']) || empty($source->external_property_id)) {
            return [];
        }

        // STUB: Real implementation:
        // $response = Http::withToken($creds['access_token'])
        //     ->get("https://mybusiness.googleapis.com/v4/accounts/{$creds['account_id']}/locations/{$source->external_property_id}/reviews");
        // foreach ($response->json('reviews') as $r) {
        //     yield [
        //         'external_id' => $r['reviewId'],
        //         'reviewer_name' => $r['reviewer']['displayName'] ?? null,
        //         'rating' => match($r['starRating']) { 'ONE'=>1, 'TWO'=>2, 'THREE'=>3, 'FOUR'=>4, 'FIVE'=>5, default=>0 },
        //         'body' => $r['comment'] ?? null,
        //         'review_date' => Carbon::parse($r['createTime'])->toDateString(),
        //         'response_text' => $r['reviewReply']['comment'] ?? null,
        //         'raw' => $r,
        //     ];
        // }
        return [];
    }

    /**
     * TripAdvisor Content API
     * https://developer-tripadvisor.com/content-api/documentation/
     *
     * Endpoint: /location/{locationId}/reviews
     * Auth: API key in 'X-TripAdvisor-API-Key' header
     * Pagination: 5 reviews per page max; use ?offset= and ?limit=
     * Rate limit: 50 calls/second
     *
     * Response shape: { data: [ { id, lang, rating (1-5), title, text,
     *   trip_type, travel_date, published_date, user: {username, user_location: {name}},
     *   subratings: { '0': {name:'Value',value}, '1':{name:'Rooms',value}, ... }
     * } ], paging: { results, total_results, next } }
     */
    private function fetchFromTripAdvisor(Property $property, ReviewSource $source): array
    {
        $apiKey = $source->credentials['api_key'] ?? config('services.tripadvisor.api_key');
        $locationId = $source->external_property_id;

        if (! $apiKey || ! $locationId) {
            Log::info('TripAdvisor source not fully configured', ['property_id' => $property->id]);
            return [];
        }

        $reviews = [];
        $offset = 0;
        $limit = 5;
        $maxPages = 20; // 100 reviews max per fetch run

        for ($page = 0; $page < $maxPages; $page++) {
            try {
                $response = Http::withHeaders(['X-TripAdvisor-API-Key' => $apiKey])
                    ->acceptJson()
                    ->timeout(20)
                    ->get("https://api.content.tripadvisor.com/api/v1/location/{$locationId}/reviews", [
                        'language' => 'en',
                        'offset' => $offset,
                        'limit' => $limit,
                    ]);

                if (! $response->successful()) {
                    Log::warning('TripAdvisor API non-200', [
                        'status' => $response->status(),
                        'body' => $response->body(),
                    ]);
                    break;
                }

                $body = $response->json();
                $items = $body['data'] ?? [];
                if (empty($items)) break;

                foreach ($items as $r) {
                    $reviews[] = [
                        'external_id' => (string) $r['id'],
                        'reviewer_name' => $r['user']['username'] ?? 'TripAdvisor User',
                        'reviewer_country' => $r['user']['user_location']['name'] ?? null,
                        'review_date' => $r['published_date']
                            ? substr($r['published_date'], 0, 10)
                            : now()->toDateString(),
                        'stay_date' => isset($r['travel_date']) ? substr($r['travel_date'], 0, 10) : null,
                        // TripAdvisor uses 1-5 scale already, no normalisation needed
                        'rating' => (float) ($r['rating'] ?? 0),
                        'original_rating' => (float) ($r['rating'] ?? 0),
                        'original_rating_max' => 5,
                        'title' => $r['title'] ?? null,
                        'body' => $r['text'] ?? null,
                        'aspect_ratings' => $this->parseTripAdvisorSubratings($r['subratings'] ?? []),
                        'language' => $r['lang'] ?? 'en',
                        'response_text' => $r['owner_response']['text'] ?? null,
                        'raw' => $r,
                    ];
                }

                // Pagination
                if (count($items) < $limit) break; // Last page
                $offset += $limit;
            } catch (\Throwable $e) {
                Log::error('TripAdvisor fetch threw', [
                    'property_id' => $property->id,
                    'offset' => $offset,
                    'error' => $e->getMessage(),
                ]);
                break;
            }
        }

        return $reviews;
    }

    private function parseTripAdvisorSubratings(array $subratings): array
    {
        // TripAdvisor returns: { '0': {name:'Value', value:5}, '1':{name:'Rooms', value:4}, ... }
        $out = [];
        foreach ($subratings as $sub) {
            if (isset($sub['name'], $sub['value'])) {
                $key = strtolower(str_replace(' ', '_', $sub['name']));
                $out[$key] = (float) $sub['value'];
            }
        }
        return $out;
    }

    /**
     * Booking.com Connectivity API — review feedback endpoint.
     */
    private function fetchFromBookingCom(Property $property, ReviewSource $source): array
    {
        // STUB: Real implementation requires Booking Connectivity API certification.
        return [];
    }

    private function mapForCreate(Property $property, ReviewSource $source, array $data): array
    {
        return [
            'external_review_id' => $data['external_id'],
            'reviewer_name' => $data['reviewer_name'] ?? 'Anonymous',
            'reviewer_country' => $data['reviewer_country'] ?? null,
            'review_date' => $data['review_date'],
            'stay_date' => $data['stay_date'] ?? null,
            'rating' => $data['rating'],
            'original_rating' => $data['original_rating'] ?? $data['rating'],
            'original_rating_max' => $data['original_rating_max'] ?? 5,
            'title' => $data['title'] ?? null,
            'body' => $data['body'] ?? null,
            'aspect_ratings' => $data['aspect_ratings'] ?? null,
            'sentiment' => $this->classifySentiment($data['rating']),
            'language' => $data['language'] ?? 'en',
            'response_text' => $data['response_text'] ?? null,
            'raw_payload' => $data['raw'] ?? null,
        ];
    }

    private function mapForUpdate(array $data): array
    {
        return [
            'rating' => $data['rating'],
            'body' => $data['body'] ?? null,
            'response_text' => $data['response_text'] ?? null,
            'raw_payload' => $data['raw'] ?? null,
        ];
    }

    private function classifySentiment(float $rating): string
    {
        if ($rating >= 4.0) return Review::SENTIMENT_POSITIVE;
        if ($rating <= 2.5) return Review::SENTIMENT_NEGATIVE;
        return Review::SENTIMENT_NEUTRAL;
    }
}
