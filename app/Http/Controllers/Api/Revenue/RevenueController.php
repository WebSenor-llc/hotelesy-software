<?php

namespace App\Http\Controllers\Api\Revenue;

use App\Http\Controllers\Controller;
use App\Models\Property;
use App\Models\Revenue\Competitor;
use App\Models\Revenue\PricingRule;
use App\Models\Revenue\RateShop;
use App\Models\RoomType;
use App\Services\Revenue\DynamicPricingEngine;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class RevenueController extends Controller
{
    public function __construct(private readonly DynamicPricingEngine $engine) {}

    /**
     * GET /api/revenue/rate-recommendation?property_id=&room_type_id=&from=&to=
     */
    public function rateRecommendation(Request $request): JsonResponse
    {
        $data = $request->validate([
            'property_id' => ['required', 'integer', 'exists:properties,id'],
            'room_type_id' => ['required', 'integer', 'exists:room_types,id'],
            'from' => ['required', 'date'],
            'to' => ['required', 'date', 'after_or_equal:from'],
        ]);

        $property = Property::findOrFail($data['property_id']);
        $roomType = RoomType::findOrFail($data['room_type_id']);
        $from = Carbon::parse($data['from']);
        $to = Carbon::parse($data['to']);

        $results = [];
        for ($d = $from->copy(); $d->lte($to); $d->addDay()) {
            $results[] = array_merge(
                ['date' => $d->toDateString()],
                $this->engine->compute($property, $roomType, $d->copy()),
            );
        }

        return response()->json(['data' => $results]);
    }

    public function rateShop(Request $request): JsonResponse
    {
        $data = $request->validate([
            'property_id' => ['required', 'integer'],
            'from' => ['required', 'date'],
            'to' => ['required', 'date', 'after_or_equal:from'],
        ]);

        $rates = RateShop::where('property_id', $data['property_id'])
            ->whereBetween('stay_date', [$data['from'], $data['to']])
            ->with('competitor')
            ->orderBy('stay_date')
            ->get();

        return response()->json(['data' => $rates->groupBy(fn($r) => $r->stay_date->toDateString())]);
    }

    /* Pricing Rules CRUD */
    public function indexRules(Request $request): JsonResponse
    {
        $q = PricingRule::query();
        if ($request->filled('property_id')) $q->where('property_id', $request->integer('property_id'));
        return response()->json(['data' => $q->orderBy('priority')->get()]);
    }

    public function storeRule(Request $request): JsonResponse
    {
        $data = $request->validate([
            'property_id' => ['required', 'integer', 'exists:properties,id'],
            'room_type_id' => ['nullable', 'integer', 'exists:room_types,id'],
            'rate_plan_id' => ['nullable', 'integer', 'exists:rate_plans,id'],
            'name' => ['required', 'string', 'max:200'],
            'rule_type' => ['required', Rule::in([
                'occupancy_based', 'days_to_arrival', 'day_of_week',
                'season', 'event', 'compset_position', 'min_max_floor',
            ])],
            'conditions' => ['required', 'array'],
            'action' => ['required', Rule::in([
                'increase_percent', 'decrease_percent', 'set_to', 'increase_fixed', 'decrease_fixed',
            ])],
            'value' => ['required', 'numeric'],
            'valid_from' => ['nullable', 'date'],
            'valid_to' => ['nullable', 'date', 'after_or_equal:valid_from'],
            'priority' => ['nullable', 'integer', 'min:1', 'max:1000'],
        ]);

        $rule = PricingRule::create(array_merge($data, ['is_active' => true]));
        return response()->json(['data' => $rule], 201);
    }

    /* Competitors */
    public function indexCompetitors(Request $request): JsonResponse
    {
        $q = Competitor::where('is_active', true);
        if ($request->filled('property_id')) $q->where('property_id', $request->integer('property_id'));
        return response()->json(['data' => $q->get()]);
    }

    public function storeCompetitor(Request $request): JsonResponse
    {
        $data = $request->validate([
            'property_id' => ['required', 'integer'],
            'name' => ['required', 'string', 'max:200'],
            'booking_com_url' => ['nullable', 'url'],
            'mmt_url' => ['nullable', 'url'],
            'tripadvisor_url' => ['nullable', 'url'],
            'distance_km' => ['nullable', 'numeric', 'min:0'],
            'is_primary_compset' => ['nullable', 'boolean'],
        ]);
        return response()->json(['data' => Competitor::create($data)], 201);
    }
}
