<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\GstReportController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\ItemController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\UsersController;
use Illuminate\Support\Facades\Route;

// ── Guest-only (redirects authenticated users to /) ────────────
Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'create'])->name('login');
    Route::post('/login', [LoginController::class, 'store'])
        ->middleware('throttle:5,1')   // 5 attempts per minute per IP
        ->name('login.post');
});

Route::post('/logout', [LoginController::class, 'destroy'])
    ->name('logout')
    ->middleware('auth');

// ── Authenticated routes ───────────────────────────────────────
Route::middleware('auth')->group(function () {

    // Dashboard
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

    // ── Invoices ───────────────────────────────────────────────
    // Read (all roles)
    Route::get('invoices', [InvoiceController::class, 'index'])->name('invoices.index');
    Route::get('invoices/{invoice}/pdf', [InvoiceController::class, 'pdf'])->name('invoices.pdf');

    // Write (admin + staff)
    Route::middleware('role:admin,staff')->group(function () {
        Route::get('invoices/create', [InvoiceController::class, 'create'])->name('invoices.create');
        Route::post('invoices', [InvoiceController::class, 'store'])->name('invoices.store');
        Route::get('invoices/{invoice}/edit', [InvoiceController::class, 'edit'])->name('invoices.edit');
        Route::put('invoices/{invoice}', [InvoiceController::class, 'update'])->name('invoices.update');
        Route::delete('invoices/{invoice}', [InvoiceController::class, 'destroy'])->name('invoices.destroy');
        Route::patch('invoices/{invoice}/status', [InvoiceController::class, 'updateStatus'])->name('invoices.status');
        Route::post('invoices/{invoice}/payment', [InvoiceController::class, 'recordPayment'])->name('invoices.payment');
        Route::post('invoices/{invoice}/email', [InvoiceController::class, 'sendEmail'])->name('invoices.email');
    });

    // ── Customers ──────────────────────────────────────────────
    // Search used during invoice creation — all roles
    Route::get('customers/search', [CustomerController::class, 'search'])->name('customers.search');

    // Read
    Route::get('customers', [CustomerController::class, 'index'])->name('customers.index');

    // Write
    Route::middleware('role:admin,staff')->group(function () {
        Route::post('customers', [CustomerController::class, 'store'])->name('customers.store');
        Route::put('customers/{customer}', [CustomerController::class, 'update'])->name('customers.update');
        Route::delete('customers/{customer}', [CustomerController::class, 'destroy'])->name('customers.destroy');
    });

    // ── Items ──────────────────────────────────────────────────
    Route::get('items/search', [ItemController::class, 'search'])->name('items.search');
    Route::get('items', [ItemController::class, 'index'])->name('items.index');

    Route::middleware('role:admin,staff')->group(function () {
        Route::post('items', [ItemController::class, 'store'])->name('items.store');
        Route::put('items/{item}', [ItemController::class, 'update'])->name('items.update');
        Route::delete('items/{item}', [ItemController::class, 'destroy'])->name('items.destroy');
    });

    // Logo image — served via PHP, no storage symlink required
    Route::get('settings/logo/image', [SettingsController::class, 'logoImage'])->name('settings.logo.image');

    // ── Reports (all roles) ────────────────────────────────────
    Route::get('reports/gst', [GstReportController::class, 'index'])->name('reports.gst');
    Route::get('reports/gst/export', [GstReportController::class, 'export'])->name('reports.gst.export');

    // ── Admin-only ─────────────────────────────────────────────
    Route::middleware('role:admin')->group(function () {
        Route::get('settings', [SettingsController::class, 'index'])->name('settings.index');
        Route::post('settings', [SettingsController::class, 'update'])->name('settings.update');
        Route::post('settings/logo', [SettingsController::class, 'uploadLogo'])->name('settings.logo.upload');
        Route::delete('settings/logo', [SettingsController::class, 'removeLogo'])->name('settings.logo.remove');
        Route::post('settings/test-email', [SettingsController::class, 'testEmail'])->name('settings.test-email');

        Route::resource('users', UsersController::class)->except(['show', 'create', 'edit']);
    });
});
