<?php

namespace App\Http\Controllers\Api\Desktop;

use App\Http\Controllers\Controller;
use App\Models\License;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

/**
 * LicenseServerController — runs on the CLOUD instance and is consumed by
 * desktop installations. Handles the activation / re-validation / release
 * lifecycle of an offline desktop seat.
 *
 *  POST /api/desktop/activate
 *  POST /api/desktop/validate
 *  POST /api/desktop/deactivate
 *
 * The desktop app calls these endpoints. The cloud verifies the activation
 * key, records the machine fingerprint against a single seat (default), and
 * returns the JSON payload the desktop persists locally.
 *
 * IMPORTANT: this controller is NOT loaded in the desktop build — only on the
 * cloud Hotelesy that issues activation keys.
 */
class LicenseServerController extends Controller
{
    /**
     * Activate a license against this machine.
     *
     * Body:
     *   license_key   — 25-char dashed key (XXXXX-XXXXX-XXXXX-XXXXX-XXXXX)
     *   fingerprint   — SHA-256 hex string (~64 chars)
     *   hostname      — short host label
     *   os            — Darwin / Linux / Windows
     */
    public function activate(Request $request): JsonResponse
    {
        // Accept both `activation_key` (what the desktop client sends) and
        // `license_key` (legacy name) so the API is forgiving for either.
        $request->merge([
            'license_key' => $request->input('license_key', $request->input('activation_key')),
            'hostname'    => $request->input('hostname', $request->input('host')),
        ]);

        $data = $request->validate([
            'license_key' => 'required|string|min:19|max:32',     // accepts HTLY-XXXX-XXXX-XXXX-XXXX (24) and variants
            'fingerprint' => 'required|string|min:32|max:128',
            'hostname'    => 'nullable|string|max:120',
            'os'          => ['nullable', Rule::in(['Darwin', 'Linux', 'Windows', 'BSD', 'Solaris', 'Unknown'])],
        ]);

        $license = $this->lookupKey($data['license_key']);
        if (! $license) {
            return response()->json(['ok' => false, 'error' => 'Invalid activation key.'], 404);
        }

        if (! in_array($license->status, [License::STATUS_ACTIVE, License::STATUS_TRIAL], true)) {
            return response()->json([
                'ok'    => false,
                'error' => "License is {$license->status}. Renew or contact support.",
            ], 403);
        }

        if ($license->expires_at && $license->expires_at->isPast()) {
            return response()->json(['ok' => false, 'error' => 'License has expired.'], 403);
        }

        // First activation OR same machine re-activation OK. Different machine
        // is rejected unless a seat is free.
        if ($license->desktop_machine_fingerprint
            && $license->desktop_machine_fingerprint !== $data['fingerprint']) {
            return response()->json([
                'ok'    => false,
                'error' => 'This license is already activated on another machine. Deactivate it there first or contact support.',
            ], 409);
        }

        DB::transaction(function () use ($license, $data) {
            $license->update([
                'is_desktop'                  => true,
                'desktop_machine_fingerprint' => $data['fingerprint'],
                'desktop_machine_label'       => $data['hostname'] ?? null,
                'desktop_activated_at'        => $license->desktop_activated_at ?? now(),
                'desktop_last_seen_at'        => now(),
                'last_validated_at'           => now(),
            ]);
        });

        $payload = $this->payload($license->fresh());
        return response()->json(array_merge(
            ['ok' => true, 'license' => $payload],
            $payload   // duplicate at top level for the desktop client
        ));
    }

    /**
     * Phone-home re-validation. Desktop sends this every N days while online.
     */
    public function validateLicense(Request $request): JsonResponse
    {
        $request->merge([
            'license_key' => $request->input('license_key', $request->input('activation_key')),
        ]);

        $data = $request->validate([
            'license_key' => 'required|string|min:19|max:32',
            'fingerprint' => 'required|string|min:32|max:128',
        ]);

        $license = $this->lookupKey($data['license_key']);
        if (! $license) {
            return response()->json(['ok' => false, 'error' => 'License not found.'], 404);
        }

        if ($license->desktop_machine_fingerprint !== $data['fingerprint']) {
            return response()->json([
                'ok'    => false,
                'error' => 'Machine fingerprint mismatch.',
            ], 409);
        }

        if (! in_array($license->status, [License::STATUS_ACTIVE, License::STATUS_TRIAL], true)) {
            return response()->json([
                'ok'      => false,
                'revoked' => true,
                'error'   => "License status is {$license->status}.",
            ], 403);
        }

        if ($license->expires_at && $license->expires_at->isPast()) {
            return response()->json([
                'ok'      => false,
                'revoked' => true,
                'error'   => 'License has expired.',
            ], 403);
        }

        $license->update(['desktop_last_seen_at' => now(), 'last_validated_at' => now()]);

        $payload = $this->payload($license);
        return response()->json(array_merge(
            ['ok' => true, 'license' => $payload],
            $payload
        ));
    }

    /**
     * Release a desktop seat — frees it for activation on a different machine.
     */
    public function deactivate(Request $request): JsonResponse
    {
        $request->merge([
            'license_key' => $request->input('license_key', $request->input('activation_key')),
        ]);

        $data = $request->validate([
            'license_key' => 'required|string|min:19|max:32',
            'fingerprint' => 'required|string|min:32|max:128',
        ]);

        $license = $this->lookupKey($data['license_key']);
        if (! $license) {
            return response()->json(['ok' => false, 'error' => 'License not found.'], 404);
        }

        if ($license->desktop_machine_fingerprint !== $data['fingerprint']) {
            return response()->json([
                'ok'    => false,
                'error' => 'Fingerprint mismatch — cannot release a seat from a different machine.',
            ], 409);
        }

        $license->update([
            'desktop_machine_fingerprint' => null,
            'desktop_machine_label'       => null,
            'desktop_activated_at'        => null,
            'desktop_last_seen_at'        => null,
        ]);

        return response()->json(['ok' => true]);
    }

    /**
     * Look up a license by raw activation key. The DB stores the bcrypt hash
     * (column license_key_hash) for security, with the raw key surfaced only
     * on creation; if the column doesn't exist or is null, fall back to the
     * plaintext license_key column.
     */
    private function lookupKey(string $rawKey): ?License
    {
        $rawKey = strtoupper(trim($rawKey));

        // Direct plaintext match (most common — keys are issued and shown in
        // plaintext to the customer at purchase time).
        $direct = License::query()->where('license_key', $rawKey)->first();
        if ($direct) {
            return $direct;
        }

        // Fall back to bcrypt-hashed lookup if a hash column exists.
        if (\Illuminate\Support\Facades\Schema::hasColumn('licenses', 'license_key_hash')) {
            foreach (License::query()->whereNotNull('license_key_hash')->cursor() as $candidate) {
                if (Hash::check($rawKey, $candidate->license_key_hash)) {
                    return $candidate;
                }
            }
        }

        return null;
    }

    /**
     * Build the JSON payload the desktop persists locally. Keep this lean —
     * everything in here gets written to disk on the customer's machine.
     */
    private function payload(License $license): array
    {
        return [
            'license_key'      => $license->license_key,
            'tenant_id'        => $license->tenant_id,
            'plan'             => optional($license->plan)->code ?? 'desktop',
            'status'           => $license->status,
            'starts_at'        => optional($license->starts_at)->toIso8601String(),
            'valid_until'      => optional($license->expires_at)->toIso8601String(),
            'features'         => optional($license->plan)->features ?? ['desktop' => true],
            'issued_at'        => optional($license->issued_at)->toIso8601String(),
            'fingerprint'      => $license->desktop_machine_fingerprint,
            'machine_label'    => $license->desktop_machine_label,
            'activated_at'     => optional($license->desktop_activated_at)->toIso8601String(),
            'last_validated'   => optional($license->desktop_last_seen_at)->toIso8601String(),
        ];
    }
}
