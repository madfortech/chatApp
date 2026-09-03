<?php

use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;
use App\Events\WebRTCSignal;
use Illuminate\Http\Request;
use App\Http\Controllers\VideoMatchController;
use Illuminate\Support\Facades\Log;

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

Route::post('/webrtc/signal', function (Request $request) {

    Log::info('WEBRTC SIGNAL: request received', [
        'user_id' => auth()->id(),
    ]);

    try {

        $validated = $request->validate([
            'type' => ['required', 'string'],
            'data' => ['nullable', 'array'],
            'receiverId' => ['required', 'integer'],
        ]);

        Log::info('WEBRTC SIGNAL: validation passed', [
            'type' => $validated['type'],
            'receiverId' => $validated['receiverId'],
        ]);

        Log::info('WEBRTC SIGNAL: broadcasting', [
            'senderId' => (int) auth()->id(),
            'receiverId' => (int) $validated['receiverId'],
        ]);

        broadcast(new WebRTCSignal(
            type: $validated['type'],
            data: $validated['data'] ?? [],
            senderId: (int) auth()->id(),
            receiverId: (int) $validated['receiverId'],
        ))->toOthers();

        Log::info('WEBRTC SIGNAL: broadcast successful');

        return response()->json([
            'success' => true,
        ]);

    } catch (\Throwable $e) {

        Log::error('WEBRTC SIGNAL: BROADCAST FAILED', [
            'message' => $e->getMessage(),
            'exception' => get_class($e),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
        ]);

        return response()->json([
            'success' => false,
            'message' => $e->getMessage(),
        ], 500);
    }

})->middleware('auth');
require __DIR__.'/auth.php';
