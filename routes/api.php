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

    Route::get('/stillalive', function () {
        return response()->json([
            'message' => 'but barely breathing',
            'status' => 'alive',
            'timestamp' => now()->toISOString()
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

        Route::get('/alltickets', [TicketController::class, 'allTickets']);
        Route::get('/ticket/{id}', [TicketController::class, 'showTicket']);
        Route::post('/ticket', [TicketController::class, 'createTicket']);
        Route::post('/ticket/discussion/{id}', [TicketController::class, 'createDiscussion']);
        Route::get('/categories', [TicketController::class, 'getCategories']);
        Route::put('/ticket/{id}', [TicketController::class, 'updateTicket']);
        Route::delete('/ticket/{id}', [TicketController::class, 'deleteTicket']);

        Route::get('/admin/deleted-tickets', [TicketController::class, 'deletedTickets']); // Solo admin
        Route::post('/admin/restore-ticket/{id}', [TicketController::class, 'restoreTicket']); // Solo admin


        Route::post('/ticket/{id}/close', [TicketController::class, 'closeTicket']); // 👈 NUOVA
        Route::post('/ticket/{id}/reopen', [TicketController::class, 'reopenTicket']); // 👈 NUOVA
        Route::patch('/ticket/{id}/status', [TicketController::class, 'changeTicketStatus']); // 👈 NUOVA

        Route::get('/users/assignable', [TicketController::class, 'getAssignableUsers']);

    });
});
