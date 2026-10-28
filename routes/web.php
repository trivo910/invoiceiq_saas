<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\RegistrationController;
use App\Http\Controllers\Auth\PasswordController;
use App\Http\Controllers\Auth\VerificationController;
use App\Http\Controllers\OnboardingController;
use App\Http\Controllers\InvitationController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\GstReportController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\ItemController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\UsersController;
use App\Http\Controllers\BillingController;
use App\Http\Controllers\RazorpayWebhookController;
use Illuminate\Support\Facades\Route;

require __DIR__.'/superadmin.php';
require __DIR__.'/workflows.php';

Route::post('/webhooks/razorpay', RazorpayWebhookController::class)->middleware('throttle:120,1')->name('billing.webhook');

// ── Guest-only (redirects authenticated users to /) ────────────
Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'create'])->name('login');
    Route::post('/login', [LoginController::class, 'store'])
        ->middleware('throttle:5,1')   // 5 attempts per minute per IP
        ->name('login.post');
    Route::get('/register', [RegistrationController::class, 'create'])->name('register');
    Route::post('/register', [RegistrationController::class, 'store'])->middleware('throttle:5,1')->name('register.store');
    Route::get('/forgot-password', [PasswordController::class, 'requestForm'])->name('password.request');
    Route::post('/forgot-password', [PasswordController::class, 'sendLink'])->middleware('throttle:5,1')->name('password.email');
    Route::get('/reset-password/{token}', [PasswordController::class, 'resetForm'])->name('password.reset');
    Route::post('/reset-password', [PasswordController::class, 'reset'])->middleware('throttle:5,1')->name('password.update');
});

Route::middleware(['auth', 'tenant'])->group(function () {
    Route::get('/email/verify', [VerificationController::class, 'notice'])->name('verification.notice');
    Route::get('/email/verify/{id}/{hash}', [VerificationController::class, 'verify'])->middleware(['signed', 'throttle:6,1'])->name('verification.verify');
    Route::post('/email/verification-notification', [VerificationController::class, 'resend'])->middleware('throttle:6,1')->name('verification.send');
    Route::get('/onboarding', [OnboardingController::class, 'show'])->name('onboarding.show');
    Route::post('/onboarding', [OnboardingController::class, 'store'])->middleware(['verified', 'subscription'])->name('onboarding.store');
});

Route::middleware(['auth', 'verified', 'tenant'])->group(function () {
    Route::get('/billing', [BillingController::class, 'index'])->middleware('role:owner')->name('billing.index');
    Route::post('/billing/plans/{plan}/checkout', [BillingController::class, 'checkout'])
        ->middleware(['role:owner', 'throttle:10,1'])->name('billing.checkout');
    Route::get('/billing/invoices/{invoice}/download', [BillingController::class, 'invoice'])->middleware('role:owner')->name('billing.invoices.download');
});

Route::post('/logout', [LoginController::class, 'destroy'])
    ->name('logout')
    ->middleware('auth');

Route::get('/invitations/{token}', [InvitationController::class, 'show'])->name('invitations.accept');
Route::post('/invitations/{token}', [InvitationController::class, 'accept'])->middleware('throttle:10,1')->name('invitations.accept.store');

// ── Authenticated routes ───────────────────────────────────────
Route::middleware(['auth', 'verified', 'tenant', 'onboarded'])->group(function () {

    // Dashboard
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

    // ── Invoices ───────────────────────────────────────────────
    // Read (all roles)
    Route::get('invoices', [InvoiceController::class, 'index'])->name('invoices.index');
    Route::get('invoices/number-preview', [InvoiceController::class, 'numberPreview'])->name('invoices.number-preview');
    Route::get('invoices/{invoice}/pdf', [InvoiceController::class, 'pdf'])->name('invoices.pdf');

    // Write (admin + staff)
    Route::middleware(['can:write-records', 'subscription'])->group(function () {
        Route::get('invoices/create', [InvoiceController::class, 'create'])->name('invoices.create');
        Route::post('invoices', [InvoiceController::class, 'store'])->middleware('plan.limit:invoices')->name('invoices.store');
        Route::get('invoices/{invoice}/edit', [InvoiceController::class, 'edit'])->name('invoices.edit');
        Route::put('invoices/{invoice}', [InvoiceController::class, 'update'])->name('invoices.update');
        Route::delete('invoices/{invoice}', [InvoiceController::class, 'destroy'])->middleware('can:delete-records')->name('invoices.destroy');
        Route::patch('invoices/{invoice}/status', [InvoiceController::class, 'updateStatus'])->name('invoices.status');
        Route::post('invoices/{invoice}/payment', [InvoiceController::class, 'recordPayment'])->name('invoices.payment');
        Route::post('invoices/{invoice}/email', [InvoiceController::class, 'sendEmail'])->middleware('throttle:10,1')->name('invoices.email');
    });

    // ── Customers ──────────────────────────────────────────────
    // Search used during invoice creation — all roles
    Route::get('customers/search', [CustomerController::class, 'search'])->name('customers.search');

    // Read
    Route::get('customers', [CustomerController::class, 'index'])->name('customers.index');

    // Write
    Route::middleware(['can:write-records', 'subscription'])->group(function () {
        Route::post('customers', [CustomerController::class, 'store'])->name('customers.store');
        Route::put('customers/{customer}', [CustomerController::class, 'update'])->name('customers.update');
        Route::delete('customers/{customer}', [CustomerController::class, 'destroy'])->middleware('can:delete-records')->name('customers.destroy');
    });

    // ── Items ──────────────────────────────────────────────────
    Route::get('items/search', [ItemController::class, 'search'])->name('items.search');
    Route::get('items', [ItemController::class, 'index'])->name('items.index');

    Route::middleware(['can:write-records', 'subscription'])->group(function () {
        Route::post('items', [ItemController::class, 'store'])->name('items.store');
        Route::put('items/{item}', [ItemController::class, 'update'])->name('items.update');
        Route::delete('items/{item}', [ItemController::class, 'destroy'])->middleware('can:delete-records')->name('items.destroy');
    });

    // Logo image — served via PHP, no storage symlink required
    Route::get('settings/logo/image', [SettingsController::class, 'logoImage'])->name('settings.logo.image');

    // ── Reports (all roles) ────────────────────────────────────
    Route::get('reports/gst', [GstReportController::class, 'index'])->middleware('plan.feature:gst_reports')->name('reports.gst');
    Route::get('reports/gst/export', [GstReportController::class, 'export'])->middleware('plan.feature:gst_reports')->name('reports.gst.export');

    // ── Admin-only ─────────────────────────────────────────────
    Route::middleware('role:owner,admin')->group(function () {
        Route::get('settings', [SettingsController::class, 'index'])->name('settings.index');
        Route::post('settings', [SettingsController::class, 'update'])->middleware('subscription')->name('settings.update');
        Route::post('settings/logo', [SettingsController::class, 'uploadLogo'])->middleware('subscription')->name('settings.logo.upload');
        Route::delete('settings/logo', [SettingsController::class, 'removeLogo'])->middleware('subscription')->name('settings.logo.remove');
        Route::post('settings/test-email', [SettingsController::class, 'testEmail'])->middleware('subscription')->name('settings.test-email');

        Route::get('users', [UsersController::class, 'index'])->name('users.index');
        Route::post('users', [UsersController::class, 'store'])->middleware(['subscription', 'throttle:10,1'])->name('users.store');
        Route::patch('users/{user}/role', [UsersController::class, 'updateRole'])->middleware('subscription')->name('users.role');
        Route::delete('users/{user}', [UsersController::class, 'destroy'])->middleware('subscription')->name('users.destroy');
    });
});
