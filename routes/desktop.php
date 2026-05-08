<?php

/**
 * Desktop-only routes.
 *
 * Loaded by App\Providers\DesktopServiceProvider when APP_MODE=desktop.
 * The cloud (SaaS) build never touches this file.
 *
 *  /desktop                            → entrypoint, redirects to activation /
 *                                        first-run setup / dashboard depending
 *                                        on state.
 *  /desktop/activate                   → license activation screen.
 *  /desktop/setup                      → first-run hotel + owner + rooms wizard.
 *  /desktop/license/info               → diagnostic page (machine fingerprint,
 *                                        last-validated, deactivate button).
 *  /desktop/license/deactivate         → POST to release this machine.
 *  /desktop/license/phone-home         → POST to manually re-validate.
 */

use App\Livewire\Desktop\Activation as DesktopActivation;
use App\Livewire\Desktop\FirstRunSetup as DesktopFirstRunSetup;
use App\Services\Desktop\HardwareFingerprint;
use App\Services\Desktop\LicenseActivator;
use Illuminate\Support\Facades\Route;

Route::prefix('desktop')->group(function () {

    /*
     * Entrypoint. Decides where the user should land:
     *   no license   → activation screen
     *   activated, no tenant/property yet → setup wizard
     *   activated and provisioned → dashboard (auth required)
     */
    Route::get('/', function (LicenseActivator $activator) {
        $state = $activator->status();

        if (in_array($state['status'], [
            LicenseActivator::STATUS_UNACTIVATED,
            LicenseActivator::STATUS_INVALID,
        ], true)) {
            return redirect()->route('desktop.license.activate');
        }

        // License OK → check provisioning. Tables may not exist on a brand-new
        // install (the installer runs migrations on first launch).
        try {
            if (! \Illuminate\Support\Facades\Schema::hasTable('users') ||
                ! \App\Models\User::query()->exists()) {
                return redirect()->route('desktop.setup.firstrun');
            }
        } catch (\Throwable $e) {
            return redirect()->route('desktop.setup.firstrun');
        }

        if (! auth()->check()) {
            return redirect()->route('login');
        }

        return redirect()->route('dashboard');
    })->name('desktop.home');

    /*
     * Activation screen — entered with a 25-char key from the purchase email.
     */
    Route::get('activate', DesktopActivation::class)->name('desktop.license.activate');

    /*
     * First-run setup wizard.
     */
    Route::get('setup', DesktopFirstRunSetup::class)->name('desktop.setup.firstrun');

    /*
     * License diagnostic page — displays machine fingerprint, last validation,
     * and a "deactivate this machine" button.
     */
    Route::get('license/info', function (LicenseActivator $activator, HardwareFingerprint $fp) {
        $state = $activator->status();
        return view('desktop.license-info', [
            'state'       => $state,
            'fingerprint' => $fp->display(),
            'host'        => gethostname(),
            'os'          => PHP_OS_FAMILY,
        ]);
    })->name('desktop.license.info');

    /*
     * Manually trigger a phone-home re-validation. Useful when the user just
     * came back online after the soft-warn / read-only banner.
     */
    Route::post('license/phone-home', function (LicenseActivator $activator) {
        $ok = $activator->phoneHome();
        return back()->with($ok ? 'success' : 'warning',
            $ok ? 'License re-validated successfully.'
                : 'Could not reach the license server. Please check your internet connection.');
    })->name('desktop.license.phonehome');

    /*
     * Release this machine's license slot.
     */
    Route::post('license/deactivate', function (LicenseActivator $activator) {
        $activator->deactivate();
        return redirect()->route('desktop.license.activate')
            ->with('reason', 'License deactivated on this machine.');
    })->name('desktop.license.deactivate');
});
