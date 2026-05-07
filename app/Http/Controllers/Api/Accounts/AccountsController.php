<?php

namespace App\Http\Controllers\Api\Accounts;

use App\Http\Controllers\Controller;
use App\Models\Accounts\ChartAccount;
use App\Models\Accounts\GstReturn;
use App\Models\Accounts\NightAuditLog;
use App\Models\Accounts\Voucher;
use App\Models\Accounts\VoucherType;
use App\Models\Property;
use App\Services\Accounts\GstReturnService;
use App\Services\Accounts\NightAuditService;
use App\Services\Accounts\VoucherService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AccountsController extends Controller
{
    public function __construct(
        private readonly VoucherService $vouchers,
        private readonly GstReturnService $gst,
        private readonly NightAuditService $nightAudit,
    ) {}

    /* ============ Chart of Accounts ============ */
    public function chart(Request $request): JsonResponse
    {
        $q = ChartAccount::where('is_active', true);
        if ($request->filled('property_id')) $q->where('property_id', $request->integer('property_id'));
        if ($request->filled('type')) $q->where('type', $request->string('type'));
        return response()->json(['data' => $q->orderBy('code')->get()]);
    }

    /* ============ Vouchers ============ */
    public function vouchers(Request $request): JsonResponse
    {
        $q = Voucher::with('voucherType', 'entries.account');
        if ($request->filled('property_id')) $q->where('property_id', $request->integer('property_id'));
        if ($request->filled('from')) $q->whereDate('voucher_date', '>=', $request->date('from'));
        if ($request->filled('to')) $q->whereDate('voucher_date', '<=', $request->date('to'));
        return response()->json(['data' => $q->latest('voucher_date')->paginate($request->integer('per_page', 25))]);
    }

    public function postVoucher(Request $request): JsonResponse
    {
        $data = $request->validate([
            'property_id' => ['required', 'integer'],
            'voucher_type_id' => ['required', 'integer', 'exists:accounts_voucher_types,id'],
            'voucher_date' => ['required', 'date'],
            'narration' => ['nullable', 'string', 'max:500'],
            'entries' => ['required', 'array', 'min:2'],
            'entries.*.account_id' => ['required', 'integer', 'exists:accounts_chart,id'],
            'entries.*.side' => ['required', 'in:debit,credit'],
            'entries.*.amount' => ['required', 'numeric', 'min:0.01'],
            'entries.*.narration' => ['nullable', 'string', 'max:255'],
        ]);
        $property = Property::findOrFail($data['property_id']);
        $type = VoucherType::findOrFail($data['voucher_type_id']);
        try {
            $voucher = $this->vouchers->post(
                $property,
                $type,
                Carbon::parse($data['voucher_date']),
                $data['entries'],
                ['narration' => $data['narration'] ?? null],
            );
        } catch (\DomainException | \InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
        return response()->json(['data' => $voucher], 201);
    }

    public function reverseVoucher(Request $request, Voucher $voucher): JsonResponse
    {
        $data = $request->validate(['reason' => ['nullable', 'string', 'max:500']]);
        try {
            $reversal = $this->vouchers->reverse($voucher, $data['reason'] ?? null);
        } catch (\DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
        return response()->json(['data' => $reversal]);
    }

    public function trialBalance(Request $request): JsonResponse
    {
        $data = $request->validate([
            'property_id' => ['required', 'integer'],
            'as_of' => ['nullable', 'date'],
        ]);
        $property = Property::findOrFail($data['property_id']);
        $asOf = isset($data['as_of']) ? Carbon::parse($data['as_of']) : null;
        return response()->json(['data' => $this->vouchers->trialBalance($property, $asOf)]);
    }

    /* ============ GST Returns ============ */
    public function gstReturns(Request $request): JsonResponse
    {
        $q = GstReturn::query();
        if ($request->filled('property_id')) $q->where('property_id', $request->integer('property_id'));
        return response()->json(['data' => $q->latest('end_date')->paginate(25)]);
    }

    public function computeGst(Request $request): JsonResponse
    {
        $data = $request->validate([
            'property_id' => ['required', 'integer'],
            'return_type' => ['required', 'in:gstr1,gstr3b'],
            'period' => ['required', 'regex:/^\d{4}-\d{2}$/'],
        ]);
        $property = Property::findOrFail($data['property_id']);
        try {
            $return = $this->gst->compute($property, $data['return_type'], $data['period']);
        } catch (\DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
        return response()->json(['data' => $return]);
    }

    public function markGstFiled(Request $request, GstReturn $return): JsonResponse
    {
        $data = $request->validate(['arn_number' => ['required', 'string', 'max:50']]);
        try {
            $return = $this->gst->markFiled($return, $data['arn_number']);
        } catch (\DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
        return response()->json(['data' => $return]);
    }

    /* ============ Night Audit ============ */
    public function nightAuditHistory(Request $request): JsonResponse
    {
        $q = NightAuditLog::query()->with('performedBy');
        if ($request->filled('property_id')) $q->where('property_id', $request->integer('property_id'));
        return response()->json(['data' => $q->latest('business_date')->paginate(30)]);
    }

    public function runNightAudit(Request $request): JsonResponse
    {
        $data = $request->validate([
            'property_id' => ['required', 'integer'],
            'business_date' => ['nullable', 'date'],
        ]);
        $property = Property::findOrFail($data['property_id']);
        $forDate = isset($data['business_date']) ? Carbon::parse($data['business_date']) : null;
        try {
            $log = $this->nightAudit->run($property, $forDate);
        } catch (\Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
        return response()->json(['data' => $log], 201);
    }
}
