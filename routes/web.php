<?php

use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;
use App\Events\WebRTCSignal;
use Illuminate\Http\Request;
use App\Http\Controllers\VideoMatchController;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/dashboard', function () {
    return view('dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::post('/video-match/start', [VideoMatchController::class, 'start'])
        ->name('video-match.start');

    Route::post('/video-match/next', [VideoMatchController::class, 'next'])
        ->name('video-match.next');

    Route::post('/video-match/leave', [VideoMatchController::class, 'leave'])
        ->name('video-match.leave');
});


Route::middleware('auth')->group(function () {

    Route::post('/webrtc/signal', function (Request $request) {
        $validated = $request->validate([
            'type' => ['required', 'string'],
            'data' => ['required', 'array'],
            'receiverId' => ['required', 'integer'],
        ]);

        broadcast(new WebRTCSignal(
            type: $validated['type'],
            data: $validated['data'],
            senderId: auth()->id(),
            receiverId: $validated['receiverId'],
        ))->toOthers();

        return response()->json([
            'success' => true,
        ]);
    });

});


require __DIR__.'/auth.php';
