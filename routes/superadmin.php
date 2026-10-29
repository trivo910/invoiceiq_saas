<?php

use App\Http\Controllers\SuperAdminController;
use Illuminate\Support\Facades\Route;

Route::prefix('superadmin')->name('superadmin.')->controller(SuperAdminController::class)->group(function () {
    Route::get('login', 'login')->name('login');
    Route::post('login', 'authenticate')->middleware('throttle:5,1')->name('authenticate');
    Route::middleware('superadmin')->group(function () {
        Route::get('/', 'dashboard')->name('dashboard');
        Route::post('logout', 'logout')->name('logout');
        Route::get('tenants', 'tenants')->name('tenants');
        Route::get('tenants/{tenant}', 'tenant')->name('tenant');
        Route::patch('tenants/{tenant}', 'updateTenant')->middleware('throttle:20,1')->name('tenant.update');
        Route::delete('tenants/{tenant}', 'deleteTenant')->middleware('throttle:5,1')->name('tenant.delete');
        Route::post('tenants/{tenant}/restore', 'restoreTenant')->middleware('throttle:5,1')->name('tenant.restore');
        Route::post('tenants/{tenant}/impersonate', 'impersonate')->middleware('throttle:5,1')->name('impersonate');
        Route::post('impersonation/stop', 'stopImpersonation')->name('impersonation.stop');
        Route::get('audit', 'audit')->name('audit');
        Route::get('catalog/{kind}', 'catalog')->where('kind', 'plans|coupons|announcements')->name('catalog');
        Route::post('catalog/{kind}', 'saveCatalog')->where('kind', 'plans|coupons|announcements')->middleware('throttle:30,1')->name('catalog.create');
        Route::put('catalog/{kind}/{record}', 'saveCatalog')->where('kind', 'plans|coupons|announcements')->whereNumber('record')->middleware('throttle:30,1')->name('catalog.update');
        Route::delete('catalog/{kind}/{record}', 'deleteCatalog')->where('kind', 'plans|coupons|announcements')->whereNumber('record')->middleware('throttle:30,1')->name('catalog.delete');
    });
});
