#!/usr/bin/env bash
# =============================================================================
#  build-mac-app.sh  ·  Hotelesy Desktop launcher builder (macOS)
# =============================================================================
#  Produces a real macOS .app bundle that:
#    1. Boots a private PHP dev server on a free port using APP_MODE=desktop
#    2. Waits until the server is reachable
#    3. Opens Google Chrome in --app mode pointing at /desktop, in an
#       isolated profile so the user's regular Chrome is untouched
#    4. When the Chrome window closes, kills the PHP server
#
#  Run from the project root:
#      bash desktop-launcher/build-mac-app.sh
#
#  Output: ./dist/Hotelesy.app   — drag this into /Applications.
# =============================================================================
set -euo pipefail

PROJECT_ROOT="$(cd "$(dirname "$0")/.." && pwd)"
DIST="$PROJECT_ROOT/dist"
APP="$DIST/Hotelesy.app"

echo "Building Hotelesy.app at $APP"
rm -rf "$APP"
mkdir -p "$APP/Contents/MacOS" "$APP/Contents/Resources"

# ----- Info.plist ------------------------------------------------------------
cat > "$APP/Contents/Info.plist" <<'PLIST'
<?xml version="1.0" encoding="UTF-8"?>
<!DOCTYPE plist PUBLIC "-//Apple//DTD PLIST 1.0//EN"
  "http://www.apple.com/DTDs/PropertyList-1.0.dtd">
<plist version="1.0">
<dict>
    <key>CFBundleName</key>            <string>Hotelesy</string>
    <key>CFBundleDisplayName</key>     <string>Hotelesy</string>
    <key>CFBundleIdentifier</key>      <string>app.hotelesy.desktop</string>
    <key>CFBundleVersion</key>         <string>1.0.0</string>
    <key>CFBundleShortVersionString</key><string>1.0.0</string>
    <key>CFBundlePackageType</key>     <string>APPL</string>
    <key>CFBundleExecutable</key>      <string>Hotelesy</string>
    <key>CFBundleIconFile</key>        <string>Hotelesy.icns</string>
    <key>LSUIElement</key>             <true/>
    <key>LSMinimumSystemVersion</key>  <string>11.0</string>
</dict>
</plist>
PLIST

# ----- Launcher script -------------------------------------------------------
cat > "$APP/Contents/MacOS/Hotelesy" <<LAUNCHER
#!/usr/bin/env bash
# Hotelesy launcher — boots PHP server + opens Chrome in app mode.

set -euo pipefail

APP_ROOT="$PROJECT_ROOT"
LOGFILE="\$HOME/Library/Logs/Hotelesy.log"
PROFILE_DIR="\$HOME/Library/Application Support/Hotelesy/chrome-profile"
PORT_FILE="\$HOME/Library/Application Support/Hotelesy/.port"
mkdir -p "\$(dirname "\$PROFILE_DIR")" "\$PROFILE_DIR"

# Find an unused localhost port (8001 first, then random).
find_port() {
    for p in 8001 8002 8003 8004 8005; do
        if ! lsof -nP -iTCP:\$p -sTCP:LISTEN >/dev/null 2>&1; then
            echo \$p; return
        fi
    done
    python3 -c 'import socket; s=socket.socket(); s.bind(("127.0.0.1",0)); print(s.getsockname()[1]); s.close()'
}
PORT=\$(find_port)
echo "\$PORT" > "\$PORT_FILE"

# Locate PHP — prefer Herd, then /opt/homebrew, then system.
PHP_BIN=""
for candidate in \
    "\$HOME/Library/Application Support/Herd/bin/php" \
    "/opt/homebrew/bin/php" \
    "/usr/local/bin/php" \
    "/usr/bin/php"; do
    if [ -x "\$candidate" ]; then PHP_BIN="\$candidate"; break; fi
done
if [ -z "\$PHP_BIN" ]; then PHP_BIN="\$(command -v php || true)"; fi
if [ -z "\$PHP_BIN" ]; then
    osascript -e 'display alert "Hotelesy" message "PHP is not installed on this Mac."'
    exit 1
fi

# Locate Chrome — prefer Google Chrome, fall back to Edge / Chromium.
BROWSER=""
for candidate in \
    "/Applications/Google Chrome.app/Contents/MacOS/Google Chrome" \
    "/Applications/Microsoft Edge.app/Contents/MacOS/Microsoft Edge" \
    "/Applications/Chromium.app/Contents/MacOS/Chromium" \
    "/Applications/Brave Browser.app/Contents/MacOS/Brave Browser"; do
    if [ -x "\$candidate" ]; then BROWSER="\$candidate"; break; fi
done
if [ -z "\$BROWSER" ]; then
    osascript -e 'display alert "Hotelesy" message "Chrome, Edge, Brave or Chromium is required to run Hotelesy Desktop."'
    exit 1
fi

cd "\$APP_ROOT"

# Boot PHP server in the background with APP_MODE=desktop.
APP_ENV=desktop "\$PHP_BIN" artisan serve --host=127.0.0.1 --port=\$PORT \\
    >> "\$LOGFILE" 2>&1 &
PHP_PID=\$!

# Make sure we kill the PHP server when this process exits.
trap "kill \$PHP_PID 2>/dev/null || true" EXIT INT TERM

# Wait for the server to come up (max 15s).
for i in \$(seq 1 75); do
    if curl -fsS "http://127.0.0.1:\$PORT/up" >/dev/null 2>&1; then break; fi
    sleep 0.2
done

# Open Chrome in app mode with an isolated profile.
"\$BROWSER" \\
    --app="http://127.0.0.1:\$PORT/desktop" \\
    --user-data-dir="\$PROFILE_DIR" \\
    --no-first-run \\
    --no-default-browser-check \\
    --disable-features=ChromeWhatsNewUI

# When the user closes the Chrome window, the trap above kills the PHP server.
LAUNCHER
chmod +x "$APP/Contents/MacOS/Hotelesy"

# ----- Generic icon (placeholder) -------------------------------------------
# If you have a real .icns file, copy it here before / after the build.
if [ -f "$PROJECT_ROOT/desktop-launcher/Hotelesy.icns" ]; then
    cp "$PROJECT_ROOT/desktop-launcher/Hotelesy.icns" "$APP/Contents/Resources/Hotelesy.icns"
fi

# ----- Sign with an ad-hoc signature so Gatekeeper is more lenient ----------
codesign --deep --force --sign - "$APP" 2>/dev/null || true

echo
echo "✓ Built $APP"
echo
echo "Drag it into /Applications, then launch from Spotlight or Finder."
echo "Logs:  ~/Library/Logs/Hotelesy.log"
