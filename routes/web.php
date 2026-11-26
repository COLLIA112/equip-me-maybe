<?php

use App\Http\Controllers\ProfileController; // from Breeze
use App\Http\Controllers\Web\ActivityController;
use App\Http\Controllers\Web\DashboardController;
use App\Http\Controllers\Web\DepartmentReportController;
use App\Http\Controllers\Web\DocumentController;
use App\Http\Controllers\Web\EquipmentController;
use App\Http\Controllers\Web\EquipmentToolsController;
use App\Http\Controllers\Web\LabAdminController;
use App\Http\Controllers\Web\LabClosureController;
use App\Http\Controllers\Web\MaintenanceLogController;
use App\Http\Controllers\Web\NotificationController;
use App\Http\Controllers\Web\PreferencesController;
use App\Http\Controllers\Web\ReservationController;
use App\Http\Controllers\Web\TagAdminController;
use App\Http\Controllers\Web\UserRoleController;
use App\Http\Controllers\Web\WishlistController;
use App\Http\Middleware\RememberListPreferences;
use App\Http\Middleware\RequireWebRole;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => view('welcome'));

Route::middleware(['auth', RequireWebRole::class, RememberListPreferences::class])->group(function () {
    // ----- account (Breeze) -----
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    // ----- shared pages -----
    Route::get('/dashboard', DashboardController::class)->name('dashboard');
    Route::get('/equipment', [EquipmentController::class, 'index'])->name('equipment.index');
    Route::patch('/preferences', [PreferencesController::class, 'update'])->name('preferences.update');
    Route::get('/wishlist', [WishlistController::class, 'index'])->name('wishlist.index');
    Route::post('/equipment/{equipment}/wishlist', [WishlistController::class, 'store'])->name('wishlist.store');
    Route::delete('/equipment/{equipment}/wishlist', [WishlistController::class, 'destroy'])->name('wishlist.destroy');
    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::post('/notifications/read-all', [NotificationController::class, 'readAll'])->name('notifications.read-all');
    Route::patch('/notifications/{notification}/read', [NotificationController::class, 'read'])->name('notifications.read');
    Route::get('/activities', [ActivityController::class, 'index'])->name('activities.index');
    Route::get('/equipment-tools', [EquipmentToolsController::class, 'index'])->name('equipment.tools.index');
    Route::get('/equipment-tools/template', [EquipmentToolsController::class, 'template'])->name('equipment.tools.template');
    Route::get('/equipment-tools/export', [EquipmentToolsController::class, 'export'])->name('equipment.tools.export');
    Route::post('/equipment-tools/import', [EquipmentToolsController::class, 'import'])->name('equipment.tools.import');
    Route::get('/equipment-tools/labels', [EquipmentToolsController::class, 'labels'])->name('equipment.tools.labels');
    Route::post('/equipment/{equipment}/clone', [EquipmentToolsController::class, 'duplicate'])->name('equipment.tools.clone');
    Route::resource('lab-closures', LabClosureController::class)->only(['index', 'store', 'update', 'destroy']);
    Route::resource('tags', TagAdminController::class)
        ->only(['index', 'store', 'update', 'destroy']);

    // ----- lab manager and department head: equipment CRUD (policy decides the record) -----
    Route::middleware('can:manage_equipment')->group(function () {
        Route::get('/equipment/create', [EquipmentController::class, 'create'])->name('equipment.create');
        Route::post('/equipment', [EquipmentController::class, 'store'])->name('equipment.store');
        Route::get('/equipment/{equipment}/qr-label', [EquipmentController::class, 'qrLabel'])->name('equipment.qr-label');
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
    Route::post('/equipment/{equipment}/documents/acknowledge-all', [DocumentController::class, 'acknowledgeAll'])->name('equipment.documents.acknowledge-all');
    Route::post('/equipment/{equipment}/documents', [DocumentController::class, 'store'])->name('documents.store');
    Route::delete('/documents/{document}', [DocumentController::class, 'destroy'])->name('documents.destroy');

    // ----- student and faculty -----
    Route::middleware('can:reserve_equipment')->group(function () {
        Route::get('/reservations', [ReservationController::class, 'index'])->name('reservations.index');
        Route::get('/equipment/{equipment}/reserve', [ReservationController::class, 'create'])->name('reservations.create');
        Route::post('/equipment/{equipment}/reserve', [ReservationController::class, 'store'])->name('reservations.store');
        Route::patch('/reservations/{reservation}/cancel', [ReservationController::class, 'cancel'])->name('reservations.cancel');
    });

    Route::post('/reservations/bulk', [ReservationController::class, 'bulk'])->name('reservations.bulk');
    Route::get('/counter', [ReservationController::class, 'counter'])->name('reservations.counter');
    Route::post('/counter/lookup', [ReservationController::class, 'counterLookup'])->name('reservations.counter.lookup');
    Route::get('/reservations/{reservation}/again', [ReservationController::class, 'again'])->name('reservations.again');
    Route::get('/reservations/{reservation}/calendar.ics', [ReservationController::class, 'calendar'])->name('reservations.calendar');
    Route::get('/reservations/{reservation}', [ReservationController::class, 'show'])->name('reservations.show');

    // ----- lab manager: queue and lifecycle (ReservationPolicy@manage on each action) -----
    Route::get('/queue', [ReservationController::class, 'queue'])->name('reservations.queue');
    Route::patch('/reservations/{reservation}/approve', [ReservationController::class, 'approve'])->name('reservations.approve');
    Route::patch('/reservations/{reservation}/reject', [ReservationController::class, 'reject'])->name('reservations.reject');
    Route::patch('/reservations/{reservation}/revoke', [ReservationController::class, 'revoke'])->name('reservations.revoke');
    Route::patch('/reservations/{reservation}/handover', [ReservationController::class, 'handover'])->name('reservations.handover');
    Route::patch('/reservations/{reservation}/return', [ReservationController::class, 'return'])->name('reservations.return');

    // ----- department head -----
    Route::middleware('can:view_department_reports')->prefix('department')->name('department.')->group(function () {
        Route::get('/export', [DepartmentReportController::class, 'export'])->name('export');
        Route::resource('labs', LabAdminController::class)
            ->only(['index', 'store', 'update', 'destroy']);
        Route::get('/', [DepartmentReportController::class, 'index'])->name('index');
        Route::get('/equipment/{equipment}/breakdowns', [DepartmentReportController::class, 'breakdowns'])->name('breakdowns');
        Route::get('/users', [UserRoleController::class, 'index'])->name('users');
        Route::patch('/users/{user}', [UserRoleController::class, 'update'])->name('users.update');
    });
});

require __DIR__.'/auth.php'; // Breeze: login, register, password reset
