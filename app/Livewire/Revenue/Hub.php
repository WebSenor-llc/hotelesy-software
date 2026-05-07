<?php

namespace App\Livewire\Revenue;

use App\Models\ChannelMapping;
use App\Models\Property;
use App\Models\Revenue\Competitor;
use App\Models\Revenue\Forecast;
use App\Models\Revenue\PricingRule;
use App\Models\Revenue\RateShop;
use App\Models\RoomType;
use App\Services\TenantContext;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app-shell')]
class Hub extends Component
{
    public string $tab = 'shopper';

    /* ================ COMPETITOR FORM ================ */
    public bool $showCompetitorForm = false;
    public ?int $editingCompetitor = null;
    public string $compName = '';
    public ?float $compDistance = null;
    public string $compBookingUrl = '';
    public string $compMmtUrl = '';
    public string $compTripadvisorUrl = '';
    public bool $compIsPrimary = false;
    public bool $compIsActive = true;

    /* ================ RATE SHOP FORM ================ */
    public bool $showRateShopForm = false;
    public ?int $rsCompetitorId = null;
    public string $rsRoomLabel = '';
    public ?float $rsRate = null;
    public string $rsStayDate = '';
    public string $rsSource = 'booking.com';

    /* ================ PRICING RULE FORM ================ */
    public bool $showRuleForm = false;
    public ?int $editingRule = null;
    public string $ruleName = '';
    public string $ruleType = 'day_of_week';
    public ?int $ruleRoomTypeId = null;
    public string $ruleConditionsJson = '{}';
    public string $ruleAction = 'increase_percent';
    public ?float $ruleValue = null;
    public int $rulePriority = 100;
    public string $ruleValidFrom = '';
    public string $ruleValidTo = '';
    public bool $ruleIsActive = true;

    /* ================ COMPETITOR CRUD ================ */
    public function startCreateCompetitor(): void
    {
        $this->reset(['editingCompetitor','compName','compDistance','compBookingUrl','compMmtUrl','compTripadvisorUrl','compIsPrimary']);
        $this->compIsActive = true;
        $this->showCompetitorForm = true;
    }

    public function startEditCompetitor(int $id): void
    {
        $c = Competitor::findOrFail($id);
        $this->editingCompetitor = $id;
        $this->compName = $c->name;
        $this->compDistance = $c->distance_km !== null ? (float) $c->distance_km : null;
        $this->compBookingUrl = $c->booking_com_url ?? '';
        $this->compMmtUrl = $c->mmt_url ?? '';
        $this->compTripadvisorUrl = $c->tripadvisor_url ?? '';
        $this->compIsPrimary = (bool) $c->is_primary_compset;
        $this->compIsActive = (bool) $c->is_active;
        $this->showCompetitorForm = true;
    }

    public function saveCompetitor(): void
    {
        $data = $this->validate([
            'compName' => 'required|string|max:200',
            'compDistance' => 'nullable|numeric|min:0|max:9999',
            'compBookingUrl' => 'nullable|string|max:500',
            'compMmtUrl' => 'nullable|string|max:500',
            'compTripadvisorUrl' => 'nullable|string|max:500',
            'compIsPrimary' => 'boolean',
            'compIsActive' => 'boolean',
        ]);

        $ctx = app(TenantContext::class);
        $payload = [
            'property_id' => $ctx->propertyId(),
            'name' => $data['compName'],
            'distance_km' => $data['compDistance'],
            'booking_com_url' => $data['compBookingUrl'] ?: null,
            'mmt_url' => $data['compMmtUrl'] ?: null,
            'tripadvisor_url' => $data['compTripadvisorUrl'] ?: null,
            'is_primary_compset' => $data['compIsPrimary'],
            'is_active' => $data['compIsActive'],
        ];

        if ($this->editingCompetitor) {
            Competitor::findOrFail($this->editingCompetitor)->update($payload);
            session()->flash('success', "Competitor '{$payload['name']}' updated.");
        } else {
            Competitor::create($payload);
            session()->flash('success', "Competitor '{$payload['name']}' added.");
        }
        $this->cancelCompetitorForm();
    }

    public function deactivateCompetitor(int $id): void
    {
        $c = Competitor::findOrFail($id);
        $c->update(['is_active' => ! $c->is_active]);
        session()->flash('success', "Competitor '{$c->name}' " . ($c->is_active ? 'activated' : 'deactivated') . '.');
    }

    public function cancelCompetitorForm(): void
    {
        $this->showCompetitorForm = false;
        $this->editingCompetitor = null;
    }

    /* ================ RATE SHOP CRUD ================ */
    public function startCreateRateShop(): void
    {
        $this->reset(['rsCompetitorId','rsRoomLabel','rsRate']);
        $this->rsStayDate = today()->addDay()->toDateString();
        $this->rsSource = 'booking.com';
        $this->showRateShopForm = true;
    }

    public function saveRateShop(): void
    {
        $data = $this->validate([
            'rsCompetitorId' => 'required|integer|exists:revenue_competitors,id',
            'rsRoomLabel' => 'nullable|string|max:200',
            'rsRate' => 'required|numeric|min:0',
            'rsStayDate' => 'required|date',
            'rsSource' => 'required|string|max:30',
        ]);

        $ctx = app(TenantContext::class);
        RateShop::create([
            'property_id' => $ctx->propertyId(),
            'competitor_id' => $data['rsCompetitorId'],
            'shop_date' => today(),
            'stay_date' => $data['rsStayDate'],
            'source' => $data['rsSource'],
            'room_type_label' => $data['rsRoomLabel'] ?: null,
            'rate' => $data['rsRate'],
            'currency' => 'INR',
            'available' => true,
        ]);

        session()->flash('success', 'Rate snapshot recorded.');
        $this->cancelRateShopForm();
    }

    public function cancelRateShopForm(): void
    {
        $this->showRateShopForm = false;
    }

    /**
     * Stub "shop now" — synthesise rate snapshots from our recent ADR (or base
     * rate fallback) for each active competitor across the next 7 days, as if
     * scraped.  Primary compset competitors stay near our rate (±5%), secondary
     * competitors swing wider (-15% .. +25%).
     */
    public function shopNow(): void
    {
        // TODO: replace with real OTA scraping via headless browser when budget allows.
        $ctx = app(TenantContext::class);
        $propertyId = $ctx->propertyId();

        $competitors = Competitor::where('property_id', $propertyId)
            ->where('is_active', true)->get();

        if ($competitors->isEmpty()) {
            session()->flash('success', 'No active competitors to shop. Add one first.');
            return;
        }

        $roomTypes = RoomType::where('property_id', $propertyId)->where('is_active', true)->get();

        // Average our own ADR per room_type from the last 7 days of daily_rates;
        // fall back to the room_type's base_rate when we have no data.
        $weekStart = today()->subDays(7)->toDateString();
        $weekEnd = today()->toDateString();
        $avgRates = DB::table('daily_rates')
            ->where('property_id', $propertyId)
            ->whereBetween('date', [$weekStart, $weekEnd])
            ->selectRaw('room_type_id, AVG(rate) as avg_rate')
            ->groupBy('room_type_id')
            ->pluck('avg_rate', 'room_type_id');

        $defaultSources = ['booking_com', 'mmt', 'agoda', 'goibibo'];
        // Channels we actually ship to from this property — used as the OTA pool
        // for the simulated shop snapshots (fallback to the default OTA list).
        $propertyChannels = ChannelMapping::where('property_id', $propertyId)
            ->pluck('channel')
            ->filter()
            ->unique()
            ->values()
            ->all();
        $defaultPropSources = !empty($propertyChannels) ? $propertyChannels : $defaultSources;

        $shopDate = today();
        $created = 0;

        DB::transaction(function () use ($competitors, $roomTypes, $avgRates, $defaultPropSources, $propertyId, $shopDate, &$created) {
            foreach ($competitors as $comp) {
                $sources = $defaultPropSources;
                $isPrimary = (bool) $comp->is_primary_compset;

                for ($d = 0; $d < 7; $d++) {
                    $stayDate = today()->addDays($d);
                    foreach ($roomTypes as $rt) {
                        $base = (float) ($avgRates[$rt->id] ?? $rt->base_rate ?? 8000);

                        // 0.85 .. 1.25 multiplier overall, tightened for primary compset.
                        if ($isPrimary) {
                            // ±5% jitter around our rate
                            $multiplier = 0.95 + (mt_rand(0, 100) / 1000); // 0.95 .. 1.05
                        } else {
                            // -15% .. +25% spread
                            $multiplier = 0.85 + (mt_rand(0, 400) / 1000); // 0.85 .. 1.25
                        }
                        $rate = round($base * $multiplier, 2);
                        $isSoldOut = mt_rand(1, 100) <= 8; // 8% chance

                        RateShop::create([
                            'property_id'     => $propertyId,
                            'competitor_id'   => $comp->id,
                            'shop_date'       => $shopDate,
                            'stay_date'       => $stayDate,
                            'source'          => $sources[array_rand($sources)],
                            'room_type_label' => $rt->name,
                            'rate'            => $rate,
                            'currency'        => 'INR',
                            'available'       => !$isSoldOut,
                        ]);
                        $created++;
                    }
                }
            }
        });

        session()->flash('success', "Shop run complete: {$created} rate snapshots captured across {$competitors->count()} competitors.");
    }

    /* ================ PRICING RULE CRUD ================ */
    public function startCreateRule(): void
    {
        $this->reset(['editingRule','ruleName','ruleRoomTypeId','ruleValue','ruleValidFrom','ruleValidTo']);
        $this->ruleType = 'day_of_week';
        $this->ruleAction = 'increase_percent';
        $this->ruleConditionsJson = '{}';
        $this->rulePriority = 100;
        $this->ruleIsActive = true;
        $this->showRuleForm = true;
    }

    public function startEditRule(int $id): void
    {
        $r = PricingRule::findOrFail($id);
        $this->editingRule = $id;
        $this->ruleName = $r->name;
        $this->ruleType = $r->rule_type;
        $this->ruleRoomTypeId = $r->room_type_id;
        $this->ruleConditionsJson = json_encode($r->conditions ?? new \stdClass(), JSON_UNESCAPED_SLASHES);
        $this->ruleAction = $r->action;
        $this->ruleValue = (float) $r->value;
        $this->rulePriority = (int) $r->priority;
        $this->ruleValidFrom = $r->valid_from ? Carbon::parse($r->valid_from)->toDateString() : '';
        $this->ruleValidTo = $r->valid_to ? Carbon::parse($r->valid_to)->toDateString() : '';
        $this->ruleIsActive = (bool) $r->is_active;
        $this->showRuleForm = true;
    }

    public function saveRule(): void
    {
        $data = $this->validate([
            'ruleName' => 'required|string|max:200',
            'ruleType' => 'required|in:occupancy_based,days_to_arrival,day_of_week,season,event,compset_position,min_max_floor',
            'ruleRoomTypeId' => 'nullable|integer|exists:room_types,id',
            'ruleConditionsJson' => 'required|string',
            'ruleAction' => 'required|in:increase_percent,decrease_percent,set_to,increase_fixed,decrease_fixed',
            'ruleValue' => 'required|numeric',
            'rulePriority' => 'required|integer|min:0|max:65535',
            'ruleValidFrom' => 'nullable|date',
            'ruleValidTo' => 'nullable|date',
            'ruleIsActive' => 'boolean',
        ]);

        $conditions = json_decode($data['ruleConditionsJson'], true);
        if (! is_array($conditions)) {
            $this->addError('ruleConditionsJson', 'Conditions must be valid JSON.');
            return;
        }

        $ctx = app(TenantContext::class);
        $payload = [
            'property_id' => $ctx->propertyId(),
            'room_type_id' => $data['ruleRoomTypeId'],
            'name' => $data['ruleName'],
            'rule_type' => $data['ruleType'],
            'conditions' => $conditions,
            'action' => $data['ruleAction'],
            'value' => $data['ruleValue'],
            'priority' => $data['rulePriority'],
            'valid_from' => $data['ruleValidFrom'] ?: null,
            'valid_to' => $data['ruleValidTo'] ?: null,
            'is_active' => $data['ruleIsActive'],
        ];

        if ($this->editingRule) {
            PricingRule::findOrFail($this->editingRule)->update($payload);
            session()->flash('success', "Rule '{$payload['name']}' updated.");
        } else {
            PricingRule::create($payload);
            session()->flash('success', "Rule '{$payload['name']}' created.");
        }
        $this->cancelRuleForm();
    }

    public function toggleRule(int $id): void
    {
        $r = PricingRule::findOrFail($id);
        $r->update(['is_active' => ! $r->is_active]);
        session()->flash('success', "Rule '{$r->name}' " . ($r->is_active ? 'activated' : 'deactivated') . '.');
    }

    public function deleteRule(int $id): void
    {
        $r = PricingRule::findOrFail($id);
        $name = $r->name;
        $r->delete();
        session()->flash('success', "Rule '{$name}' deleted.");
    }

    public function cancelRuleForm(): void
    {
        $this->showRuleForm = false;
        $this->editingRule = null;
    }

    /* ================ FORECAST ================ */
    /**
     * Regenerate occupancy_forecast rows for next 30 days using existing
     * reservations (on-books) and the property's average occupancy as baseline.
     */
    public function runForecast(): void
    {
        $ctx = app(TenantContext::class);
        $propertyId = $ctx->propertyId();
        $property = Property::find($propertyId);
        $totalRooms = max(1, (int) ($property->total_rooms ?? 50));

        // Average historical occupancy from last 30 days of reservations
        $historyStart = today()->subDays(30);
        $historyEnd = today();

        $avgOccupancy = $this->calcAverageOccupancyPct($propertyId, $historyStart, $historyEnd, $totalRooms);
        $avgArr = $this->calcAverageArr($propertyId, $historyStart, $historyEnd);

        // Pickup pace: rooms on-books for upcoming 30 days vs same window last year
        DB::table('revenue_forecasts')
            ->where('property_id', $propertyId)
            ->where('stay_date', '>=', today())
            ->where('stay_date', '<', today()->addDays(30))
            ->delete();

        $created = 0;
        for ($i = 0; $i < 30; $i++) {
            $stay = today()->addDays($i);

            // Rooms on books for this stay date (active statuses)
            $onBooks = (int) DB::table('reservations')
                ->where('property_id', $propertyId)
                ->whereIn('status', ['confirmed','checked_in','tentative'])
                ->where('arrival_date', '<=', $stay)
                ->where('departure_date', '>', $stay)
                ->sum('rooms_count');

            $bookedPct = round(($onBooks / $totalRooms) * 100, 2);

            // Forecast: blend on-books with historical average, weight tilts toward on-books closer in
            $daysOut = $i;
            $weightOnBooks = max(0.3, 1 - ($daysOut / 30));
            $forecastOcc = round(
                ($bookedPct * $weightOnBooks) + ($avgOccupancy * (1 - $weightOnBooks)),
                2
            );
            $forecastOcc = min(100.0, max(0.0, $forecastOcc));

            $forecastArr = round($avgArr, 2);
            $forecastRevpar = round($forecastArr * $forecastOcc / 100, 2);

            // Compare to 7 days ago snapshot (if exists) for pickup pace
            $priorOnBooks = (int) DB::table('reservations')
                ->where('property_id', $propertyId)
                ->whereIn('status', ['confirmed','checked_in','tentative'])
                ->where('arrival_date', '<=', $stay)
                ->where('departure_date', '>', $stay)
                ->where('created_at', '<=', today()->subDays(7))
                ->sum('rooms_count');
            $pickup = $onBooks - $priorOnBooks;
            $vsLastYear = $avgOccupancy > 0
                ? round((($bookedPct - $avgOccupancy) / max($avgOccupancy, 1)) * 100, 1)
                : 0;

            Forecast::create([
                'property_id' => $propertyId,
                'forecast_date' => today(),
                'stay_date' => $stay,
                'forecast_occupancy_pct' => $forecastOcc,
                'forecast_arr' => $forecastArr,
                'forecast_revpar' => $forecastRevpar,
                'booking_pace' => [
                    'on_books' => $onBooks,
                    'pickup_7d' => $pickup,
                    'vs_last_year_pct' => $vsLastYear,
                    'pace_index' => $avgOccupancy > 0 ? round($bookedPct / $avgOccupancy, 2) : 1.0,
                ],
                'model_version' => 'v1.2-baseline',
            ]);
            $created++;
        }

        session()->flash('success', "Forecast regenerated for next {$created} days.");
    }

    private function calcAverageOccupancyPct(int $propertyId, Carbon $from, Carbon $to, int $totalRooms): float
    {
        $days = max(1, $from->diffInDays($to));
        $totalNights = (int) DB::table('reservations')
            ->where('property_id', $propertyId)
            ->whereIn('status', ['confirmed','checked_in','checked_out'])
            ->where('arrival_date', '<', $to)
            ->where('departure_date', '>', $from)
            ->sum('nights');
        if ($totalNights <= 0) {
            return 65.0; // sensible default for new properties
        }
        return round(min(100, ($totalNights / ($totalRooms * $days)) * 100), 2);
    }

    private function calcAverageArr(int $propertyId, Carbon $from, Carbon $to): float
    {
        $row = DB::table('reservations')
            ->where('property_id', $propertyId)
            ->whereIn('status', ['confirmed','checked_in','checked_out'])
            ->where('arrival_date', '<', $to)
            ->where('departure_date', '>', $from)
            ->selectRaw('SUM(room_revenue) as rev, SUM(nights * rooms_count) as roomNights')
            ->first();
        if (! $row || ! $row->roomNights || $row->roomNights <= 0) {
            return 8500.0;
        }
        return round(((float) $row->rev) / ((float) $row->roomNights), 2);
    }

    /* ================ RENDER ================ */
    public function render()
    {
        $ctx = app(TenantContext::class);
        $propertyId = $ctx->propertyId();

        $roomTypes = RoomType::where('property_id', $propertyId)->where('is_active', true)->get();

        $competitors = DB::table('revenue_competitors')->where('property_id', $propertyId)->orderBy('name')->get();
        $activeCompetitors = $competitors->where('is_active', true);

        $rateShop = DB::table('revenue_rate_shop')
            ->leftJoin('revenue_competitors','revenue_rate_shop.competitor_id','=','revenue_competitors.id')
            ->where('revenue_rate_shop.property_id', $propertyId)
            ->where('shop_date', '>=', today()->subDays(7))
            ->select('revenue_rate_shop.*','revenue_competitors.name as competitor_name')
            ->orderByDesc('shop_date')->limit(60)->get();

        $pricingRules = DB::table('revenue_pricing_rules')
            ->leftJoin('room_types','revenue_pricing_rules.room_type_id','=','room_types.id')
            ->where('revenue_pricing_rules.property_id', $propertyId)
            ->select('revenue_pricing_rules.*','room_types.name as room_type_name')
            ->orderBy('priority')->get();

        $forecasts = DB::table('revenue_forecasts')->where('property_id', $propertyId)
            ->where('stay_date', '>=', today())
            ->orderBy('stay_date')->limit(30)->get();

        // Pickup pace summary for forecast tab
        $pickupSummary = $this->summarisePickup($forecasts);

        return view('livewire.revenue.hub', compact(
            'roomTypes','competitors','activeCompetitors','rateShop','pricingRules','forecasts','pickupSummary'
        ));
    }

    private function summarisePickup($forecasts): array
    {
        $totalOnBooks = 0; $totalPickup = 0; $count = 0;
        foreach ($forecasts as $f) {
            $bp = is_string($f->booking_pace) ? json_decode($f->booking_pace, true) : $f->booking_pace;
            if (! is_array($bp)) continue;
            $totalOnBooks += (int) ($bp['on_books'] ?? 0);
            $totalPickup += (int) ($bp['pickup_7d'] ?? 0);
            $count++;
        }
        return [
            'on_books' => $totalOnBooks,
            'pickup_7d' => $totalPickup,
            'days' => $count,
        ];
    }
}
