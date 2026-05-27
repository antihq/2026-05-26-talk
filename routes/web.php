<?php

use App\Http\Controllers\WebPushController;
use App\Http\Middleware\EnsureTeamMembership;
use Illuminate\Support\Facades\Route;

Route::view('/', 'pages::home')->name('home')->middleware('guest');

Route::prefix('{current_team}')
    ->middleware(['auth', 'verified', EnsureTeamMembership::class])
    ->group(function () {
        Route::view('/dashboard', 'pages.dashboard')->name('dashboard');
    });

Route::middleware(['auth'])->group(function () {
    Route::livewire('invitations/{invitation}', 'pages::invitations.show')->name('invitations.show');

    Route::post('/webpush/subscribe', [WebPushController::class, 'store'])->name('webpush.subscribe');
    Route::delete('/webpush/subscribe/{id}', [WebPushController::class, 'destroy'])->name('webpush.unsubscribe');
});

require __DIR__.'/settings.php';
