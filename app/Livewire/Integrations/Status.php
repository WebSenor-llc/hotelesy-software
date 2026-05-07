<?php

namespace App\Livewire\Integrations;

use App\Services\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app-shell')]
class Status extends Component
{
    /** Currently selected integration key for the configure form. */
    public string $configureKey = '';

    /** Form fields (raw associative array — bound dynamically per integration). */
    public array $configFields = [];

    /** Whether the integration is being marked active. */
    public bool $configIsActive = true;

    /**
     * Catalog of integrations + the field schema they expose.
     * Each field: ['key' => 'api_key', 'label' => 'API key', 'type' => 'text|password|url']
     */
    public function catalog(): array
    {
        return [
            // Payment gateways
            'razorpay' => ['name' => 'Razorpay', 'group' => 'Payment gateway', 'desc' => 'Card / UPI / NetBanking — India default', 'fields' => [
                ['key' => 'key_id', 'label' => 'Key ID', 'type' => 'text'],
                ['key' => 'key_secret', 'label' => 'Key secret', 'type' => 'password'],
                ['key' => 'webhook_secret', 'label' => 'Webhook secret', 'type' => 'password'],
            ]],
            'stripe' => ['name' => 'Stripe', 'group' => 'Payment gateway', 'desc' => 'Cards, international currencies', 'fields' => [
                ['key' => 'public_key', 'label' => 'Publishable key', 'type' => 'text'],
                ['key' => 'secret_key', 'label' => 'Secret key', 'type' => 'password'],
                ['key' => 'webhook_secret', 'label' => 'Webhook secret', 'type' => 'password'],
            ]],
            'payu' => ['name' => 'PayU', 'group' => 'Payment gateway', 'desc' => 'India alternative', 'fields' => [
                ['key' => 'merchant_key', 'label' => 'Merchant key', 'type' => 'text'],
                ['key' => 'salt', 'label' => 'Salt', 'type' => 'password'],
            ]],
            'ccavenue' => ['name' => 'CCAvenue', 'group' => 'Payment gateway', 'desc' => 'India alternative', 'fields' => [
                ['key' => 'merchant_id', 'label' => 'Merchant ID', 'type' => 'text'],
                ['key' => 'access_code', 'label' => 'Access code', 'type' => 'text'],
                ['key' => 'working_key', 'label' => 'Working key', 'type' => 'password'],
            ]],
            // Channel managers
            'axisrooms' => ['name' => 'AxisRooms', 'group' => 'Channel manager', 'desc' => 'OTA inventory + rate sync', 'fields' => [
                ['key' => 'api_key', 'label' => 'API key', 'type' => 'password'],
                ['key' => 'hotel_id', 'label' => 'Hotel ID', 'type' => 'text'],
                ['key' => 'endpoint', 'label' => 'Endpoint URL', 'type' => 'url'],
            ]],
            'staah' => ['name' => 'STAAH', 'group' => 'Channel manager', 'desc' => 'Alternative driver', 'fields' => [
                ['key' => 'api_key', 'label' => 'API key', 'type' => 'password'],
                ['key' => 'property_code', 'label' => 'Property code', 'type' => 'text'],
            ]],
            'siteminder' => ['name' => 'SiteMinder', 'group' => 'Channel manager', 'desc' => 'Alternative driver', 'fields' => [
                ['key' => 'username', 'label' => 'Username', 'type' => 'text'],
                ['key' => 'password', 'label' => 'Password', 'type' => 'password'],
                ['key' => 'hotel_code', 'label' => 'Hotel code', 'type' => 'text'],
            ]],
            'rategain' => ['name' => 'RateGain', 'group' => 'Channel manager', 'desc' => 'Alternative driver', 'fields' => [
                ['key' => 'api_key', 'label' => 'API key', 'type' => 'password'],
                ['key' => 'hotel_code', 'label' => 'Hotel code', 'type' => 'text'],
            ]],
            // Door locks
            'onity' => ['name' => 'Onity', 'group' => 'Door locks', 'desc' => 'Key-card programming', 'fields' => [
                ['key' => 'api_key', 'label' => 'API key', 'type' => 'password'],
                ['key' => 'site_id', 'label' => 'Site ID', 'type' => 'text'],
            ]],
            'saflok' => ['name' => 'Saflok', 'group' => 'Door locks', 'desc' => 'Key-card programming', 'fields' => [
                ['key' => 'api_key', 'label' => 'API key', 'type' => 'password'],
                ['key' => 'site_id', 'label' => 'Site ID', 'type' => 'text'],
            ]],
            'dormakaba' => ['name' => 'dormakaba', 'group' => 'Door locks', 'desc' => 'Key-card programming', 'fields' => [
                ['key' => 'api_key', 'label' => 'API key', 'type' => 'password'],
            ]],
            'salto' => ['name' => 'Salto', 'group' => 'Door locks', 'desc' => 'Key-card programming', 'fields' => [
                ['key' => 'api_key', 'label' => 'API key', 'type' => 'password'],
            ]],
            'vingcard' => ['name' => 'VingCard', 'group' => 'Door locks', 'desc' => 'Key-card programming', 'fields' => [
                ['key' => 'api_key', 'label' => 'API key', 'type' => 'password'],
            ]],
            // ID scanner
            'hyperverge' => ['name' => 'Hyperverge', 'group' => 'ID scanner / OCR', 'desc' => 'Aadhaar / passport OCR', 'fields' => [
                ['key' => 'app_id', 'label' => 'App ID', 'type' => 'text'],
                ['key' => 'app_key', 'label' => 'App key', 'type' => 'password'],
            ]],
            'jumio' => ['name' => 'Jumio', 'group' => 'ID scanner / OCR', 'desc' => 'International ID verification', 'fields' => [
                ['key' => 'api_token', 'label' => 'API token', 'type' => 'password'],
                ['key' => 'api_secret', 'label' => 'API secret', 'type' => 'password'],
            ]],
            'onfido' => ['name' => 'Onfido', 'group' => 'ID scanner / OCR', 'desc' => 'International ID verification', 'fields' => [
                ['key' => 'api_token', 'label' => 'API token', 'type' => 'password'],
            ]],
            // Messaging
            'aisensy' => ['name' => 'AiSensy (WhatsApp)', 'group' => 'Messaging', 'desc' => 'Booking confirmations + service requests', 'fields' => [
                ['key' => 'api_key', 'label' => 'API key', 'type' => 'password'],
                ['key' => 'sender', 'label' => 'Sender / WABA number', 'type' => 'text'],
            ]],
            'gallabox' => ['name' => 'Gallabox', 'group' => 'Messaging', 'desc' => 'Alternative WhatsApp BSP', 'fields' => [
                ['key' => 'api_key', 'label' => 'API key', 'type' => 'password'],
                ['key' => 'channel_id', 'label' => 'Channel ID', 'type' => 'text'],
            ]],
            'msg91' => ['name' => 'MSG91 (SMS)', 'group' => 'Messaging', 'desc' => 'OTP, transactional SMS', 'fields' => [
                ['key' => 'auth_key', 'label' => 'Auth key', 'type' => 'password'],
                ['key' => 'sender_id', 'label' => 'Sender ID', 'type' => 'text'],
                ['key' => 'route', 'label' => 'Route', 'type' => 'text'],
            ]],
            // Accounting
            'tally' => ['name' => 'Tally', 'group' => 'Accounting export', 'desc' => 'GL export to Tally Prime', 'fields' => [
                ['key' => 'export_path', 'label' => 'Export path', 'type' => 'text'],
                ['key' => 'company_name', 'label' => 'Tally company name', 'type' => 'text'],
            ]],
            'zoho_books' => ['name' => 'Zoho Books', 'group' => 'Accounting export', 'desc' => 'Alternative GL export', 'fields' => [
                ['key' => 'client_id', 'label' => 'Client ID', 'type' => 'text'],
                ['key' => 'client_secret', 'label' => 'Client secret', 'type' => 'password'],
                ['key' => 'organization_id', 'label' => 'Organization ID', 'type' => 'text'],
            ]],
            // Distribution
            'gds' => ['name' => 'GDS (Sabre/Galileo)', 'group' => 'Distribution', 'desc' => 'Travel-agent distribution', 'fields' => [
                ['key' => 'api_key', 'label' => 'API key', 'type' => 'password'],
                ['key' => 'pseudo_city_code', 'label' => 'Pseudo city code', 'type' => 'text'],
            ]],
            'booking_engine' => ['name' => 'Booking engine widget', 'group' => 'Distribution', 'desc' => 'Direct booking from website', 'fields' => [
                ['key' => 'domain', 'label' => 'Domain', 'type' => 'url'],
                ['key' => 'widget_token', 'label' => 'Widget token', 'type' => 'password'],
            ]],
            // Self-service
            'kiosk' => ['name' => 'Kiosk', 'group' => 'Self-service', 'desc' => 'Lobby self-check-in kiosk', 'fields' => [
                ['key' => 'api_key', 'label' => 'API key', 'type' => 'password'],
                ['key' => 'device_id', 'label' => 'Device ID', 'type' => 'text'],
            ]],
            // Reviews
            'google_reviews' => ['name' => 'Google Reviews', 'group' => 'Reviews', 'desc' => 'Pull reviews via Places API', 'fields' => [
                ['key' => 'place_id', 'label' => 'Google Place ID', 'type' => 'text'],
                ['key' => 'api_key', 'label' => 'Places API key', 'type' => 'password'],
            ]],
            'tripadvisor' => ['name' => 'TripAdvisor', 'group' => 'Reviews', 'desc' => 'Pull reviews via API', 'fields' => [
                ['key' => 'api_key', 'label' => 'API key', 'type' => 'password'],
                ['key' => 'location_id', 'label' => 'Location ID', 'type' => 'text'],
            ]],
            'bookingcom_reviews' => ['name' => 'Booking.com Reviews', 'group' => 'Reviews', 'desc' => 'Pull reviews via API', 'fields' => [
                ['key' => 'api_key', 'label' => 'API key', 'type' => 'password'],
                ['key' => 'hotel_id', 'label' => 'Hotel ID', 'type' => 'text'],
            ]],
        ];
    }

    public function startConfigure(string $key): void
    {
        $catalog = $this->catalog();
        if (!isset($catalog[$key])) return;

        $this->configureKey = $key;
        $this->configIsActive = true;
        $this->configFields = [];

        // Pre-fill from existing row
        if (Schema::hasTable('integration_settings')) {
            $ctx = app(TenantContext::class);
            $row = DB::table('integration_settings')
                ->where('property_id', $ctx->propertyId())
                ->where('key', $key)
                ->first();
            if ($row) {
                $this->configFields = is_string($row->config_json) ? (json_decode($row->config_json, true) ?: []) : (array)($row->config_json ?? []);
                $this->configIsActive = (bool) $row->is_active;
            }
        }

        // Initialise empty values for missing fields
        foreach ($catalog[$key]['fields'] as $f) {
            if (!array_key_exists($f['key'], $this->configFields)) {
                $this->configFields[$f['key']] = '';
            }
        }
    }

    public function cancelConfigure(): void
    {
        $this->configureKey = '';
        $this->configFields = [];
    }

    public function saveConfigure(): void
    {
        if ($this->configureKey === '') return;

        if (!Schema::hasTable('integration_settings')) {
            session()->flash('error', 'integration_settings table not present — please run migrations.');
            return;
        }

        $ctx = app(TenantContext::class);
        $now = now();

        DB::table('integration_settings')->updateOrInsert(
            ['property_id' => $ctx->propertyId(), 'key' => $this->configureKey],
            [
                'tenant_id' => $ctx->tenantId(),
                'config_json' => json_encode($this->configFields),
                'is_active' => $this->configIsActive,
                'updated_at' => $now,
                'created_at' => $now,
            ]
        );

        session()->flash('success', 'Integration configured.');
        $this->configureKey = '';
        $this->configFields = [];
    }

    public function testConnection(string $key): void
    {
        $catalog = $this->catalog();
        $name = $catalog[$key]['name'] ?? $key;
        $ctx = app(TenantContext::class);

        if (!Schema::hasTable('integration_settings')) {
            session()->flash('error', 'integration_settings table not present — please run migrations.');
            return;
        }

        $row = DB::table('integration_settings')
            ->where('property_id', $ctx->propertyId())
            ->where('key', $key)
            ->first();

        if (!$row) {
            session()->flash('error', "No credentials saved for {$name}. Configure first.");
            $this->logIntegrationTest($ctx, $key, false, "No credentials saved for {$name}.");
            return;
        }

        $config = is_string($row->config_json) ? (json_decode($row->config_json, true) ?: []) : (array) ($row->config_json ?? []);

        $error = $this->validateIntegrationConfig($key, $config);

        if ($error !== null) {
            $msg = "✗ {$name}: {$error}";
            session()->flash('error', $msg);
            $this->logIntegrationTest($ctx, $key, false, $msg);
            return;
        }

        $msg = "✓ {$name} credentials look valid. (Live API call not run — would need outbound network in dev.)";
        session()->flash('success', $msg);
        $this->logIntegrationTest($ctx, $key, true, $msg);
    }

    /**
     * Per-integration shape check on the saved config.
     * Returns null when valid, or a human reason when invalid.
     */
    private function validateIntegrationConfig(string $key, array $config): ?string
    {
        $val = fn (string $k) => trim((string) ($config[$k] ?? ''));

        switch ($key) {
            case 'razorpay':
                if ($val('key_id') === '') return 'missing key_id';
                if ($val('key_secret') === '') return 'missing key_secret';
                if (strlen($val('key_id')) < 30) return 'key_id looks too short (expected 30+ chars)';
                if (strlen($val('key_secret')) < 30) return 'key_secret looks too short (expected 30+ chars)';
                return null;

            case 'axisrooms':
                if ($val('username') === '' && $val('api_key') === '') return 'missing username (or api_key)';
                if ($val('username') !== '' && $val('password') === '') return 'missing password';
                return null;

            case 'staah':
                if ($val('api_key') === '') return 'missing api_key';
                return null;

            case 'msg91':
                if ($val('auth_key') === '') return 'missing auth_key';
                return null;

            case 'aisensy':
                if ($val('api_key') === '') return 'missing api_key';
                return null;

            case 'tally':
                $host = $val('host') !== '' ? $val('host') : $val('export_path');
                if ($host === '') return 'missing host / export_path';
                if (filter_var($host, FILTER_VALIDATE_URL) === false && !preg_match('#^[\w./:\\\\-]+$#', $host)) {
                    return 'host is not a parseable URL or path';
                }
                return null;

            default:
                $hasAny = false;
                foreach ($config as $v) {
                    if (is_string($v) && trim($v) !== '') { $hasAny = true; break; }
                    if (!is_string($v) && !empty($v)) { $hasAny = true; break; }
                }
                return $hasAny ? null : 'no fields filled in';
        }
    }

    private function logIntegrationTest(TenantContext $ctx, string $key, bool $success, string $message): void
    {
        if (!Schema::hasTable('integration_logs')) {
            return;
        }

        try {
            DB::table('integration_logs')->insert([
                'tenant_id'      => $ctx->tenantId(),
                'property_id'    => $ctx->propertyId(),
                'integration'    => $key,
                'operation'      => 'test_connection',
                'reference_type' => null,
                'reference_id'   => null,
                'response_payload' => json_encode(['message' => $message]),
                'success'        => $success,
                'error_message'  => $success ? null : $message,
                'duration_ms'    => 0,
                'called_at'      => now(),
                'created_at'     => now(),
                'updated_at'     => now(),
            ]);
        } catch (\Throwable $e) {
            // best-effort audit; never block the UX on a logging failure
        }
    }

    public function render()
    {
        $ctx = app(TenantContext::class);
        $propertyId = $ctx->propertyId();
        $catalog = $this->catalog();

        // Read existing settings to know which are configured
        $configured = collect();
        if (Schema::hasTable('integration_settings')) {
            $configured = DB::table('integration_settings')
                ->where('property_id', $propertyId)
                ->where('is_active', true)
                ->pluck('key')
                ->flip();
        }

        // Build rows for view
        $rows = [];
        foreach ($catalog as $key => $entry) {
            $rows[] = [
                'key' => $key,
                'name' => $entry['name'],
                'group' => $entry['group'],
                'desc' => $entry['desc'],
                'configured' => $configured->has($key),
                'fields' => $entry['fields'],
            ];
        }
        $byGroup = collect($rows)->groupBy('group');

        $logs = Schema::hasTable('integration_logs')
            ? DB::table('integration_logs')->where('property_id', $propertyId)->orderByDesc('called_at')->limit(30)->get()
            : collect();

        // Active integration's catalog entry for the form
        $activeEntry = $this->configureKey !== '' ? ($catalog[$this->configureKey] ?? null) : null;

        return view('livewire.integrations.status', compact('byGroup','logs','activeEntry'));
    }
}
