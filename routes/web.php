<?php

use App\Http\Controllers\AppointmentController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CatalogController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ManagementController;
use Illuminate\Support\Facades\Route;

Route::view('/', 'home')->name('home');
Route::get('/doctors', [CatalogController::class, 'doctors'])->name('doctors');
Route::get('/services', [CatalogController::class, 'services'])->name('services');
Route::get('/doctors/{doctor}/availability', [CatalogController::class, 'availability'])->middleware('throttle:60,1')->name('availability');
Route::middleware('guest')->group(function () {
    Route::get('/login', fn () => app(AuthController::class)->form('login'))->name('login');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:login');
    Route::get('/register', fn () => app(AuthController::class)->form('register'))->name('register');
    Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:registration');
    Route::get('/forgot-password', fn () => app(AuthController::class)->form('forgot-password'))->name('password.request');
    Route::post('/forgot-password', [AuthController::class, 'forgot'])->middleware('throttle:password-reset')->name('password.email');
    Route::get('/reset-password/{token}', [AuthController::class, 'resetForm'])->name('password.reset');
    Route::post('/reset-password', [AuthController::class, 'reset'])->middleware('throttle:password-reset')->name('password.update');
});
Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
    Route::get('/dashboard', DashboardController::class)->name('dashboard');
    Route::get('/profile', [AuthController::class, 'profile'])->name('profile');
    Route::patch('/profile', [AuthController::class, 'updateProfile'])->name('profile.update');
    Route::put('/profile/password', [AuthController::class, 'changePassword'])->name('profile.password');
    Route::get('/appointments', [AppointmentController::class, 'index'])->name('appointments.index');
    Route::get('/appointments/create', [AppointmentController::class, 'create'])->name('appointments.create');
    Route::post('/appointments', [AppointmentController::class, 'store'])->name('appointments.store');
    Route::get('/appointments/{appointment}', [AppointmentController::class, 'show'])->name('appointments.show');
    Route::get('/appointments/{appointment}/edit', [AppointmentController::class, 'edit'])->name('appointments.edit');
    Route::patch('/appointments/{appointment}/reschedule', [AppointmentController::class, 'reschedule'])->name('appointments.reschedule');
    Route::post('/appointments/{appointment}/cancel', [AppointmentController::class, 'cancel'])->name('appointments.cancel');
    Route::patch('/appointments/{appointment}/status', [AppointmentController::class, 'status'])->name('appointments.status');
    Route::prefix('admin')->middleware('role:admin')->group(function () {
        Route::get('/{kind}', [ManagementController::class, 'index'])->name('admin.index');
        Route::get('/{kind}/create', [ManagementController::class, 'form'])->name('admin.create');
        Route::get('/{kind}/{id}/edit', [ManagementController::class, 'form'])->name('admin.edit');
        Route::post('/{kind}', [ManagementController::class, 'save'])->name('admin.store');
        Route::put('/{kind}/{id}', [ManagementController::class, 'save'])->name('admin.update');
        Route::delete('/{kind}/{id}',[ManagementController::class, 'destroy'])->name('admin.destroy');
    });
});
