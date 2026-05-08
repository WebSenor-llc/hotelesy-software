# Hotelesy Desktop — Build & Install Guide

Hotelesy ships in two editions from one codebase:

| Edition  | Hosting           | Tenancy        | License model                    |
|----------|-------------------|----------------|----------------------------------|
| Cloud    | hotelesy.com      | Multi-tenant   | Razorpay subscription            |
| Desktop  | Customer's Mac/PC | Single-tenant  | Activation key + machine bind    |

The cloud build is what you see on `hotelesy.com`. The desktop build wraps the
exact same Laravel app in [NativePHP](https://nativephp.com/) (Electron under the
hood) and bundles a private PHP runtime + SQLite database.

---

## How the dual build works

A single environment flag controls everything:

```env
APP_MODE=desktop      # or `cloud`
```

When `APP_MODE=desktop` the boot pipeline does this:

1. `bootstrap/providers.php` registers `App\Providers\DesktopServiceProvider`.
2. The provider:
   - Loads `routes/desktop.php` (activation, first-run wizard, license info).
   - Pushes `EnforceDesktopLicense` middleware onto the `web` group.
   - Registers the `desktop:phone-home` artisan command.
   - Force-binds the single tenant + property in `TenantContext` so
     `BelongsToTenant` magic continues to work.
   - Hooks NativePHP's `Window::open()` if `nativephp/electron` is installed.
3. `EnforceDesktopLicense` checks the encrypted local license file on every
   request, redirects unactivated users to `/desktop/activate`, locks the app
   read-only after 60 offline days, and silently re-validates against
   `DESKTOP_LICENSE_SERVER` on cadence.

Cloud mode loads none of the above — the SaaS build is unaffected.

---

## License lifecycle

### 1. Issuance (cloud-side)

When a customer pays for the desktop edition through the cloud SaaS, a
`License` row is created with:

- `is_desktop = true`
- `license_key` = 25-char dashed key like `ABCDE-FGHIJ-KLMNO-PQRST-UVWXY`
- `desktop_max_machines = 1` (single seat by default)

The customer gets the key by email.

### 2. Activation (desktop-side)

On first launch the user enters the key. Hotelesy:

1. Generates a SHA-256 fingerprint from `OS + hostname + machine UUID + MAC + APP_KEY`.
2. POSTs `{license_key, fingerprint, hostname, os}` to
   `${DESKTOP_LICENSE_SERVER}/activate` (i.e. `https://hotelesy.com/api/desktop/activate`).
3. The cloud verifies the key and binds it to the fingerprint, returning a
   payload with `valid_until`, `features`, `plan`, etc.
4. The desktop stores the payload encrypted (`Crypt::encryptString`) at
   `storage/app/desktop/license.json` with `0600` permissions.

### 3. Re-validation

`desktop:phone-home` runs daily at 03:00 (registered in `routes/console.php`).
If the local file's `last_validated_at` is older than `DESKTOP_PHONE_HOME_DAYS`
(default 7), it re-POSTs to `/api/desktop/validate`. On success it refreshes
`valid_until` and resets the offline clock.

If the server is unreachable, the offline grace period kicks in:

| Offline duration                        | Effect                              |
|-----------------------------------------|-------------------------------------|
| `< 30d`                                  | Silent; status `active`             |
| `≥ DESKTOP_SOFT_WARN_DAYS` (30d)         | Yellow banner asking to reconnect   |
| `≥ DESKTOP_LOCK_READONLY_DAYS` (60d)     | Red banner; POST/PUT/PATCH blocked  |

### 4. Deactivation

The user can release the seat from `/desktop/license/info → Deactivate this
machine`. The desktop POSTs to `/api/desktop/deactivate` — the cloud clears the
fingerprint, freeing the seat for activation on a different computer.

---

## Local development

You don't need NativePHP installed to develop the desktop pieces. Just point
your local Laravel at `APP_MODE=desktop`:

```bash
cp .env.desktop.example .env
php artisan key:generate
mkdir -p storage/app/desktop
touch storage/app/desktop/hotelesy.sqlite
php artisan migrate
php artisan serve
```

Visit `http://127.0.0.1:8000/desktop` — you'll be redirected to the activation
screen. To test the activation flow without a real cloud server, you can:

- Run a second copy of Hotelesy as `APP_MODE=cloud` on `hotelesy.test` and
  set `DESKTOP_LICENSE_SERVER=http://hotelesy.test/api/desktop` in the desktop
  copy's `.env`, **OR**
- Issue a test license via tinker on the cloud DB:

```php
\App\Models\License::create([
    'tenant_id'      => 1,
    'subscription_plan_id' => null,
    'license_key'    => 'TESTA-BCDEF-GHIJK-LMNOP-QRSTU',
    'status'         => 'active',
    'starts_at'      => now(),
    'expires_at'     => now()->addYear(),
    'billing_cycle'  => 'lifetime',
    'is_desktop'     => true,
    'issued_at'      => now(),
]);
```

---

## Building the installer (NativePHP)

Once you're ready to ship to customers:

### 1. Install NativePHP

```bash
composer require nativephp/electron
php artisan native:install
```

This adds the desktop wrapper config, registers the IPC bridge, and downloads
the Electron runtime. `DesktopServiceProvider::bootNativePhp()` will then
detect the `Native\Laravel\Facades\Window` class and open the main window
on launch.

### 2. Configure the build

Edit `nativephp.php`:

```php
return [
    'app_id'      => 'app.hotelesy.desktop',
    'name'        => 'Hotelesy',
    'version'     => '1.0.0',
    'auto_update' => true,
    'icon'        => resource_path('icons/hotelesy.icns'),    // mac
    'updater'     => [
        'channel' => 'stable',
        'url'     => 'https://hotelesy.com/desktop/updates',
    ],
];
```

### 3. Build platform installers

```bash
# macOS .dmg + .pkg
php artisan native:build mac

# Windows .exe
php artisan native:build win

# Linux .AppImage / .deb
php artisan native:build linux
```

The resulting installers ship with:

- Hotelesy app code (`app/`, `bootstrap/`, `config/`, `routes/`, `resources/`, `vendor/`)
- A bundled PHP 8.2 runtime
- A blank SQLite database that the first-run wizard populates
- An Electron-rendered Chromium window

### 4. Codesign + notarize (macOS)

```bash
codesign --deep --force --sign "Developer ID Application: WebSenor" \
    "Hotelesy.app"
xcrun notarytool submit Hotelesy.dmg \
    --apple-id "you@example.com" --password "app-specific-pwd" \
    --team-id "ABCDE12345" --wait
```

Without notarization, macOS Gatekeeper will refuse to launch the app on the
customer's machine. Windows builds need a signed `.exe` from a Sectigo or
DigiCert code-signing cert.

---

## File map

```
app/
├── Console/Commands/
│   └── DesktopPhoneHome.php           # daily cron
├── Http/
│   ├── Controllers/Api/Desktop/
│   │   └── LicenseServerController.php   # CLOUD-SIDE: /api/desktop/{activate,validate,deactivate}
│   └── Middleware/
│       └── EnforceDesktopLicense.php  # request-time gate (desktop only)
├── Livewire/Desktop/
│   ├── Activation.php                 # 25-char key entry screen
│   └── FirstRunSetup.php              # 3-step wizard
├── Providers/
│   └── DesktopServiceProvider.php     # the desktop boot hook
└── Services/Desktop/
    ├── HardwareFingerprint.php        # OS+hostname+UUID+MAC SHA-256
    └── LicenseActivator.php           # encrypted local file + phone-home

config/
└── desktop.php                        # tenant_id, license_server, cadence

database/migrations/
└── 2026_05_08_100001_add_desktop_fields_to_licenses.php

resources/views/
├── desktop/
│   └── license-info.blade.php
├── layouts/
│   └── desktop-shell.blade.php        # NativePHP-aware layout
└── livewire/desktop/
    ├── activation.blade.php
    └── first-run-setup.blade.php

routes/
└── desktop.php                        # /desktop/{activate,setup,license/*}

.env.desktop.example                   # build template
DESKTOP.md                             # this file
```

---

## Smoke test checklist

Before cutting an installer, verify on a clean machine:

- [ ] First launch redirects to `/desktop/activate`
- [ ] Invalid key shows a clear error
- [ ] Valid key activates and redirects to `/desktop/setup`
- [ ] Wizard creates tenant + property + owner user
- [ ] Owner is auto-logged-in and lands on `/dashboard`
- [ ] Logout → login still works (uses standard `LoginController`)
- [ ] Restart laptop → app reopens directly to dashboard (no re-activation)
- [ ] Disable internet for 31 days (or fudge the file) → soft-warn banner appears
- [ ] Disable internet for 61 days → read-only mode, edits blocked
- [ ] Reconnect → "Re-validate now" clears banner
- [ ] Deactivate → activation screen reappears, key can be reused on another machine
