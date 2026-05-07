<?php

namespace App\Http\Requests\Reservation;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreReservationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('reservations.create') ?? false;
    }

    public function rules(): array
    {
        return [
            'property_id' => ['required', 'integer', 'exists:properties,id'],

            // Guest details — guest_id OR walk-in fields required.
            'guest_id' => ['nullable', 'integer', 'exists:guests,id'],
            'company_id' => ['nullable', 'integer', 'exists:companies,id'],
            'guest_name' => ['required', 'string', 'max:200'],
            'guest_phone' => ['nullable', 'string', 'max:20'],
            'guest_email' => ['nullable', 'email', 'max:200'],

            'source_type' => ['nullable', Rule::in([
                'direct', 'walk_in', 'phone', 'email', 'website',
                'ota', 'corporate', 'travel_agent', 'gds', 'group',
            ])],
            'source_name' => ['nullable', 'string', 'max:100'],
            'ota_booking_id' => ['nullable', 'string', 'max:100'],
            'ota_channel_code' => ['nullable', 'string', 'max:50'],
            'market_segment' => ['nullable', 'string', 'max:100'],
            'business_source' => ['nullable', 'string', 'max:100'],

            'arrival_date' => ['required', 'date', 'after_or_equal:today'],
            'departure_date' => ['required', 'date', 'after:arrival_date'],
            'arrival_time' => ['nullable', 'date_format:H:i'],
            'departure_time' => ['nullable', 'date_format:H:i'],

            'adults' => ['required', 'integer', 'min:1', 'max:10'],
            'children' => ['nullable', 'integer', 'min:0', 'max:10'],
            'infants' => ['nullable', 'integer', 'min:0', 'max:10'],

            // Single room-type path
            'room_type_id' => ['required_without:room_allocations', 'integer', 'exists:room_types,id'],
            'rate_plan_id' => ['nullable', 'integer', 'exists:rate_plans,id'],
            'rooms_count' => ['nullable', 'integer', 'min:1', 'max:50'],
            'rate' => ['nullable', 'numeric', 'min:0'],

            // Multi room-type path
            'room_allocations' => ['nullable', 'array', 'min:1'],
            'room_allocations.*.room_type_id' => ['required', 'integer', 'exists:room_types,id'],
            'room_allocations.*.rate_plan_id' => ['nullable', 'integer', 'exists:rate_plans,id'],
            'room_allocations.*.rooms' => ['required', 'integer', 'min:1', 'max:50'],
            'room_allocations.*.rate' => ['nullable', 'numeric', 'min:0'],

            'advance_amount' => ['nullable', 'numeric', 'min:0'],
            'special_requests' => ['nullable', 'string', 'max:1000'],
            'internal_notes' => ['nullable', 'string', 'max:1000'],
            'is_vip' => ['nullable', 'boolean'],
            'is_complimentary' => ['nullable', 'boolean'],
            'is_house_use' => ['nullable', 'boolean'],
            'billing_to' => ['nullable', Rule::in(['guest', 'company', 'split'])],
            'billing_instructions' => ['nullable', 'string', 'max:500'],

            'status' => ['nullable', Rule::in(['tentative', 'confirmed', 'waitlist'])],

            // Offline sync
            'device_id' => ['nullable', 'string', 'max:100'],
        ];
    }
}
