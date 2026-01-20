<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BookingController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\ProviderController;
use App\Http\Controllers\Api\ReviewController;
use App\Http\Controllers\Api\ServiceController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Booking System REST API
| Base URL: /api
|
*/

// Auth
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

// Public routes
Route::get('/categories', [CategoryController::class, 'index']);
Route::get('/categories/{category:slug}', [CategoryController::class, 'show']);

Route::get('/services', [ServiceController::class, 'index']);
Route::get('/services/{service:slug}', [ServiceController::class, 'show']);

Route::get('/providers', [ProviderController::class, 'index']);
Route::get('/providers/{provider}', [ProviderController::class, 'show']);
Route::get('/providers/{provider}/slots', [ProviderController::class, 'availableSlots']);
Route::get('/providers/{provider}/reviews', [ReviewController::class, 'providerReviews']);

// Stripe webhook (no auth)
Route::post('/webhooks/stripe', [PaymentController::class, 'webhook']);

// Authenticated routes
Route::middleware('auth:sanctum')->group(function () {
    // Auth
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/user', [AuthController::class, 'user']);

    // Bookings
    Route::get('/bookings', [BookingController::class, 'index']);
    Route::post('/bookings', [BookingController::class, 'store']);
    Route::get('/bookings/{booking}', [BookingController::class, 'show']);
    Route::post('/bookings/{booking}/cancel', [BookingController::class, 'cancel']);

    // Payments
    Route::post('/bookings/{booking}/pay', [PaymentController::class, 'createIntent']);

    // Reviews
    Route::post('/reviews', [ReviewController::class, 'store']);
});
