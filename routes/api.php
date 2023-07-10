<?php

use App\Http\Controllers\InstallController;
use App\Http\Controllers\ManagerGroupController;
use App\Http\Controllers\ParticipantController;
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

Route::post('/install', [InstallController::class, 'install']);

// Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
//     return $request->user();
// });

Route::prefix('bx')->group(function () {
    Route::get('/users', [\App\Http\Controllers\CRM\UserController::class, 'search']);
});

Route::apiResources([
    'tickets' => \App\Http\Controllers\TicketController::class,
    'reasons' => \App\Http\Controllers\ReasonController::class,
    'roles' => \App\Http\Controllers\RoleController::class,
    'groups' => \App\Http\Controllers\GroupController::class,
    'messages' => \App\Http\Controllers\MessageController::class,
    'users' => \App\Http\Controllers\UserController::class,
    'template_messages' => \App\Http\Controllers\TemplateMessageController::class,
], [
    'except' => 'show'
]);

Route::apiResource('manager_groups', ManagerGroupController::class)->only([
    'index', 'store', 'destroy'
]);

Route::apiResource('participants', ParticipantController::class)->only([
    'store'
]);