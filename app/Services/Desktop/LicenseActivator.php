<?php

namespace App\Services\Desktop;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * LicenseActivator — manages the desktop edition's license lifecycle.
 *
 *   - activate(string $key)         → contact license server, verify key,
 *                                       bind to this hardware, write local file
 *   - status(): array               → 'unactivated' | 'active' | 'soft_warn' | 'readonly' | 'invalid'
 *   - phoneHome()                   → re-validate when online (called from a daily cron)
 *   - deactivate()                  → release license (so user can re-activate elsewhere)
 *
 * Local state is stored as encrypted JSON at storage/app/desktop/license.json
 * The cloud-issued payload contains:
 *   { activation_key, fingerprint, plan, valid_until, last_validated_at,
 *     issued_to_email, issued_at, signature, server_response }
 */
class LicenseActivator
{
    public const STATUS_UNACTIVATED = 'unactivated';
    public const STATUS_ACTIVE      = 'active';
    public const STATUS_SOFT_WARN   = 'soft_warn';     // online check overdue (>30d)
    public const STATUS_READONLY    = 'readonly';      // online check very overdue (>60d)
    public const STATUS_INVALID     = 'invalid';       // key revoked / fingerprint mismatch

    /**
     * Try to activate using a 25-character activation key (e.g. ABCDE-FGHIJ-KLMNO-PQRST-UVWXY).
     */
    public function activate(string $rawKey): array
    {
        $key = $this->normaliseKey($rawKey);
        if (! $this->keyLooksValid($key)) {
            throw new RuntimeException('Invalid activation key format. Expected 5 groups of 5 characters.');
        }

        $fingerprint = HardwareFingerprint::generate();
        $payload = [
            'activation_key' => $key,
            'fingerprint'    => $fingerprint,
            'host'           => gethostname() ?: 'unknown',
            'os'             => PHP_OS_FAMILY,
            'app_version'    => config('desktop.version', '1.0.0'),
            'requested_at'   => now()->toIso8601String(),
        ];

        // ---- STUB MODE ----
        // When DESKTOP_LICENSE_SERVER is unset / empty / "stub", we skip the
        // HTTP call entirely and accept any well-formed 25-char key. Useful
        // for local dev and for shipping standalone installers that don't
        // need to phone home (e.g. perpetual-license desktop builds for
        // offline customers).
        $serverUrl = trim((string) config('desktop.license_server_url', ''));
        if ($serverUrl === '' || strtolower($serverUrl) === 'stub') {
            $stored = [
                'activation_key'    => $key,
                'fingerprint'       => $fingerprint,
                'plan'              => 'desktop-stub',
                'valid_until'       => now()->addYears(99)->toIso8601String(),
                'last_validated_at' => now()->toIso8601String(),
                'issued_to_email'   => 'local@stub',
                'issued_at'         => now()->toIso8601String(),
                'signature'         => 'stub',
                'features'          => ['desktop' => true, 'pos' => true, 'banquet' => true],
                'server_response'   => ['ok' => true, 'mode' => 'stub'],
            ];
            $this->writeLocalLicense($stored);
            return $stored;
        }

        $url = rtrim($serverUrl, '/') . '/activate';
        try {
            $resp = Http::timeout(15)->retry(2, 250)->acceptJson()->post($url, $payload);
        } catch (\Throwable $e) {
            throw new RuntimeException('Cannot reach the license server. Check your internet connection and try again. (' . $e->getMessage() . ')');
        }

        if (! $resp->successful()) {
            $msg = $resp->json('message') ?: $resp->body();
            throw new RuntimeException('Activation failed: ' . $msg);
        }

        $body = $resp->json();
        if (! ($body['ok'] ?? false)) {
            throw new RuntimeException($body['message'] ?? 'License server rejected the activation.');
        }

        $stored = [
            'activation_key'    => $key,
            'fingerprint'       => $fingerprint,
            'plan'              => $body['plan']         ?? 'standard',
            'valid_until'       => $body['valid_until']  ?? null,   // null = perpetual
            'last_validated_at' => now()->toIso8601String(),
            'issued_to_email'   => $body['issued_to']    ?? null,
            'issued_at'         => $body['issued_at']    ?? now()->toIso8601String(),
            'signature'         => $body['signature']    ?? null,
            'features'          => $body['features']     ?? [],
            'server_response'   => $body,
        ];

        $this->writeLocalLicense($stored);
        return $stored;
    }

    /**
     * Return the current license status without contacting the server.
     */
    public function status(): array
    {
        $local = $this->readLocalLicense();
        if (! $local) {
            return ['status' => self::STATUS_UNACTIVATED, 'license' => null];
        }

        // Hardware fingerprint must still match the activation
        if (($local['fingerprint'] ?? '') !== HardwareFingerprint::generate()) {
            return ['status' => self::STATUS_INVALID, 'license' => $local, 'reason' => 'Hardware fingerprint mismatch — license is bound to a different machine.'];
        }

        // Hard expiry (for time-limited plans)
        if (! empty($local['valid_until']) && Carbon::parse($local['valid_until'])->isPast()) {
            return ['status' => self::STATUS_INVALID, 'license' => $local, 'reason' => 'License period expired.'];
        }

        // Online-validation freshness
        $lastValidated = ! empty($local['last_validated_at'])
            ? Carbon::parse($local['last_validated_at'])
            : Carbon::parse($local['issued_at'] ?? now());
        $daysOffline = (int) $lastValidated->diffInDays(now());

        $softAfter   = (int) config('desktop.soft_warn_after_days', 30);
        $readOnlyAfter = (int) config('desktop.lock_readonly_after_days', 60);

        if ($daysOffline >= $readOnlyAfter) {
            return ['status' => self::STATUS_READONLY, 'license' => $local, 'days_offline' => $daysOffline];
        }
        if ($daysOffline >= $softAfter) {
            return ['status' => self::STATUS_SOFT_WARN, 'license' => $local, 'days_offline' => $daysOffline];
        }
        return ['status' => self::STATUS_ACTIVE, 'license' => $local, 'days_offline' => $daysOffline];
    }

    /**
     * Periodic phone-home — refreshes last_validated_at when online.
     * Failure is silent (we keep the existing license; offline grace handles this).
     */
    public function phoneHome(): bool
    {
        $local = $this->readLocalLicense();
        if (! $local) return false;

        // Stub-mode license — refresh `last_validated_at` locally and bail.
        $serverUrl = trim((string) config('desktop.license_server_url', ''));
        if ($serverUrl === '' || strtolower($serverUrl) === 'stub' || ($local['signature'] ?? null) === 'stub') {
            $local['last_validated_at'] = now()->toIso8601String();
            $this->writeLocalLicense($local);
            return true;
        }

        $url = rtrim($serverUrl, '/') . '/validate';
        try {
            $resp = Http::timeout(8)->acceptJson()->post($url, [
                'activation_key' => $local['activation_key'] ?? '',
                'fingerprint'    => $local['fingerprint'] ?? '',
                'app_version'    => config('desktop.version', '1.0.0'),
            ]);
            if (! $resp->successful()) return false;
            $body = $resp->json();
            if (! ($body['ok'] ?? false)) {
                // License revoked server-side — mark local as invalid
                $local['revoked_at'] = now()->toIso8601String();
                $local['revoked_reason'] = $body['message'] ?? 'Revoked by license server';
                $this->writeLocalLicense($local);
                return false;
            }
            $local['last_validated_at'] = now()->toIso8601String();
            // Server may push updated entitlements
            if (! empty($body['valid_until'])) $local['valid_until'] = $body['valid_until'];
            if (! empty($body['features']))    $local['features']    = $body['features'];
            $this->writeLocalLicense($local);
            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }

    public function deactivate(): bool
    {
        $local = $this->readLocalLicense();
        if (! $local) return true;

        $url = rtrim((string) config('desktop.license_server_url', ''), '/') . '/deactivate';
        try {
            Http::timeout(8)->post($url, [
                'activation_key' => $local['activation_key'] ?? '',
                'fingerprint'    => $local['fingerprint'] ?? '',
            ]);
        } catch (\Throwable $e) {
            // Best effort — wipe the local file regardless
        }

        $file = (string) config('desktop.license_file');
        if (File::exists($file)) File::delete($file);
        return true;
    }

    // ---------- internals ----------

    private function readLocalLicense(): ?array
    {
        $file = (string) config('desktop.license_file');
        if (! File::exists($file)) return null;
        try {
            $encrypted = File::get($file);
            $json = Crypt::decryptString($encrypted);
            $data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
            return is_array($data) ? $data : null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function writeLocalLicense(array $data): void
    {
        $file = (string) config('desktop.license_file');
        $dir  = dirname($file);
        if (! File::isDirectory($dir)) File::makeDirectory($dir, 0700, true);
        File::put($file, Crypt::encryptString(json_encode($data, JSON_UNESCAPED_SLASHES)));
        @chmod($file, 0600);
    }

    private function normaliseKey(string $key): string
    {
        // Strip whitespace, uppercase, then re-insert dashes between every
        // 4-char group. Lets users paste with weird spacing or missing dashes.
        $clean = strtoupper(preg_replace('/[^A-Z0-9]/i', '', trim($key)));
        if (strlen($clean) === 20) {
            return implode('-', str_split($clean, 4));
        }
        return strtoupper(preg_replace('/[^A-Z0-9]/i', '-', trim($key)));
    }

    private function keyLooksValid(string $key): bool
    {
        // Cloud format: HTLY-XXXX-XXXX-XXXX-XXXX (5 groups of 4 = 24 chars).
        // Accept any 5-group-of-4 to allow test keys without the HTLY prefix.
        return (bool) preg_match('/^[A-Z0-9]{4}(-[A-Z0-9]{4}){4}$/', $key);
    }
}
