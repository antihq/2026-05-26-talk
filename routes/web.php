<?php

use App\Http\Controllers\WebPushController;
use App\Http\Middleware\EnsureTeamMembership;
use Illuminate\Support\Facades\Route;

Route::view('/', 'pages::home')->name('home')->middleware('guest');

Route::get('/sw.js', function () {
    return response()
        ->make(file_get_contents(resource_path('js/sw.js')))
        ->header('Content-Type', 'application/javascript')
        ->header('Cache-Control', 'no-cache, must-revalidate');
});

Route::get('/manifest', function () {
    return response()->view('manifest-json')->header('Content-Type', 'application/json');
});

Route::prefix('{current_team}')
    ->middleware(['auth', 'verified', EnsureTeamMembership::class])
    ->group(function () {
        Route::get('dashboard', fn () => redirect()->route('rooms.index'))->name('dashboard');
        Route::livewire('rooms', 'pages::rooms.index')->name('rooms.index');
        Route::livewire('rooms/create', 'pages::rooms.create')->name('rooms.create');
        Route::livewire('rooms/{room}/edit', 'pages::rooms.edit')->name('rooms.edit');
        Route::livewire('rooms/{room}', 'pages::rooms.show')->name('rooms.show');
    });

Route::middleware(['auth'])->group(function () {
    Route::livewire('invitations/{invitation}', 'pages::invitations.show')->name('invitations.show');

    Route::post('/webpush/subscribe', [WebPushController::class, 'store'])->name('webpush.subscribe');
    Route::delete('/webpush/subscribe/{id}', [WebPushController::class, 'destroy'])->name('webpush.unsubscribe');
});

require __DIR__.'/settings.php';
