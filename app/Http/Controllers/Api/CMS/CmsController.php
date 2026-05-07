<?php

namespace App\Http\Controllers\Api\CMS;

use App\Http\Controllers\Controller;
use App\Models\CMS\Page;
use App\Models\CMS\Site;
use App\Services\CMS\PublicRenderer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CmsController extends Controller
{
    public function __construct(private readonly PublicRenderer $renderer) {}

    /**
     * Public endpoint — no auth, no tenant resolve.
     * GET /api/public/cms/site?host=mirajlakepalace.miraj-hotels.com
     */
    public function publicSite(Request $request): JsonResponse
    {
        $host = $request->query('host', $request->getHost());
        $site = $this->renderer->resolveSiteFromHost($host);
        if (! $site) return response()->json(['message' => 'Site not found.'], 404);

        return response()->json([
            'data' => [
                'id' => $site->id,
                'title' => $site->site_title,
                'description' => $site->site_description,
                'logo' => $site->logo_url,
                'theme' => $site->theme,
                'theme_config' => $site->theme_config,
                'navigation' => $site->publishedPages()->get(['slug', 'title', 'page_type'])->toArray(),
            ],
        ]);
    }

    /**
     * GET /api/public/cms/page?host=&slug=
     */
    public function publicPage(Request $request): JsonResponse
    {
        $host = $request->query('host', $request->getHost());
        $slug = $request->query('slug', 'home');

        $site = $this->renderer->resolveSiteFromHost($host);
        if (! $site) return response()->json(['message' => 'Site not found.'], 404);

        $rendered = $this->renderer->renderPage($site, $slug);
        if (! $rendered) return response()->json(['message' => 'Page not found.'], 404);

        return response()->json(['data' => $rendered]);
    }

    /* Authenticated admin endpoints */

    public function index(Request $request): JsonResponse
    {
        $q = Site::query();
        if ($request->filled('property_id')) $q->where('property_id', $request->integer('property_id'));
        return response()->json(['data' => $q->with('pages:id,site_id,slug,title,page_type,is_published')->get()]);
    }

    public function show(Site $site): JsonResponse
    {
        return response()->json(['data' => $site->load('pages')]);
    }

    public function publish(Site $site): JsonResponse
    {
        $site->update(['is_published' => true, 'published_at' => now()]);
        $this->renderer->bustCacheForSite($site);
        return response()->json(['data' => $site->fresh()]);
    }

    public function storePage(Request $request, Site $site): JsonResponse
    {
        $data = $request->validate([
            'slug' => ['required', 'string', 'max:100'],
            'title' => ['required', 'string', 'max:200'],
            'page_type' => ['required', \Illuminate\Validation\Rule::in([
                'home', 'rooms', 'dining', 'amenities', 'gallery', 'contact', 'about', 'offers', 'custom',
            ])],
            'blocks' => ['required', 'array'],
            'seo_meta' => ['nullable', 'array'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ]);

        $page = Page::create([
            'property_id' => $site->property_id,
            'site_id' => $site->id,
            ...$data,
            'is_published' => false,
        ]);

        return response()->json(['data' => $page], 201);
    }

    public function updatePage(Request $request, Page $page): JsonResponse
    {
        $data = $request->validate([
            'title' => ['nullable', 'string', 'max:200'],
            'blocks' => ['nullable', 'array'],
            'seo_meta' => ['nullable', 'array'],
            'is_published' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ]);
        $page->update($data);
        $this->renderer->bustCacheForSite($page->site);
        return response()->json(['data' => $page->fresh()]);
    }
}
