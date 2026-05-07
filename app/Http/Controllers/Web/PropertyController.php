<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Property;
use App\Services\TenantContext;
use Illuminate\Http\Request;

class PropertyController extends Controller
{
    public function select(Request $request, TenantContext $context)
    {
        $properties = $request->user()->properties()->where('properties.status', 'active')->get();

        if ($properties->isEmpty() && $context->tenant()) {
            // No explicit pivot — fall back to all active properties under the user's tenant
            $properties = $context->tenant()->properties()->where('status', 'active')->get();
        }

        if ($properties->count() === 1) {
            $request->user()->update(['default_property_id' => $properties->first()->id]);
            return redirect()->route('dashboard');
        }

        return view('property.select', ['properties' => $properties]);
    }

    public function switch(Request $request)
    {
        $data = $request->validate(['property_id' => 'required|exists:properties,id']);
        $property = Property::findOrFail($data['property_id']);

        // Authorization: user must have access
        $user = $request->user();
        if (!$user->is_super_admin
            && $property->tenant_id !== $user->tenant_id
            && !$user->properties->contains('id', $property->id)) {
            abort(403, 'You do not have access to this property.');
        }

        $user->update(['default_property_id' => $property->id]);
        return redirect()->route('dashboard');
    }
}
