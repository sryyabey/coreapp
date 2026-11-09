<?php

use App\Http\Controllers\ClaryflowMarketingController;
use App\Http\Controllers\ClaryflowStoreController;
use App\Http\Controllers\MinimalFastingMarketingController;
use App\Http\Controllers\MinimalFastingPrivacyController;
use App\Http\Controllers\ShiftCalMarketingController;
use App\Http\Controllers\ShiftCalPrivacyController;
use App\Http\Controllers\ShiftCalSupportController;
use App\Http\Controllers\ShiftCalTermsController;
use App\Http\Controllers\WelcomeController;
use Illuminate\Support\Facades\Route;

Route::get('/', WelcomeController::class)->name('home');
Route::get('/claryflow', ClaryflowMarketingController::class)->name('claryflow');
Route::get('/claryflow/store/{platform}', ClaryflowStoreController::class)
    ->whereIn('platform', ['ios', 'android'])
    ->name('claryflow.store');
Route::get('/shiftcal', ShiftCalMarketingController::class)->name('shiftcal');
Route::get('/shiftcal/privacy', ShiftCalPrivacyController::class)->name('shiftcal.privacy');
Route::get('/shiftcal/support', ShiftCalSupportController::class)->name('shiftcal.support');
Route::get('/shiftcal/terms', ShiftCalTermsController::class)->name('shiftcal.terms');

Route::get('/minimal-fasting', MinimalFastingMarketingController::class)->name('minimal-fasting');
Route::get('/minimal-fasting/privacy', MinimalFastingPrivacyController::class)->name('minimal-fasting.privacy');
Route::view('/minimal-fasting/terms', 'minimal-fasting-terms')->name('minimal-fasting.terms');
