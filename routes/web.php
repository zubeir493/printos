<?php

use App\Http\Controllers\PrivateStorageController;
use App\Http\Controllers\WebPushSubscriptionController;
use Illuminate\Support\Facades\Route;

Route::get('login-home', function () {
    return redirect()->route('filament.admin.auth.login');
})->middleware('rate.requests:30,1')->name('login');

Route::get('private-storage/{disk}/{path}', [PrivateStorageController::class, 'show'])
    ->where('path', '.*')
    ->middleware(['signed', 'rate.requests:120,1'])
    ->name('private-storage.show');

Route::middleware(['auth', 'rate.requests:30,1'])->group(function (): void {
    Route::post('webpush/subscriptions', [WebPushSubscriptionController::class, 'store'])
        ->name('webpush.subscriptions.store');

    Route::delete('webpush/subscriptions', [WebPushSubscriptionController::class, 'destroy'])
        ->name('webpush.subscriptions.destroy');
});
