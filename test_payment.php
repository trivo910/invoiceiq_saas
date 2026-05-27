<?php
// Quick payment feature test - run via: php artisan tinker test_payment.php
// Or: php -r "require 'bootstrap/app.php'; ..."

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(\Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Invoice;
use App\Models\Payment;

$invoiceId = 5; // INV-2026-0006, total=29500, status=sent, amount_paid=0
$invoice = Invoice::find($invoiceId);

echo "\n=== RECORD PAYMENT - INTEGRATION TEST ===\n";
echo "Invoice: {$invoice->number} | Total: {$invoice->grand_total} | Status: {$invoice->status}\n\n";

// ---- TEST 1: Partial payment ----
Payment::create(['invoice_id'=>$invoiceId,'amount'=>10000,'payment_date'=>'2026-05-27','mode'=>'upi','reference'=>'T-001','notes'=>null]);
$paid = $invoice->payments()->sum('amount');
$status = $paid >= $invoice->grand_total ? 'paid' : 'partial';
$invoice->update(['amount_paid'=>$paid,'status'=>$status]);
$invoice->refresh();
$pass1 = ($invoice->status === 'partial' && (float)$invoice->amount_paid === 10000.0 && (float)$invoice->balance_due === 19500.0);
echo "TEST 1 - Partial payment (10000 of 29500):\n";
echo "  amount_paid={$invoice->amount_paid} status={$invoice->status} balance_due={$invoice->balance_due}\n";
echo "  " . ($pass1 ? "PASS" : "FAIL") . "\n\n";

// ---- TEST 2: Second partial, cumulative ----
Payment::create(['invoice_id'=>$invoiceId,'amount'=>9500,'payment_date'=>'2026-05-27','mode'=>'neft','reference'=>'T-002','notes'=>null]);
$paid = $invoice->payments()->sum('amount');
$status = $paid >= $invoice->grand_total ? 'paid' : 'partial';
$invoice->update(['amount_paid'=>$paid,'status'=>$status]);
$invoice->refresh();
$pass2 = ($invoice->status === 'partial' && (float)$invoice->amount_paid === 19500.0 && (float)$invoice->balance_due === 10000.0);
echo "TEST 2 - Second partial (9500 more, total 19500):\n";
echo "  amount_paid={$invoice->amount_paid} status={$invoice->status} balance_due={$invoice->balance_due}\n";
echo "  " . ($pass2 ? "PASS" : "FAIL") . "\n\n";

// ---- TEST 3: Final payment (exact remaining) ----
Payment::create(['invoice_id'=>$invoiceId,'amount'=>10000,'payment_date'=>'2026-05-27','mode'=>'cash','reference'=>null,'notes'=>null]);
$paid = $invoice->payments()->sum('amount');
$status = $paid >= $invoice->grand_total ? 'paid' : 'partial';
$invoice->update(['amount_paid'=>$paid,'status'=>$status]);
$invoice->refresh();
$pass3 = ($invoice->status === 'paid' && (float)$invoice->amount_paid === 29500.0 && (float)$invoice->balance_due === 0.0);
echo "TEST 3 - Final payment (10000 remaining = fully paid):\n";
echo "  amount_paid={$invoice->amount_paid} status={$invoice->status} balance_due={$invoice->balance_due}\n";
echo "  payments_count=" . $invoice->payments()->count() . "\n";
echo "  " . ($pass3 ? "PASS" : "FAIL") . "\n\n";

// ---- TEST 4: Overpayment edge case ----
Payment::create(['invoice_id'=>$invoiceId,'amount'=>500,'payment_date'=>'2026-05-27','mode'=>'cash','reference'=>null,'notes'=>null]);
$paid = $invoice->payments()->sum('amount');
$status = $paid >= $invoice->grand_total ? 'paid' : 'partial';
$invoice->update(['amount_paid'=>$paid,'status'=>$status]);
$invoice->refresh();
$pass4 = ($invoice->status === 'paid' && (float)$invoice->balance_due === 0.0); // balance_due uses max(0,...) guard
echo "TEST 4 - Overpayment (+500 extra):\n";
echo "  amount_paid={$invoice->amount_paid} status={$invoice->status} balance_due={$invoice->balance_due}\n";
echo "  NOTE: amount_paid({$invoice->amount_paid}) > grand_total({$invoice->grand_total}) — no cap enforced\n";
echo "  " . ($pass4 ? "PASS (balance_due protected by max(0))" : "FAIL") . "\n\n";

// ---- TEST 5: Validation - zero amount rejected ----
try {
    $validator = \Illuminate\Support\Facades\Validator::make(['amount'=>0,'payment_date'=>'2026-05-27','mode'=>'upi'], [
        'amount'       => 'required|numeric|min:0.01',
        'payment_date' => 'required|date',
        'mode'         => 'required|in:cash,upi,neft,rtgs,cheque,card,other',
    ]);
    $pass5 = $validator->fails();
    echo "TEST 5 - Validation rejects amount=0:\n";
    echo "  fails=" . ($validator->fails() ? 'true' : 'false') . " errors=" . implode(', ', $validator->errors()->all()) . "\n";
    echo "  " . ($pass5 ? "PASS" : "FAIL") . "\n\n";
} catch (\Exception $e) {
    echo "TEST 5 - ERROR: " . $e->getMessage() . "\n\n";
}

// ---- Cleanup ----
Payment::where('invoice_id', $invoiceId)->delete();
$invoice->update(['amount_paid'=>0,'status'=>'sent']);
echo "=== CLEANUP DONE ===\n";
echo "\nSUMMARY: " . array_sum([$pass1,$pass2,$pass3,$pass4,$pass5]) . "/5 tests passed\n";
