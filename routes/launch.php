<?php

use App\Http\Controllers\{PublicSiteController, WorkspaceSafetyController};
use Illuminate\Support\Facades\Route;

Route::get('/', [PublicSiteController::class, 'home'])->name('home');
Route::get('pricing', [PublicSiteController::class, 'pricing'])->name('pricing');
Route::get('legal/{page}', [PublicSiteController::class, 'legal'])->where('page', 'terms|privacy')->name('legal');
Route::middleware(['auth', 'verified', 'tenant', 'onboarded'])->group(function () {
    Route::get('workspace/activity', [WorkspaceSafetyController::class, 'activity'])->middleware('role:owner,admin')->name('workspace.activity');
    Route::post('workspace/delete', [WorkspaceSafetyController::class, 'delete'])->middleware(['role:owner', 'throttle:3,10'])->name('workspace.delete');
});
