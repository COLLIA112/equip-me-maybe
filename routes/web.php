<?php

use App\Http\Controllers\ProfileController; // from Breeze
use App\Http\Controllers\Web\DashboardController;
use App\Http\Controllers\Web\DepartmentReportController;
use App\Http\Controllers\Web\DocumentController;
use App\Http\Controllers\Web\EquipmentController;
use App\Http\Controllers\Web\MaintenanceLogController;
use App\Http\Controllers\Web\ReservationController;
use App\Http\Controllers\Web\UserRoleController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => view('welcome'));

Route::middleware('auth')->group(function () {
    // ----- account (Breeze) -----
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    // ----- shared pages -----
    Route::get('/dashboard', DashboardController::class)->name('dashboard');
    Route::get('/equipment', [EquipmentController::class, 'index'])->name('equipment.index');

    // ----- lab manager and department head: equipment CRUD (policy decides the record) -----
    Route::middleware('can:manage_equipment')->group(function () {
        Route::get('/equipment/create', [EquipmentController::class, 'create'])->name('equipment.create');
        Route::post('/equipment', [EquipmentController::class, 'store'])->name('equipment.store');
        Route::get('/equipment/{equipment}/edit', [EquipmentController::class, 'edit'])->name('equipment.edit');
        Route::put('/equipment/{equipment}', [EquipmentController::class, 'update'])->name('equipment.update');
        Route::delete('/equipment/{equipment}', [EquipmentController::class, 'destroy'])->name('equipment.destroy');

        Route::get('/maintenance', [MaintenanceLogController::class, 'index'])->name('maintenance.index');
        Route::post('/maintenance', [MaintenanceLogController::class, 'store'])->name('maintenance.store');
        Route::patch('/maintenance/{log}/resolve', [MaintenanceLogController::class, 'resolve'])->name('maintenance.resolve');
    });

    Route::get('/equipment/{equipment}', [EquipmentController::class, 'show'])->name('equipment.show');

    // ----- documents -----
    Route::get('/documents/checklist', [DocumentController::class, 'checklist'])->name('documents.checklist');
    Route::get('/documents/{document}/download', [DocumentController::class, 'download'])->name('documents.download');
    Route::post('/documents/{document}/acknowledge', [DocumentController::class, 'acknowledge'])->name('documents.acknowledge');
    Route::get('/equipment/{equipment}/documents', [DocumentController::class, 'index'])->name('documents.index');
    Route::post('/equipment/{equipment}/documents', [DocumentController::class, 'store'])->name('documents.store');
    Route::delete('/documents/{document}', [DocumentController::class, 'destroy'])->name('documents.destroy');

    // ----- student and faculty -----
    Route::middleware('can:reserve_equipment')->group(function () {
        Route::get('/reservations', [ReservationController::class, 'index'])->name('reservations.index');
        Route::get('/equipment/{equipment}/reserve', [ReservationController::class, 'create'])->name('reservations.create');
        Route::post('/equipment/{equipment}/reserve', [ReservationController::class, 'store'])->name('reservations.store');
        Route::patch('/reservations/{reservation}/cancel', [ReservationController::class, 'cancel'])->name('reservations.cancel');
    });

    // ----- lab manager: queue and lifecycle (ReservationPolicy@manage on each action) -----
    Route::get('/queue', [ReservationController::class, 'queue'])->name('reservations.queue');
    Route::patch('/reservations/{reservation}/approve', [ReservationController::class, 'approve'])->name('reservations.approve');
    Route::patch('/reservations/{reservation}/reject', [ReservationController::class, 'reject'])->name('reservations.reject');
    Route::patch('/reservations/{reservation}/handover', [ReservationController::class, 'handover'])->name('reservations.handover');
    Route::patch('/reservations/{reservation}/return', [ReservationController::class, 'return'])->name('reservations.return');

    // ----- department head -----
    Route::middleware('can:view_department_reports')->prefix('department')->name('department.')->group(function () {
        Route::get('/', [DepartmentReportController::class, 'index'])->name('index');
        Route::get('/equipment/{equipment}/breakdowns', [DepartmentReportController::class, 'breakdowns'])->name('breakdowns');
        Route::get('/users', [UserRoleController::class, 'index'])->name('users');
        Route::patch('/users/{user}', [UserRoleController::class, 'update'])->name('users.update');
    });
});

require __DIR__.'/auth.php'; // Breeze: login, register, password reset
