<?php

namespace App\Livewire\HotelSite;

use App\Models\Property;
use App\Models\Tenant;
use App\Services\TenantContext;
use Livewire\Component;

/**
 * Base class for every public hotel-site page. Resolves the tenant from the
 * route param (subdomain in production, /h/{slug}/ on localhost), pins it
 * into TenantContext under bypass so global tenant scopes don't fight us,
 * and exposes $tenant + $property to subclasses.
 */
abstract class HotelSiteBase extends Component
{
    /** @var \App\Models\Tenant */
    public $tenant;

    /** @var \App\Models\Property|null */
    public $property;

    public string $tenant_slug = '';

    public function mount(string $tenant_slug, ?string $roomTypeCode = null): void
    {
        $this->tenant_slug = $tenant_slug;

        $this->tenant = app(TenantContext::class)->bypass(
            fn () => Tenant::where('slug', $tenant_slug)->first()
        );
        if (! $this->tenant) {
            abort(404, "We couldn't find a hotel at this address.");
        }

        // Pin the tenant for the rest of the request so any tenant-scoped
        // models we render (rooms, room types, etc.) work normally.
        $ctx = app(TenantContext::class);
        $ctx->set($this->tenant);

        // Pick the primary property — the first active one
        $this->property = $ctx->bypass(
            fn () => Property::where('tenant_id', $this->tenant->id)
                ->where('status', 'active')
                ->orderBy('id')
                ->first()
                ?? Property::where('tenant_id', $this->tenant->id)
                ->orderBy('id')
                ->first()
        );
        if ($this->property) {
            $ctx->setProperty($this->property);
        }

        // Share with the layout — Livewire public properties don't reach the
        // wrapping `#[Layout('...')]` template otherwise.
        view()->share([
            'tenant'      => $this->tenant,
            'property'    => $this->property,
            'tenant_slug' => $this->tenant_slug,
        ]);

        // Hand off to subclass (with the optional roomTypeCode for /rooms/{code})
        if (method_exists($this, 'mounted')) {
            $this->mounted($roomTypeCode);
        }
    }
}
