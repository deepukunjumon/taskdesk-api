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
    Route::get('/users/me/assignable', [UserController::class, 'assignable']);
    Route::patch('/users/{user}/manager', [UserController::class, 'updateManager']);
    Route::patch('/users/{user}/status', [UserController::class, 'updateStatus']);
    Route::patch('/users/{user}/relieve', [UserController::class, 'relieve']);
    Route::patch('/users/{user}', [UserController::class, 'update']);

    Route::get('/work-items', [WorkItemController::class, 'index']);
    Route::post('/work-items', [WorkItemController::class, 'store']);
    Route::get('/work-items/stats', [WorkItemController::class, 'stats']);
    Route::get('/work-items/{workItem}', [WorkItemController::class, 'show']);
    Route::patch('/work-items/{workItem}', [WorkItemController::class, 'update']);
    Route::patch('/work-items/{workItem}/status', [WorkItemController::class, 'updateStatus']);
    Route::patch('/work-items/{workItem}/reassign', [WorkItemController::class, 'reassign']);
    Route::delete('/work-items/{workItem}', [WorkItemController::class, 'destroy']);

    Route::get('/departments', [DepartmentController::class, 'index']);
    Route::get('/departments/options', [DepartmentController::class, 'options']);
    Route::post('/departments', [DepartmentController::class, 'store']);
    Route::patch('/departments/{department}', [DepartmentController::class, 'update']);
    Route::patch('/departments/{department}/toggle-active', [DepartmentController::class, 'toggleActive']);
    Route::delete('/departments/{department}', [DepartmentController::class, 'destroy']);

    Route::get('/branches', [BranchController::class, 'index']);
    Route::post('/branches', [BranchController::class, 'store']);
    Route::patch('/branches/{branch}', [BranchController::class, 'update']);
    Route::patch('/branches/{branch}/toggle-active', [BranchController::class, 'toggleActive']);
    Route::delete('/branches/{branch}', [BranchController::class, 'destroy']);

    Route::get('/categories', [CategoryController::class, 'index']);
    Route::post('/categories', [CategoryController::class, 'store']);
    Route::patch('/categories/{category}', [CategoryController::class, 'update']);
    Route::patch('/categories/{category}/toggle-active', [CategoryController::class, 'toggleActive']);
    Route::delete('/categories/{category}', [CategoryController::class, 'destroy']);

    Route::get('/sla-settings', [SlaSettingController::class, 'index']);
    Route::patch('/sla-settings/{slaSetting}', [SlaSettingController::class, 'update']);
});
