<?php

namespace App\Services;

use App\Models\Property;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Generates sequential, property-scoped, year-prefixed numbers for:
 *   - Reservations: PROPCODE/YYYY/00001
 *   - Folios: F/PROPCODE/YYYY/00001
 *   - Invoices: INV/PROPCODE/YYYY/00001
 *   - Receipts: RCT/PROPCODE/YYYY/00001
 *
 * Uses pessimistic locking (SELECT FOR UPDATE) on a counters table to prevent
 * duplicate numbers under concurrent load. For high-volume properties consider
 * pre-allocating ranges.
 */
class NumberGeneratorService
{
    public function reservationNumber(Property $property): string
    {
        return $this->generate($property, 'reservation', $property->code);
    }

    public function folioNumber(Property $property): string
    {
        return $this->generate($property, 'folio', 'F/' . $property->code);
    }

    public function invoiceNumber(Property $property): string
    {
        return $this->generate($property, 'invoice', 'INV/' . $property->code);
    }

    public function receiptNumber(Property $property): string
    {
        return $this->generate($property, 'receipt', 'RCT/' . $property->code);
    }

    /**
     * Generic generator for any other counter type (POS orders, KOTs, BOTs, GRNs, etc.).
     * The POS module calls this directly for pos_order numbers.
     */
    public function generate(Property $property, string $type, string $prefix): string
    {
        $year = now($property->timezone)->format('Y');
        $key = "counter:{$property->id}:{$type}:{$year}";

        return DB::transaction(function () use ($property, $type, $prefix, $year, $key) {
            // Use atomic increment via DB to avoid race conditions
            $counter = DB::table('number_counters')
                ->where('property_id', $property->id)
                ->where('type', $type)
                ->where('year', $year)
                ->lockForUpdate()
                ->first();

            if (!$counter) {
                DB::table('number_counters')->insert([
                    'property_id' => $property->id,
                    'type' => $type,
                    'year' => $year,
                    'last_number' => 1,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                $next = 1;
            } else {
                $next = $counter->last_number + 1;
                DB::table('number_counters')
                    ->where('property_id', $property->id)
                    ->where('type', $type)
                    ->where('year', $year)
                    ->update(['last_number' => $next, 'updated_at' => now()]);
            }

            return sprintf('%s/%s/%05d', $prefix, $year, $next);
        });
    }
}
