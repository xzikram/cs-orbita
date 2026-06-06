<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\V1\Auth\AuthController;
use App\Http\Controllers\Api\V1\Master\MasterDataController;
use App\Http\Controllers\Api\V1\Cleaning\CleaningActivityController;
use App\Http\Controllers\Api\V1\Dashboard\DashboardController;
use App\Http\Controllers\Api\V1\Audit\AuditController;
use App\Http\Controllers\Api\V1\Complaint\ComplaintController;
use App\Http\Controllers\Api\V1\Admin\UserController;
use App\Http\Controllers\Api\V1\Admin\QrCodeController;
use App\Http\Controllers\Api\V1\ReportController;

/*
|--------------------------------------------------------------------------
| API Routes - CLEANTRACK RS
|--------------------------------------------------------------------------
*/

// API v1
Route::prefix('v1')->group(function () {

    // Auth (public)
    Route::post('/auth/login', [AuthController::class, 'login']);

    // Auth (protected)
    Route::middleware('auth:sanctum')->group(function () {

        // Admin Routes
        Route::middleware('role:administrator')->prefix('admin')->group(function () {
            Route::apiResource('users', UserController::class);
            Route::post('users/{user}/reset-device', [UserController::class, 'resetDevice']);
            Route::get('qr/buildings', [QrCodeController::class, 'getBuildings']);
        });

        // Management & Reports (Admin, Manajemen, Supervisor)
        Route::middleware('role:administrator,manajemen,supervisor')->group(function () {
            Route::get('/reports/monthly', [ReportController::class, 'exportMonthly']);
            Route::get('/reports/audit', [ReportController::class, 'exportAudit']);
        });

        // Auth
        Route::post('/auth/logout', [AuthController::class, 'logout']);
        Route::get('/auth/me', [AuthController::class, 'me']);
        Route::put('/auth/profile', [AuthController::class, 'updateProfile']);
        Route::put('/auth/password', [AuthController::class, 'changePassword']);

        // ========== MASTER DATA (read - all authenticated users) ==========
        Route::get('/buildings', [MasterDataController::class, 'buildings']);
        Route::get('/floors', [MasterDataController::class, 'floors']);
        Route::get('/areas', [MasterDataController::class, 'areas']);
        Route::get('/areas/{area}', [MasterDataController::class, 'showArea']);
        Route::get('/areas/{area}/checklist', [MasterDataController::class, 'areaChecklist']);
        Route::get('/cleaning-objects', [MasterDataController::class, 'cleaningObjects']);
        Route::get('/shifts', [MasterDataController::class, 'shifts']);
        Route::get('/schedules', [MasterDataController::class, 'schedules']);

        // ========== QR SCAN (cleaning service) ==========
        Route::get('/qr/scan/{uuid}', [CleaningActivityController::class, 'scanQr']);

        // ========== CLEANING ACTIVITIES ==========
        Route::get('/activities', [CleaningActivityController::class, 'index']);
        Route::get('/activities/today', [CleaningActivityController::class, 'today']);
        Route::post('/activities', [CleaningActivityController::class, 'store']);
        Route::get('/activities/{activity}', [CleaningActivityController::class, 'show']);
        Route::post('/activities/{activity}/photos', [CleaningActivityController::class, 'uploadPhotos']);
        Route::put('/activities/{activity}/approve', [CleaningActivityController::class, 'approve']);

        // ========== OFFLINE SYNC ==========
        Route::post('/sync/batch', [CleaningActivityController::class, 'batchSync']);

        // ========== DASHBOARD ==========
        Route::get('/dashboard/mobile', [DashboardController::class, 'mobile']);
        Route::get('/dashboard/supervisor', [DashboardController::class, 'supervisor']);
        Route::get('/dashboard/heatmap', [DashboardController::class, 'heatmap']);
        Route::get('/dashboard/audit-grid', [DashboardController::class, 'auditGrid']);
        Route::get('/dashboard/kpi', [DashboardController::class, 'kpi']);
        Route::get('/dashboard/tv', [DashboardController::class, 'tv']);

        // ========== AUDIT (supervisor) ==========
        Route::get('/audits', [AuditController::class, 'index']);
        Route::post('/audits', [AuditController::class, 'store']);
        Route::get('/audits/findings', [AuditController::class, 'findings']);
        Route::put('/audits/findings/{finding}/resolve', [AuditController::class, 'resolveFinding']);

        // ========== REPORTS ==========
        Route::get('/reports/cleaning', [ReportController::class, 'exportMonthly']);
        Route::get('/reports/audits', [ReportController::class, 'exportAudit']);
        Route::get('/reports/matrix-excel', [App\Http\Controllers\Api\V1\Admin\MatrixReportController::class, 'export']);

        // ========== COMPLAINTS ==========
        Route::get('/complaints', [ComplaintController::class, 'index']);
        Route::post('/complaints', [ComplaintController::class, 'store']);
        Route::get('/complaints/{complaint}', [ComplaintController::class, 'show']);
        Route::put('/complaints/{complaint}/status', [ComplaintController::class, 'updateStatus']);

        // ========== ADMIN ==========
        Route::middleware('role:administrator')->group(function () {
            // Users
            Route::get('/admin/users', [UserController::class, 'index']);
            Route::post('/admin/users', [UserController::class, 'store']);
            Route::put('/admin/users/{user}', [UserController::class, 'update']);
            Route::delete('/admin/users/{user}', [UserController::class, 'destroy']);

            // Master data write
            Route::post('/buildings', [MasterDataController::class, 'storeBuilding']);
            Route::put('/buildings/{building}', [MasterDataController::class, 'updateBuilding']);
            Route::delete('/buildings/{building}', [MasterDataController::class, 'deleteBuilding']);
            Route::post('/floors', [MasterDataController::class, 'storeFloor']);
            Route::put('/floors/{floor}', [MasterDataController::class, 'updateFloor']);
            Route::post('/areas', [MasterDataController::class, 'storeArea']);
            Route::put('/areas/{area}', [MasterDataController::class, 'updateArea']);
            Route::post('/cleaning-objects', [MasterDataController::class, 'storeCleaningObject']);
            Route::post('/shifts', [MasterDataController::class, 'storeShift']);
            Route::post('/schedules', [MasterDataController::class, 'storeSchedule']);
            Route::delete('/schedules/{schedule}', [MasterDataController::class, 'deleteSchedule']);

            // QR Codes
            Route::get('/qr-codes', [QrCodeController::class, 'index']);
            Route::post('/qr-codes/generate/{area}', [QrCodeController::class, 'generate']);
            Route::post('/qr-codes/{qrCode}/regenerate', [QrCodeController::class, 'regenerate']);
            Route::post('/qr-codes/generate-all', [QrCodeController::class, 'generateAll']);
        });
    });
});
