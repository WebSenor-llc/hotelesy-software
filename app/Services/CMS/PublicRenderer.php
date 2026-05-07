<?php

namespace App\Services\CMS;

use App\Models\CMS\Page;
use App\Models\CMS\Site;
use Illuminate\Support\Facades\Cache;

/**
 * PublicRenderer — resolves a hostname (subdomain or custom domain) to a CMS site
 * and renders the requested page.
 *
 * Caching: site lookup cached 5 min, page render cached 1 hr (busts on publish).
 *
 * Page rendering: each page is a JSON `blocks` array. The frontend (React
 * via Inertia) maps block types to React components: HeroBlock, GalleryBlock,
 * RoomGridBlock, AmenityListBlock, TextBlock, CtaBlock, ContactFormBlock, MapBlock.
 *
 * The booking widget injects automatically on pages of type 'home' and 'rooms'
 * if the site has booking_engine_enabled.
 */
class PublicRenderer
{
    public function resolveSiteFromHost(string $host): ?Site
    {
        return Cache::remember("cms:site:host:{$host}", 300, function () use ($host) {
            // Custom domain match first
            $site = Site::where('custom_domain', $host)
                ->where('domain_verified', true)
                ->where('is_published', true)
                ->first();

            if ($site) return $site;

            // Subdomain match — first label of host
            $parts = explode('.', $host);
            $subdomain = $parts[0] ?? null;
            if (! $subdomain) return null;

            return Site::where('subdomain', $subdomain)
                ->where('is_published', true)
                ->first();
        });
    }

    public function renderPage(Site $site, string $slug): ?array
    {
        $cacheKey = "cms:page:{$site->id}:{$slug}";

        return Cache::remember($cacheKey, 3600, function () use ($site, $slug) {
            $page = Page::where('site_id', $site->id)
                ->where('slug', $slug)
                ->where('is_published', true)
                ->first();

            if (! $page) return null;

            return [
                'page' => [
                    'id' => $page->id,
                    'slug' => $page->slug,
                    'title' => $page->title,
                    'page_type' => $page->page_type,
                    'blocks' => $this->processBlocks($page->blocks ?? [], $site),
                    'seo' => $page->seo_meta,
                ],
                'site' => [
                    'id' => $site->id,
                    'title' => $site->site_title,
                    'description' => $site->site_description,
                    'logo' => $site->logo_url,
                    'favicon' => $site->favicon_url,
                    'theme' => $site->theme,
                    'theme_config' => $site->theme_config,
                    'analytics' => [
                        'ga_id' => $site->analytics_ga_id,
                        'meta_pixel' => $site->analytics_meta_pixel,
                        'gtm_id' => $site->analytics_gtm_id,
                    ],
                    'contact' => $site->contact,
                    'social' => $site->social_links,
                    'booking_engine_enabled' => $site->booking_engine_enabled,
                ],
                'navigation' => $this->buildNavigation($site),
            ];
        });
    }

    public function bustCacheForSite(Site $site): void
    {
        Cache::forget("cms:site:host:{$site->subdomain}");
        if ($site->custom_domain) {
            Cache::forget("cms:site:host:{$site->custom_domain}");
        }
        $site->pages()->pluck('slug')->each(function ($slug) use ($site) {
            Cache::forget("cms:page:{$site->id}:{$slug}");
        });
    }

    private function processBlocks(array $blocks, Site $site): array
    {
        // Resolve dynamic blocks: room_grid pulls live RoomTypes,
        // amenity_list pulls Amenities, etc. Static blocks pass through.
        return array_map(function ($block) use ($site) {
            return match ($block['type'] ?? null) {
                'room_grid' => $this->resolveRoomGrid($block, $site),
                'amenity_list' => $this->resolveAmenityList($block, $site),
                'review_widget' => $this->resolveReviewWidget($block, $site),
                default => $block,
            };
        }, $blocks);
    }

    private function resolveRoomGrid(array $block, Site $site): array
    {
        $rooms = \App\Models\RoomType::where('property_id', $site->property_id)
            ->where('is_active', true)
            ->get(['id', 'name', 'description', 'base_rate', 'max_occupancy'])
            ->all();

        return array_merge($block, ['data' => ['rooms' => $rooms]]);
    }

    private function resolveAmenityList(array $block, Site $site): array
    {
        $amenities = \App\Models\Amenities\Amenity::where('property_id', $site->property_id)
            ->where('is_active', true)
            ->where('available_at_booking', true)
            ->get(['id', 'name', 'description', 'price', 'pricing_type', 'image_urls'])
            ->all();

        return array_merge($block, ['data' => ['amenities' => $amenities]]);
    }

    private function resolveReviewWidget(array $block, Site $site): array
    {
        $reviews = \App\Models\Reviews\Review::where('property_id', $site->property_id)
            ->where('rating', '>=', 4.0)
            ->where('is_visible', true)
            ->latest('review_date')
            ->limit($block['limit'] ?? 6)
            ->get(['reviewer_name', 'rating', 'title', 'body', 'review_date', 'source'])
            ->all();

        $avgRating = (float) \App\Models\Reviews\Review::where('property_id', $site->property_id)
            ->where('is_visible', true)
            ->avg('rating');

        return array_merge($block, ['data' => [
            'reviews' => $reviews,
            'average_rating' => round($avgRating, 1),
        ]]);
    }

    private function buildNavigation(Site $site): array
    {
        return $site->publishedPages()
            ->orderBy('sort_order')
            ->get(['slug', 'title', 'page_type'])
            ->map(fn($p) => ['slug' => $p->slug, 'title' => $p->title, 'type' => $p->page_type])
            ->all();
    }
}
