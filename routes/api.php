<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\DeviceController;
use App\Http\Controllers\Api\V1\EntitlementController;
use App\Http\Controllers\Api\V1\PurchaseController;
use App\Http\Controllers\Api\V1\ShiftCal\CloudBackupController;
use App\Http\Controllers\Api\V1\ShiftCal\EventController;
use App\Http\Controllers\Api\V1\ShiftCal\PartnerController;
use App\Http\Controllers\Api\V1\ShiftCal\ShiftTemplateController;
use App\Http\Controllers\Api\V1\ShiftCal\TemplateApplicationController;
use App\Http\Controllers\Api\V1\ShiftCal\WageSettingController;
use App\Http\Controllers\Api\V1\StoreNotificationController;
use App\Http\Middleware\EnsureAppMembership;
use App\Http\Middleware\EnsureShiftCalApp;
use App\Http\Middleware\ResolveMobileApp;
use Illuminate\Support\Facades\Route;

Route::prefix('v1/apps/{app}')->name('api.v1.')->middleware(ResolveMobileApp::class)->group(function (): void {
    Route::get('meta', [AuthController::class, 'meta'])->middleware('throttle:60,1')->name('meta');
    Route::middleware('throttle:mobile-auth')->group(function (): void {
        Route::post('auth/register', [AuthController::class, 'register'])->name('register');
        Route::post('auth/login', [AuthController::class, 'login'])->name('login');
    });
    Route::middleware(['auth:sanctum', EnsureAppMembership::class, 'throttle:mobile-api'])->group(function (): void {
        Route::prefix('shiftcal')->name('shiftcal.')->middleware(EnsureShiftCalApp::class)->group(function (): void {
            Route::get('partner', [PartnerController::class, 'show'])->name('partner.show');
            Route::get('partner/schedule', [PartnerController::class, 'schedule'])->name('partner.schedule');
            Route::post('partner/invitation', [PartnerController::class, 'invite'])->middleware('throttle:5,1')->name('partner.invite');
            Route::delete('partner/invitation', [PartnerController::class, 'cancel'])->name('partner.cancel');
            Route::post('partner/preview', [PartnerController::class, 'preview'])->middleware('throttle:10,1')->name('partner.preview');
            Route::post('partner/accept', [PartnerController::class, 'accept'])->middleware('throttle:10,1')->name('partner.accept');
            Route::delete('partner', [PartnerController::class, 'disconnect'])->name('partner.disconnect');
            Route::get('cloud-backup', [CloudBackupController::class, 'show'])->name('cloud-backup.show');
            Route::post('cloud-backup', [CloudBackupController::class, 'store'])->middleware('throttle:5,1')->name('cloud-backup.store');
            Route::post('template-applications', [TemplateApplicationController::class, 'store'])->name('template-applications.store');
            Route::delete('template-applications/{application}', [TemplateApplicationController::class, 'destroy'])->whereUuid('application')->name('template-applications.destroy');
            Route::apiResource('events', EventController::class)->whereUuid('event');
            Route::apiResource('shift-templates', ShiftTemplateController::class)->whereUuid('shift_template');
            Route::get('wage-settings', [WageSettingController::class, 'show'])->name('wage-settings.show');
            Route::put('wage-settings', [WageSettingController::class, 'update'])->name('wage-settings.update');
        });
        Route::get('entitlements', EntitlementController::class)->name('entitlements');
        Route::get('billing/context', [PurchaseController::class, 'context'])->name('billing.context');
        Route::get('purchases', [PurchaseController::class, 'index'])->name('purchases.index');
        Route::post('purchases/verify', [PurchaseController::class, 'verify'])->middleware('throttle:10,1')->name('purchases.verify');
        Route::post('purchases/restore', [PurchaseController::class, 'verify'])->middleware('throttle:10,1')->name('purchases.restore');
        Route::get('devices', [DeviceController::class, 'index'])->name('devices.index');
        Route::get('devices/{device}', [DeviceController::class, 'show'])->whereNumber('device')->name('devices.show');
        Route::delete('devices/{device}', [DeviceController::class, 'destroy'])->whereNumber('device')->name('devices.destroy');
        Route::get('auth/me', [AuthController::class, 'me'])->name('me');
        Route::post('auth/logout', [AuthController::class, 'logout'])->name('logout');
    });
});

Route::post('v1/store-notifications/{app}/{platform}', StoreNotificationController::class)->whereIn('platform', ['ios', 'android'])->name('api.v1.store-notifications');
