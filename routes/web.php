<?php

use App\Http\Controllers\PrivateStorageController;
use Illuminate\Support\Facades\Route;

Route::get('login-home', function () {
    return redirect()->route('filament.admin.auth.login');
})->middleware('rate.requests:30,1')->name('login');

Route::get('private-storage/{disk}/{path}', [PrivateStorageController::class, 'show'])
    ->where('path', '.*')
    ->middleware(['signed', 'rate.requests:120,1'])
    ->name('private-storage.show');
