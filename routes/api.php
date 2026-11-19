<?php

use App\Http\Controllers\Api\AuthTokenController;
use App\Http\Controllers\Api\CheckpointController;
use App\Http\Controllers\Api\EquipmentController;
use Illuminate\Support\Facades\Route;

// Public: auditors trade credentials for a token that can only do checkpoint:verify.
Route::post('/login', [AuthTokenController::class, 'store'])->middleware('throttle:6,1');

// No token => 401. Token without the ability => 403.
Route::middleware(['auth:sanctum', 'abilities:checkpoint:verify', 'can:verify_checkpoint'])->group(function () {
    Route::delete('/logout', [AuthTokenController::class, 'destroy']);

    Route::get('/equipment', [EquipmentController::class, 'index']);
    Route::get('/equipment/{equipment:qr_code}/authorization', [CheckpointController::class, 'authorization'])
        ->missing(fn () => response()->json(['message' => 'Equipment not found.'], 404));
    Route::get('/reservations/active', [CheckpointController::class, 'active']);
});
