<?php

declare(strict_types=1);

use App\Http\Controllers\AuthController;
use App\Http\Controllers\AvailabilityController;
use App\Http\Controllers\CabinController;
use App\Http\Controllers\ClientController;
use App\Http\Controllers\DailySummaryController;
use App\Http\Controllers\FeatureController;
use App\Http\Controllers\PriceGroupController;
use App\Http\Controllers\PriceRangeController;
use App\Http\Controllers\ReservationController;
use App\Http\Controllers\UserController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

// API v1 Routes
Route::prefix('v1')->group(function () {
    // Rutas de autenticación
    Route::post('/auth', [AuthController::class, 'store'])->name('auth.store');
    Route::get('/auth', [AuthController::class, 'show'])->middleware('auth:sanctum')->name('auth.show');
    Route::delete('/auth', [AuthController::class, 'destroy'])->middleware('auth:sanctum')->name('auth.destroy');

    // Rutas de perfil de usuario
    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/users/profile', [UserController::class, 'profile'])->name('users.profile');
        Route::put('/users/profile', [UserController::class, 'updateProfile'])->name('users.profile.update');
        Route::put('/users/password', [UserController::class, 'updatePassword'])->name('users.password.update');
    });

    // ========================================
    // SISTEMA DE GESTIÓN DE CABAÑAS
    // ========================================
    Route::middleware('auth:sanctum')->group(function () {

        // Clientes
        Route::apiResource('clients', ClientController::class);
        Route::get('clients/dni/{dni}', [ClientController::class, 'searchByDni'])->name('clients.search-by-dni');

        // Cabañas y Características
        Route::apiResource('features', FeatureController::class);
        Route::apiResource('cabins', CabinController::class);

        // Tarifas
        Route::apiResource('price-groups', PriceGroupController::class);
        Route::apiResource('price-ranges', PriceRangeController::class);

        // Reservas
        Route::post('reservations/quote', [ReservationController::class, 'quote'])->name('reservations.quote');
        Route::apiResource('reservations', ReservationController::class);
        Route::post('reservations/{reservation}/confirm', [ReservationController::class, 'confirm'])->name('reservations.confirm');
        Route::post('reservations/{reservation}/check-in', [ReservationController::class, 'checkIn'])->name('reservations.check-in');
        Route::post('reservations/{reservation}/check-out', [ReservationController::class, 'checkOut'])->name('reservations.check-out');
        Route::post('reservations/{reservation}/cancel', [ReservationController::class, 'cancel'])->name('reservations.cancel');

        // Disponibilidad y Calendario
        Route::get('availability/calendar', [AvailabilityController::class, 'calendar'])->name('availability.calendar');
        Route::get('availability/{cabin_id}', [AvailabilityController::class, 'show'])->name('availability.show');
        Route::get('availability', [AvailabilityController::class, 'check'])->name('availability.check');

        // Resumen Diario
        Route::get('daily-summary', [DailySummaryController::class, 'index'])->name('daily-summary.index');
    });
});
