<?php

use App\Http\Controllers\WebPushController;
use App\Http\Middleware\EnsureTeamMembership;
use Illuminate\Support\Facades\Route;

Route::view('/', 'pages::home')->name('home')->middleware('guest');

Route::prefix('{current_team}')
    ->middleware(['auth', 'verified', EnsureTeamMembership::class])
    ->group(function () {
        Route::get('dashboard', fn () => redirect()->route('rooms.index'))->name('dashboard');
        Route::livewire('rooms', 'pages::rooms.index')->name('rooms.index');
        Route::livewire('rooms/create', 'pages::rooms.create')->name('rooms.create');
        Route::livewire('rooms/{room}', 'pages::rooms.show')->name('rooms.show');
    });

Route::middleware(['auth'])->group(function () {
    Route::livewire('invitations/{invitation}', 'pages::invitations.show')->name('invitations.show');

    Route::post('/webpush/subscribe', [WebPushController::class, 'store'])->name('webpush.subscribe');
    Route::delete('/webpush/subscribe/{id}', [WebPushController::class, 'destroy'])->name('webpush.unsubscribe');
});

require __DIR__.'/settings.php';
