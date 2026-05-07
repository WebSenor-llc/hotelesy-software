<?php

namespace App\Livewire\Setup;

use App\Models\Property;
use App\Services\TenantContext;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app-shell')]
class PropertySettings extends Component
{
    public $name = ''; public $code = ''; public $legal_name = '';
    public $address = ''; public $city = ''; public $state = ''; public $country = 'IN'; public $postal_code = '';
    public $phone = ''; public $email = ''; public $website = '';
    public $gst_number = ''; public $pan_number = ''; public $fssai_number = ''; public $liquor_license = '';
    public $check_in_time = '14:00'; public $check_out_time = '12:00'; public $night_audit_time = '02:00';
    public $currency = 'INR'; public $timezone = 'Asia/Kolkata';
    public $status = 'active'; public $total_rooms = 0; public $floors = 0;

    // ECI / LCO / no-show policy
    public $eci_grace_hours = 2;
    public $eci_half_day_threshold_hours = 6;
    public $eci_half_day_pct = 50;
    public $eci_full_day_pct = 100;
    public $lco_grace_hours = 2;
    public $lco_half_day_threshold_hours = 6;
    public $lco_half_day_pct = 50;
    public $lco_full_day_pct = 100;
    public $noshow_fee_pct_first_night = 100;
    public $noshow_grace_hours_after_arrival = 6;
    public $auto_mark_no_show = true;

    public function mount(): void
    {
        $p = app(TenantContext::class)->property();
        if (!$p) return;
        foreach ([
            'name','code','legal_name','address','city','state','country','postal_code','phone','email','website','gst_number','pan_number','fssai_number','liquor_license','check_in_time','check_out_time','night_audit_time','currency','timezone','status','total_rooms','floors',
            'eci_grace_hours','eci_half_day_threshold_hours','eci_half_day_pct','eci_full_day_pct',
            'lco_grace_hours','lco_half_day_threshold_hours','lco_half_day_pct','lco_full_day_pct',
            'noshow_fee_pct_first_night','noshow_grace_hours_after_arrival','auto_mark_no_show',
        ] as $f) {
            $this->$f = $p->$f ?? $this->$f;
        }
    }

    public function save(): void
    {
        $data = $this->validate([
            'name'=>'required|string|max:255','code'=>'required|string|max:20','legal_name'=>'nullable|string|max:255',
            'address'=>'nullable|string','city'=>'nullable|string|max:100','state'=>'nullable|string|max:100',
            'country'=>'required|string|size:2','postal_code'=>'nullable|string|max:20',
            'phone'=>'nullable|string|max:50','email'=>'nullable|email','website'=>'nullable|url',
            'gst_number'=>['nullable','string','max:20','regex:/^[0-9]{2}[A-Z]{5}[0-9]{4}[A-Z]{1}[1-9A-Z]{1}Z[0-9A-Z]{1}$/'],
            'pan_number'=>['nullable','string','max:20','regex:/^[A-Z]{5}[0-9]{4}[A-Z]{1}$/'],
            'fssai_number'=>'nullable|string|max:20','liquor_license'=>'nullable|string|max:50',
            'check_in_time'=>'required','check_out_time'=>'required','night_audit_time'=>'required',
            'currency'=>'required|string|size:3','timezone'=>'required|string','status'=>'required|in:active,inactive,setup',
            'total_rooms'=>'integer|min:0','floors'=>'integer|min:0',
            'eci_grace_hours'=>'integer|min:0|max:24','eci_half_day_threshold_hours'=>'integer|min:0|max:24',
            'eci_half_day_pct'=>'integer|min:0|max:100','eci_full_day_pct'=>'integer|min:0|max:100',
            'lco_grace_hours'=>'integer|min:0|max:24','lco_half_day_threshold_hours'=>'integer|min:0|max:24',
            'lco_half_day_pct'=>'integer|min:0|max:100','lco_full_day_pct'=>'integer|min:0|max:100',
            'noshow_fee_pct_first_night'=>'integer|min:0|max:100',
            'noshow_grace_hours_after_arrival'=>'integer|min:0|max:48',
            'auto_mark_no_show'=>'boolean',
        ], [
            'gst_number.regex' => 'GSTIN must be a valid 15-character GSTIN (e.g. 29ABCDE1234F1Z5).',
            'pan_number.regex' => 'PAN must be 10 chars: 5 letters, 4 digits, 1 letter (e.g. ABCDE1234F).',
        ]);
        app(TenantContext::class)->property()->update($data);
        session()->flash('success','Property settings saved.');
    }

    public function render() { return view('livewire.setup.property-settings'); }
}
