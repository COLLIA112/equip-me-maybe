<?php

use App\Http\Controllers\Api\AuthTokenController;
use App\Http\Controllers\Api\CheckpointController;
use App\Http\Controllers\Api\EquipmentController;
use App\Models\Equipment;
use Illuminate\Support\Facades\Route;

// Public: auditors trade credentials for a token that can only do checkpoint:verify.
Route::post('/login', [AuthTokenController::class, 'store']);

// No token => 401. Token without the ability => 403.
Route::middleware(['auth:sanctum', 'abilities:checkpoint:verify'])->group(function () {
    Route::delete('/logout', [AuthTokenController::class, 'destroy']);

    Route::get('/equipment', [EquipmentController::class, 'index']);
    Route::get('/equipment/{equipment:qr_code}/authorization', [CheckpointController::class, 'authorization']);
    Route::get('/reservations/active', [CheckpointController::class, 'active']);
});
