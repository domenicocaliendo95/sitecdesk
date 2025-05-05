<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\TicketController;
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


Route::prefix('v1')->group(function () {
    Route::post('/login', [AuthController::class, 'login']);

    Route::get('/test', function () {
        return response()->json([
            'message' => 'Hello World',
        ]);
    });

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/me', [AuthController::class, 'me']);
        Route::post('/logout', [AuthController::class, 'logout']);

        // API dashboard
        Route::get('/dashboard', fn () => response()->json(['todo' => true])); // da fare

        // API utenti (solo per admin, logic to be handled in controller)
        Route::get('/users', [UserController::class, 'index']);
        Route::get('/users/{user}', fn () => response()->json(['todo' => true]));
        Route::post('/users', fn () => response()->json(['todo' => true]));
        Route::put('/users/{user}', fn () => response()->json(['todo' => true]));
        Route::delete('/users/{user}', fn () => response()->json(['todo' => true]));

        // API ticket
        Route::get('/tickets', [TicketController::class, 'index']);
        Route::get('/tickets/{ticket}', [TicketController::class, 'show']); // nuova rotta singolo ticket
        Route::post('/tickets/{ticket}/reply', fn () => response()->json(['todo' => true]));
    });
});
