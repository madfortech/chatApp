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

    Log::channel('webrtc')->info('WEBRTC SIGNAL: request received', [
        'user_id' => auth()->id(),
    ]);

    try {

        $validated = $request->validate([
            'type' => ['required', 'string'],
            'data' => ['nullable', 'array'],
            'receiverId' => ['required', 'integer'],
        ]);

        Log::channel('webrtc')->info('WEBRTC SIGNAL: validation passed', [
            'type' => $validated['type'],
            'receiverId' => $validated['receiverId'],
        ]);

        Log::channel('webrtc')->info('WEBRTC SIGNAL: broadcasting', [
            'senderId' => (int) auth()->id(),
            'receiverId' => (int) $validated['receiverId'],
        ]);

        broadcast(new WebRTCSignal(
            type: $validated['type'],
            data: $validated['data'] ?? [],
            senderId: (int) auth()->id(),
            receiverId: (int) $validated['receiverId'],
        ))->toOthers();

        Log::channel('webrtc')->info('WEBRTC SIGNAL: broadcast successful');

        return response()->json([
            'success' => true,
        ]);

    } catch (\Throwable $e) {

        $previous = $e->getPrevious();

        Log::channel('webrtc')->error(
            'WEBRTC SIGNAL: BROADCAST FAILED',
            [
                'message' => $e->getMessage(),
                'exception' => get_class($e),

                'previous_exception' => $previous
                    ? get_class($previous)
                    : null,

                'previous_message' => $previous
                    ? $previous->getMessage()
                    : null,

                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]
        );

        return response()->json([
            'success' => false,
            'error' => true,

            'message' => $e->getMessage(),
            'exception' => get_class($e),

            'previous_exception' => $previous
                ? get_class($previous)
                : null,

            'previous_message' => $previous
                ? $previous->getMessage()
                : null,

            'file' => $e->getFile(),
            'line' => $e->getLine(),

        ], 500);
    }

})->middleware('auth');


Route::get('/dashboard/webrtc-log', function () {

    $path = storage_path('logs/webrtc.log');

    if (!file_exists($path)) {
        abort(404, 'WebRTC log file not found.');
    }

    return response()->download(
        $path,
        'webrtc.log',
        [
            'Content-Type' => 'text/plain',
        ]
    );

})->middleware(['auth', 'verified']);

require __DIR__.'/auth.php';
