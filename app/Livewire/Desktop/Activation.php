<?php

namespace App\Livewire\Desktop;

use App\Services\Desktop\HardwareFingerprint;
use App\Services\Desktop\LicenseActivator;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Validate;
use Livewire\Component;

#[Layout('layouts.desktop-shell')]
class Activation extends Component
{
    // Cloud keys are 24 chars (5 groups of 4). We accept 19-29 char range
    // so users can paste with or without dashes, or with extra spaces.
    #[Validate('required|string|min:19|max:32')]
    public string $activationKey = '';

    public bool $busy = false;
    public ?string $error = null;
    public ?string $success = null;

    public function mount(): void
    {
        // Already activated? Send them onwards.
        $state = app(LicenseActivator::class)->status();
        if ($state['status'] === LicenseActivator::STATUS_ACTIVE) {
            $this->redirect(url('/desktop'), navigate: false);
        }
    }

    public function activate(): void
    {
        $this->busy = true;
        $this->error = null;
        $this->success = null;

        try {
            $this->validate();
            app(LicenseActivator::class)->activate($this->activationKey);
            $this->success = 'License activated. Welcome to Hotelesy.';
            $this->redirect(url('/desktop'), navigate: false);
        } catch (\Throwable $e) {
            $this->error = $e->getMessage();
        } finally {
            $this->busy = false;
        }
    }

    public function render()
    {
        return view('livewire.desktop.activation', [
            'fingerprint' => HardwareFingerprint::display(),
            'os'          => PHP_OS_FAMILY,
            'host'        => gethostname(),
            'version'     => config('desktop.version', '1.0.0'),
        ]);
    }
}
