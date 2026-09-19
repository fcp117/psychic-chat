<?php

use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use App\Http\Controllers\ChatController;

Route::get('/', function () {
    return Inertia::render('Dashboard');
})->middleware('auth')->name('home');

// Keep existing dashboard links and authentication redirects compatible.
Route::get('/dashboard', fn () => redirect()->route('home'))
    ->middleware('auth')->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/chat', function (\Illuminate\Http\Request $request) {
        app(\App\Services\ReadingBilling::class)->sweep();
        $sessions = \App\Models\ChatSession::query()
            ->where(function ($query) use ($request) {
                $query->where('client_id', $request->user()->id)
                    ->orWhere('counselor_id', $request->user()->id);
            })
            ->whereIn('id', \App\Models\ChatSession::selectRaw('MAX(id)')->groupBy('client_id','counselor_id'))
            ->with(['client:id,name', 'counselor:id,name'])
            ->latest('updated_at')->paginate(12);

                $sessions->through(function ($s) use ($request) { $s->setAttribute('conversation_id',$s->conversationId()); if ($request->user()->role==='counselor') $s->makeHidden(['billed_units','billed_seconds']); return $s; });
        return Inertia::render('Chat/Index', ['sessions' => $sessions]);
    })->name('chat.index');
    Route::get('/earnings', [ChatController::class, 'earnings'])->middleware('role:counselor')->name('earnings');
    Route::patch('/psychics/{counselor}/notifications', [ChatController::class, 'preference'])->name('psychics.notifications');
    Route::get('/psychics', [ChatController::class, 'psychics'])->name('psychics.index');
    Route::post('/psychics/{counselor}/chat', [ChatController::class, 'start'])->middleware('throttle:20,1')->name('psychics.chat');
    Route::get('/forecast', fn () => Inertia::render('Forecast', ['forecasts' => \Illuminate\Support\Facades\DB::table('forecasts')->whereNotNull('published_at')->where('published_at', '<=', now())->orderByDesc('published_at')->paginate(10)]))->name('forecast');
    Route::get('/credits', [\App\Http\Controllers\CreditPurchaseController::class,'index'])->name('credits');
    Route::post('/credits/checkout', [\App\Http\Controllers\CreditPurchaseController::class,'checkout'])->middleware(['role:user','throttle:10,1'])->name('credits.checkout');
    Route::get('/credits/purchases/{purchase}', [\App\Http\Controllers\CreditPurchaseController::class,'show'])->name('credits.purchase');
    Route::post('/credits/purchases/{purchase}/verify', [\App\Http\Controllers\CreditPurchaseController::class,'verify'])->middleware('throttle:12,1')->name('credits.verify');
});

Route::get('/demo', function() {
    return Inertia::render('PresenceChannelDemo');
})->name('demo');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
    // GET: Loads the Vue Chat Room page
    Route::get('/reading-requests', [ChatController::class, 'requests'])->middleware('role:counselor')->name('chat.requests');
    Route::get('/chat/{chatSession}/agreement', [ChatController::class, 'agreement'])->name('chat.agreement');
    Route::get('/chat/{chatSession}', [ChatController::class, 'show'])->name('chat.room');
    
    Route::post('/chat/{chatSession}/accept', [ChatController::class, 'accept'])->name('chat.accept');
    Route::post('/chat/{chatSession}/end', [ChatController::class, 'end'])->name('chat.end');
    Route::post('/chat/{chatSession}/heartbeat', [ChatController::class, 'heartbeat'])->middleware('throttle:120,1')->name('chat.heartbeat');
    // POST: Handles the axios request when a user clicks "Send"
    Route::post('/chat/{chatSession}/message', [ChatController::class, 'store'])->name('chat.message');

});

Route::middleware(['auth', 'role:admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/', [\App\Http\Controllers\AdminController::class, 'index'])->name('settings');
    Route::patch('/credit-shop', [\App\Http\Controllers\AdminController::class,'shop'])->name('shop');
    Route::post('/credit-packages', [\App\Http\Controllers\AdminController::class,'package'])->name('package');
    Route::patch('/pricing', [\App\Http\Controllers\AdminController::class, 'pricing'])->name('pricing');
    Route::patch('/users/{user}', [\App\Http\Controllers\AdminController::class, 'user'])->name('user');
    Route::post('/users/{user}/credits', [\App\Http\Controllers\AdminController::class, 'credits'])->name('credits');
    Route::post('/sessions/{chatSession}/end', [\App\Http\Controllers\AdminController::class, 'end'])->name('end');
    Route::delete('/forecasts/{forecast}', [\App\Http\Controllers\AdminController::class, 'deleteForecast'])->whereNumber('forecast')->name('forecast.delete');
    Route::post('/forecasts', [\App\Http\Controllers\AdminController::class, 'forecast'])->name('forecast');
});

require __DIR__.'/auth.php';

Route::post('/payment-webhooks/{provider}', [\App\Http\Controllers\CreditPurchaseController::class,'webhook'])->whereIn('provider',['stripe','paypal','maya'])->middleware('throttle:120,1')->name('payments.webhook');
