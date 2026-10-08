<?php

use App\Http\Controllers\ShiftCalMarketingController;
use App\Http\Controllers\ShiftCalPrivacyController;
use App\Http\Controllers\ShiftCalSupportController;
use App\Http\Controllers\WelcomeController;
use Illuminate\Support\Facades\Route;

Route::get('/', WelcomeController::class)->name('home');
Route::get('/shiftcal', ShiftCalMarketingController::class)->name('shiftcal');
Route::get('/shiftcal/privacy', ShiftCalPrivacyController::class)->name('shiftcal.privacy');
Route::get('/shiftcal/support', ShiftCalSupportController::class)->name('shiftcal.support');
