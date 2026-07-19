<?php

use App\Http\Controllers\ProfileController;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use App\Http\Controllers\ChatController;

Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canLogin' => Route::has('login'),
        'canRegister' => Route::has('register'),
        'laravelVersion' => Application::VERSION,
        'phpVersion' => PHP_VERSION,
    ]);
});

Route::get('/dashboard', function () {
    return Inertia::render('Dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::get('/demo', function() {
    return Inertia::render('PresenceChannelDemo');
})->name('demo');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
    // GET: Loads the Vue Chat Room page
    Route::get('/chat/{chatSession}', [ChatController::class, 'show'])->name('chat.room');
    
    // POST: Handles the axios request when a user clicks "Send"
    Route::post('/chat/{chatSession}/message', [ChatController::class, 'store'])->name('chat.message');

});

require __DIR__.'/auth.php';
