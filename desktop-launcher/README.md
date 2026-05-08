# Hotelesy Desktop launcher (Path B — Chrome `--app` shell)

A pragmatic alternative to NativePHP that ships the offline desktop edition
**today** with zero composer changes and zero PHP version upgrades. Visually
indistinguishable from a native window for the end-user.

## What it does

The launcher boots a private `php artisan serve` process bound to localhost,
waits for it to come up, then opens Google Chrome in `--app` mode pointing at
`/desktop`. Chrome's `--app` mode strips the URL bar, tabs, and bookmarks —
the user sees a clean window titled "Hotelesy". When they close it, the PHP
server is shut down. A separate Chrome profile is used so the hotelier's
regular browsing isn't affected.

## Files

| File | Platform | Purpose |
|---|---|---|
| `build-mac-app.sh` | macOS | Builds `dist/Hotelesy.app` — a real .app bundle to drop into Applications |
| `Hotelesy.bat` | Windows | Double-click launcher — pin to Start menu / desktop |
| `Hotelesy.icns` | both | (optional) Add your icon here before building the .app |

## macOS — build & install

```bash
cd /path/to/hotelesy
bash desktop-launcher/build-mac-app.sh
open dist/                      # finds Hotelesy.app
# Drag Hotelesy.app into /Applications
```

The bundle works on any Mac with PHP 8.2+ and Chrome / Edge / Brave installed.
Logs go to `~/Library/Logs/Hotelesy.log`.

### Troubleshooting macOS

If macOS says "Hotelesy.app is damaged and can't be opened" (Gatekeeper):

```bash
xattr -dr com.apple.quarantine /Applications/Hotelesy.app
```

For real distribution to customers, replace the ad-hoc `codesign` line in
`build-mac-app.sh` with a Developer ID Application certificate and notarize
with `xcrun notarytool`.

## Windows — install

1. Copy the entire Hotelesy project folder somewhere stable, e.g.
   `C:\Hotelesy`.
2. Right-click `desktop-launcher\Hotelesy.bat` → **Send to → Desktop (create
   shortcut)**.
3. Right-click the shortcut → **Properties** → set the icon (any `.ico`).
4. Optionally pin to Start menu.

The user double-clicks the shortcut — no terminal visible, the app window
opens directly.

## Architecture

```
   ┌─────────────────────────────────┐
   │  Hotelesy.app (Mac)             │
   │  Hotelesy.bat (Win)             │
   └──────────────┬──────────────────┘
                  │ launches
                  ▼
   ┌─────────────────────────────────┐
   │  php artisan serve              │
   │  APP_ENV=desktop                │
   │  127.0.0.1: port 8001…8005      │
   └──────────────┬──────────────────┘
                  │ navigates
                  ▼
   ┌─────────────────────────────────┐
   │  Chrome --app mode              │
   │  Isolated profile               │
   │  http://127.0.0.1:.../desktop   │
   └─────────────────────────────────┘
```

The same Laravel stack runs in both editions. `APP_MODE=desktop` flips on the
desktop service provider, which loads license enforcement, the activation
screens, and the first-run setup wizard.

## Why this instead of NativePHP

| | Path A (NativePHP) | Path B (Chrome --app) |
|---|---|---|
| PHP version required | 8.3+ | 8.2+ (matches your project) |
| Composer changes | Adds 4 packages, refactors lock | Zero |
| Retest required | Full smoke test | None |
| End-user experience | Native Cocoa window | Chrome `--app` window (visually identical) |
| Codesigned installer | `.dmg` / `.exe` from `native:build` | Drag-to-Applications `.app` bundle |
| Shipping today | Blocked on PHP upgrade | Ready now |

When you have a quieter week to do the PHP 8.3 upgrade properly, switch to
NativePHP for a more polished installer. Until then, Path B works and ships.
