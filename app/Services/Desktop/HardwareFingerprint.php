<?php

namespace App\Services\Desktop;

/**
 * Generates a stable hardware fingerprint for the machine the desktop app
 * is installed on. The license is bound to this fingerprint so a single
 * activation key can't be reused on multiple machines.
 *
 * Combines: machine UUID (Mac/Win), MAC address of primary NIC, hostname,
 * and OS family — hashed to a 64-char hex string.
 */
class HardwareFingerprint
{
    public static function generate(): string
    {
        $components = [
            'os'       => PHP_OS_FAMILY,
            'host'     => gethostname() ?: '',
            'machine'  => self::machineId(),
            'mac'      => self::primaryMacAddress(),
            // Salt with the app's APP_KEY so different installs of Hotelesy
            // on the same machine produce different fingerprints.
            'app_salt' => substr(hash('sha256', (string) config('app.key')), 0, 16),
        ];

        return hash('sha256', json_encode($components, JSON_UNESCAPED_SLASHES));
    }

    /**
     * Returns a short, human-readable representation of the fingerprint
     * (12 chars, dashed) for display in the activation UI / support tickets.
     * Like:  4f9c-2a18-bd31
     */
    public static function display(): string
    {
        $fp = self::generate();
        return implode('-', [substr($fp, 0, 4), substr($fp, 4, 4), substr($fp, 8, 4)]);
    }

    /**
     * Cross-platform machine UUID lookup.
     *  - Mac:     ioreg -rd1 -c IOPlatformExpertDevice
     *  - Linux:   /etc/machine-id
     *  - Windows: wmic csproduct get UUID
     */
    private static function machineId(): string
    {
        $os = PHP_OS_FAMILY;
        $cmd = match ($os) {
            'Darwin'  => "ioreg -rd1 -c IOPlatformExpertDevice 2>/dev/null | awk '/IOPlatformUUID/{print \$3}' | tr -d '\"'",
            'Linux'   => 'cat /etc/machine-id 2>/dev/null',
            'Windows' => 'wmic csproduct get UUID /value 2>nul | findstr "UUID="',
            default   => null,
        };
        if (! $cmd) return '';

        try {
            $out = trim((string) @shell_exec($cmd));
            // On Windows, output is "UUID=XXXXX-..." — strip the prefix.
            if ($os === 'Windows' && str_starts_with($out, 'UUID=')) {
                $out = substr($out, 5);
            }
            return $out;
        } catch (\Throwable $e) {
            return '';
        }
    }

    /**
     * Returns the MAC address of the first non-loopback network interface.
     */
    private static function primaryMacAddress(): string
    {
        $os = PHP_OS_FAMILY;
        $cmd = match ($os) {
            'Darwin'  => "ifconfig en0 2>/dev/null | awk '/ether/{print \$2}'",
            'Linux'   => "ip link show 2>/dev/null | awk '/link\\/ether/{print \$2; exit}'",
            'Windows' => 'getmac /v /fo csv 2>nul | findstr /v "Disconnected"',
            default   => null,
        };
        if (! $cmd) return '';

        try {
            return strtolower(trim((string) @shell_exec($cmd)));
        } catch (\Throwable $e) {
            return '';
        }
    }
}
