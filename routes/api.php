<?php

use App\Http\Controllers\Api\Auth\AuthController;
use App\Http\Controllers\Api\BranchController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\DepartmentController;
use App\Http\Controllers\Api\SlaSettingController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\WorkItemController;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

Route::get('/health', function () {
    try {
        DB::connection()->getPdo();
        $database = 'ok';
    } catch (\Throwable $e) {
        $database = 'unreachable';
    }

    return response()->json([
        'status' => $database === 'ok' ? 'ok' : 'degraded',
        'database' => $database,
        'timestamp' => now()->toIso8601String(),
    ], $database === 'ok' ? 200 : 503);
});

Route::post('/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);

    Route::get('/users', [UserController::class, 'index']);

    Route::get('/work-items', [WorkItemController::class, 'index']);
    Route::post('/work-items', [WorkItemController::class, 'store']);
    Route::get('/work-items/stats', [WorkItemController::class, 'stats']);
    Route::get('/work-items/{workItem}', [WorkItemController::class, 'show']);
    Route::patch('/work-items/{workItem}', [WorkItemController::class, 'update']);
    Route::patch('/work-items/{workItem}/status', [WorkItemController::class, 'updateStatus']);
    Route::patch('/work-items/{workItem}/reassign', [WorkItemController::class, 'reassign']);
    Route::delete('/work-items/{workItem}', [WorkItemController::class, 'destroy']);

    Route::get('/departments', [DepartmentController::class, 'index']);
    Route::post('/departments', [DepartmentController::class, 'store']);

    Route::get('/branches', [BranchController::class, 'index']);
    Route::post('/branches', [BranchController::class, 'store']);

    Route::get('/categories', [CategoryController::class, 'index']);
    Route::post('/categories', [CategoryController::class, 'store']);

    Route::get('/sla-settings', [SlaSettingController::class, 'index']);
    Route::patch('/sla-settings/{slaSetting}', [SlaSettingController::class, 'update']);
});
