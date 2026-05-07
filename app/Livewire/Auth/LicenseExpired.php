<?php

namespace App\Livewire\Auth;

use App\Models\License;
use App\Services\TenantContext;
use Livewire\Attributes\Layout;
use Livewire\Component;

class LicenseExpired extends Component
{
    #[Layout('layouts.guest')]
    public function render()
    {
        $user = auth()->user();

        $license = null;
        if ($user && $user->tenant_id) {
            $license = app(TenantContext::class)->bypass(
                fn () => License::with('plan', 'tenant')
                    ->where('tenant_id', $user->tenant_id)
                    ->first()
            );
        }

        return view('livewire.auth.license-expired', [
            'license' => $license,
            'tenantName' => $user?->tenant?->name,
        ]);
    }
}
