<?php

use App\Http\Controllers\PrivateStorageController;
use Illuminate\Support\Facades\Route;

Route::get('login-home', function () {
    return redirect()->route('filament.admin.auth.login');
})->name('login');

Route::get('private-storage/{disk}/{path}', [PrivateStorageController::class, 'show'])
    ->where('path', '.*')
    ->middleware('signed')
    ->name('private-storage.show');
