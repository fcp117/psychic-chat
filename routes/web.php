<?php

use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use App\Http\Controllers\ChatController;

Route::get('/', function () {
    if (request()->user() && !request()->user()->hasVerifiedEmail()) return redirect()->route('verification.notice');
    return Inertia::render('Dashboard');
})->name('home');

Route::get('/about', fn () => Inertia::render('About'))->name('about');
Route::middleware(['auth','verified'])->group(function () {
    Route::get('/reading-feedback', [\App\Http\Controllers\CoachReviewController::class,'index'])->name('reviews.index');
    Route::post('/reading-feedback/{chatSession}', [\App\Http\Controllers\CoachReviewController::class,'store'])->middleware('throttle:10,1,reading-feedback:')->name('reviews.store');
    Route::get('/admin/reviews', [\App\Http\Controllers\CoachReviewController::class,'coaches'])->middleware('role:admin')->name('admin.reviews');
    Route::get('/admin/reviews/coaches/{coach}', [\App\Http\Controllers\CoachReviewController::class,'show'])->middleware('role:admin')->name('admin.reviews.coach');
    Route::patch('/admin/reviews/{review}', [\App\Http\Controllers\CoachReviewController::class,'moderate'])->middleware('role:admin')->name('admin.reviews.moderate');
});
Route::get('/daily-tarot', [\App\Http\Controllers\TarotController::class, 'index'])->name('tarot.index');
Route::post('/daily-tarot/draw', [\App\Http\Controllers\TarotController::class, 'draw'])->middleware('throttle:12,1,tarot-draw:')->name('tarot.draw');
Route::get('/terms', fn () => Inertia::render('Legal', ['document' => 'terms']))->name('terms');
Route::get('/privacy', fn () => Inertia::render('Legal', ['document' => 'privacy']))->name('privacy');
Route::get('/refunds', fn () => Inertia::render('Legal', ['document' => 'refunds']))->name('refunds');
Route::get('/service-disclaimer', fn () => Inertia::render('Legal', ['document' => 'disclaimer']))->name('service.disclaimer');

// Keep existing dashboard links and authentication redirects compatible.
Route::get('/dashboard', fn () => redirect()->route('home'))
    ->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::post('/assistant/respond', [\App\Http\Controllers\AssistantController::class, 'respond'])->middleware('throttle:8,1')->name('assistant.respond');
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
    Route::get('/forecast', function () {
        abort_unless(config('features.forecast'), 404);
        return Inertia::render('Forecast', ['forecasts' => \Illuminate\Support\Facades\DB::table('forecasts')->whereNotNull('published_at')->where('published_at', '<=', now())->orderByDesc('published_at')->paginate(10)]);
    })->name('forecast');
    Route::get('/credits', [\App\Http\Controllers\CreditPurchaseController::class,'index'])->name('credits');
    Route::post('/credits/checkout', [\App\Http\Controllers\CreditPurchaseController::class,'checkout'])->middleware(['role:user','throttle:10,1'])->name('credits.checkout');
    Route::get('/credits/purchases/{purchase}', [\App\Http\Controllers\CreditPurchaseController::class,'show'])->name('credits.purchase');
    Route::post('/credits/purchases/{purchase}/verify', [\App\Http\Controllers\CreditPurchaseController::class,'verify'])->middleware('throttle:12,1')->name('credits.verify');
    Route::post('/credits/purchases/{purchase}/cancel', [\App\Http\Controllers\CreditPurchaseController::class,'cancel'])->middleware('throttle:6,1')->name('credits.cancel');
});

Route::get('/demo', function() {
    return Inertia::render('PresenceChannelDemo');
})->middleware(['auth', 'verified'])->name('demo');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::post('/profile/photo', [\App\Http\Controllers\ProfilePhotoController::class, 'store'])->middleware('throttle:10,1')->name('profile.photo.store');
    Route::delete('/profile/photo', [\App\Http\Controllers\ProfilePhotoController::class, 'destroy'])->name('profile.photo.destroy');
    Route::get('/members/{user}/photo', [\App\Http\Controllers\ProfilePhotoController::class, 'show'])->name('profile.photo.show');
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
    // GET: Loads the Vue Chat Room page
    Route::get('/reading-requests', [ChatController::class, 'requests'])->middleware('role:counselor')->name('chat.requests');
    Route::get('/chat/{chatSession}/agreement', [ChatController::class, 'agreement'])->name('chat.agreement');
    Route::get('/chat/{chatSession}', [ChatController::class, 'show'])->name('chat.room');
    
    Route::post('/chat/{chatSession}/accept', [ChatController::class, 'accept'])->name('chat.accept');
    Route::post('/chat/{chatSession}/end', [ChatController::class, 'end'])->name('chat.end');
    Route::post('/chat/{chatSession}/heartbeat', [ChatController::class, 'heartbeat'])->middleware('throttle:120,1,chat-heartbeat:')->name('chat.heartbeat');
    // POST: Handles the axios request when a user clicks "Send"
    Route::post('/chat/{chatSession}/message', [ChatController::class, 'store'])->name('chat.message');

});

Route::middleware(['auth', 'verified', 'role:admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/tarot-cards', [\App\Http\Controllers\AdminTarotController::class, 'index'])->name('tarot');
    Route::post('/tarot-cards', [\App\Http\Controllers\AdminTarotController::class, 'store'])->name('tarot.store');
    Route::post('/tarot-cards/{card}', [\App\Http\Controllers\AdminTarotController::class, 'update'])->name('tarot.update');
    Route::get('/', [\App\Http\Controllers\AdminController::class, 'index'])->name('settings');
    Route::post('/account-cleanup/preview', [\App\Http\Controllers\AccountCleanupController::class,'preview'])->middleware('throttle:10,1')->name('cleanup.preview');
    Route::delete('/account-cleanup', [\App\Http\Controllers\AccountCleanupController::class,'destroy'])->middleware('throttle:10,1')->name('cleanup.destroy');
    Route::patch('/credit-shop', [\App\Http\Controllers\AdminController::class,'shop'])->name('shop');
    Route::post('/credit-packages', [\App\Http\Controllers\AdminController::class,'package'])->name('package');
    Route::patch('/pricing', [\App\Http\Controllers\AdminController::class, 'pricing'])->name('pricing');
    Route::patch('/users/{user}', [\App\Http\Controllers\AdminController::class, 'user'])->name('user');
    Route::post('/users/{user}/credits', [\App\Http\Controllers\AdminController::class, 'credits'])->name('credits');
    Route::post('/sessions/{chatSession}/end', [\App\Http\Controllers\AdminController::class, 'end'])->name('end');
    Route::delete('/forecasts/{forecast}', [\App\Http\Controllers\AdminController::class, 'deleteForecast'])->whereNumber('forecast')->name('forecast.delete');
    Route::post('/forecasts', [\App\Http\Controllers\AdminController::class, 'forecast'])->name('forecast');
});

Route::middleware(['auth','verified'])->group(function() {
    Route::get('/become-a-counselor',[\App\Http\Controllers\CounselorApplicationController::class,'show'])->name('counselor.apply');
    Route::post('/become-a-counselor',[\App\Http\Controllers\CounselorApplicationController::class,'store'])->middleware(['role:user','throttle:5,60'])->name('counselor.submit');
    Route::get('/counselors/{counselor}',[\App\Http\Controllers\CounselorApplicationController::class,'profile'])->name('counselor.profile');
    Route::patch('/admin/applications/{application}',[\App\Http\Controllers\CounselorApplicationController::class,'review'])->middleware('role:admin')->name('admin.application.review');
    Route::get('/notifications',[\App\Http\Controllers\NotificationController::class,'index'])->name('notifications.index');
    Route::post('/notifications/read',[\App\Http\Controllers\NotificationController::class,'read'])->middleware('throttle:60,1')->name('notifications.read');
});
require __DIR__.'/auth.php';
Route::post('/chat/{chatSession}/report', [\App\Http\Controllers\ReportController::class,'store'])->middleware(['auth','verified','throttle:5,60,chat-report:'])->name('chat.report');
Route::middleware(['auth','verified','role:admin'])->group(function () {
    Route::get('/admin/reports',[\App\Http\Controllers\ReportController::class,'index'])->name('admin.reports');
    Route::patch('/admin/reports/{report}',[\App\Http\Controllers\ReportController::class,'review'])->whereNumber('report')->name('admin.reports.review');
    Route::delete('/admin/ip-blocks/{block}',[\App\Http\Controllers\ReportController::class,'unblock'])->whereNumber('block')->name('admin.ip-blocks.delete');
});

Route::post('/payment-webhooks/paymongo', [\App\Http\Controllers\CreditPurchaseController::class,'webhook'])->middleware('throttle:120,1')->name('payments.webhook');
