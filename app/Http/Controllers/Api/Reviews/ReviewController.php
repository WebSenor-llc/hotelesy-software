<?php

namespace App\Http\Controllers\Api\Reviews;

use App\Http\Controllers\Controller;
use App\Models\Reviews\Review;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReviewController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $q = Review::query()->with('source');
        if ($request->filled('property_id')) $q->where('property_id', $request->integer('property_id'));
        if ($request->filled('source')) $q->where('source', $request->string('source'));
        if ($request->filled('min_rating')) $q->where('rating', '>=', $request->float('min_rating'));
        if ($request->filled('sentiment')) $q->where('sentiment', $request->string('sentiment'));
        if ($request->filled('responded') === 'no') $q->whereNull('response_text');

        return response()->json(['data' => $q->latest('review_date')->paginate($request->integer('per_page', 20))]);
    }

    public function show(Review $review): JsonResponse
    {
        return response()->json(['data' => $review->load('source', 'reservation')]);
    }

    public function respond(Request $request, Review $review): JsonResponse
    {
        $data = $request->validate([
            'response_text' => ['required', 'string', 'max:2000'],
        ]);

        $review->update([
            'response_text' => $data['response_text'],
            'responded_at' => now(),
            'responded_by' => auth()->id(),
        ]);

        return response()->json(['data' => $review->fresh()]);
    }

    public function summary(Request $request): JsonResponse
    {
        $q = Review::query();
        if ($request->filled('property_id')) $q->where('property_id', $request->integer('property_id'));
        $rows = $q->where('is_visible', true)->get();

        $bySource = $rows->groupBy('source')->map(fn($r) => [
            'count' => $r->count(),
            'avg_rating' => round($r->avg('rating'), 2),
        ]);

        return response()->json([
            'data' => [
                'total_reviews' => $rows->count(),
                'avg_rating' => round($rows->avg('rating'), 2),
                'by_source' => $bySource,
                'unresponded' => $rows->whereNull('response_text')->count(),
                'negative_count' => $rows->where('rating', '<=', 3.0)->count(),
            ],
        ]);
    }
}
