<?php

use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;
use App\Events\WebRTCSignal;
use Illuminate\Http\Request;

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
});




Route::post('/webrtc/signal', function (Request $request) {
    broadcast(new WebRTCSignal(
        type: $request->string('type')->toString(),
        data: $request->input('data', []),
        senderId: auth()->id(),
    ))->toOthers();

    return response()->json([
        'success' => true,
    ]);
})->middleware('auth');

require __DIR__.'/auth.php';
