<?php

use App\Http\Controllers\WorkspaceWorkflowController as Workflow;
use Illuminate\Support\Facades\Route;

Route::get('share/invoices/{token}', [Workflow::class, 'publicInvoice'])->middleware('throttle:60,1')->name('invoices.public');
Route::middleware(['auth', 'verified', 'tenant', 'onboarded'])->group(function () {
    Route::get('invoices/{invoice}/activity', [Workflow::class, 'activity'])->middleware('can:write-records')->name('invoices.activity');
    Route::post('invoices/{invoice}/share', [Workflow::class, 'share'])->middleware(['can:write-records', 'subscription', 'throttle:10,1'])->name('invoices.share');
    Route::delete('invoices/{invoice}/share/{link}', [Workflow::class, 'revoke'])->middleware(['can:write-records', 'subscription'])->name('invoices.share.revoke');
    Route::post('settings/branding', [Workflow::class, 'branding'])->middleware(['role:owner,admin', 'subscription', 'plan.feature:custom_branding'])->name('settings.branding');
    Route::get('exports', [Workflow::class, 'exports'])->middleware('role:owner,admin,accountant')->name('exports.index');
    Route::get('exports/csv/{resource}', [Workflow::class, 'csv'])->middleware(['role:owner,admin,accountant', 'throttle:20,1'])->name('exports.csv');
    Route::post('exports/account', [Workflow::class, 'requestExport'])->middleware(['role:owner', 'throttle:3,10'])->name('exports.account');
    Route::get('exports/account/{export}/download', [Workflow::class, 'download'])->middleware('role:owner')->name('exports.download');
});
