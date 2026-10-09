<?php

use App\Http\Controllers\AppointmentController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CatalogController;
use App\Http\Resources\UserResource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->middleware('throttle:api')->group(function () {
    Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:registration');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:login');
    Route::get('/doctors', [CatalogController::class, 'doctors']);
    Route::get('/services', [CatalogController::class, 'services']);
    Route::get('/doctors/{doctor}/availability', [CatalogController::class, 'availability']);
    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/user', fn (Request $r) => new UserResource($r->user()));
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::get('/appointments', [AppointmentController::class, 'index']);
        Route::post('/appointments', [AppointmentController::class, 'store']);
        Route::get('/appointments/{appointment}', [AppointmentController::class, 'show']);
        Route::patch('/appointments/{appointment}/reschedule', [AppointmentController::class, 'reschedule']);
        Route::post('/appointments/{appointment}/cancel', [AppointmentController::class, 'cancel']);
    });
});
