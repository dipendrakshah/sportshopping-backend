<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\CartController;
use App\Http\Controllers\Api\OrderController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
*/

// Auth
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

// Protected Routes
Route::group(['middleware' => ['auth:sanctum']], function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::post('/logout-all', [AuthController::class, 'logoutAll']);
    Route::get('/user', function (Request $request) {
        return $request->user();
    });
    
    // Products
    Route::get('/products/search', [ProductController::class, 'search']); // Specific route first
    Route::get('/products', [ProductController::class, 'index']);
    Route::get('/products/{id}', [ProductController::class, 'show']);
    Route::post('/products', [ProductController::class, 'store']);

    // Cart
    Route::get('/cart', [CartController::class, 'viewCart']);
    Route::post('/cart', [CartController::class, 'addToCart']);
    Route::delete('/cart/{itemId}', [CartController::class, 'removeFromCart']);

    // Order
    Route::post('/orders', [OrderController::class, 'placeOrder']);
    Route::post('/orders/{id}/cancel', [OrderController::class, 'cancel']);

    // Refresh Token
    Route::post('/refresh-token', [AuthController::class, 'refreshToken']);

    // Admin Routes
    Route::middleware(['auth:sanctum', 'is_admin'])->group(function () {
        Route::post('/admin/orders/{id}/refund', [OrderController::class, 'refund']);
    });
});
