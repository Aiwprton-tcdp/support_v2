<?php

use App\Http\Controllers\CRM\DepartmentController as CRMDepartmentController;
use App\Http\Controllers\CRM\IndexAPIController;
use App\Http\Controllers\CRM\UserController as CRMUserController;
use App\Http\Controllers\CRM\InstallController;
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
// Route::get('/index', [IndexAPIController::class, '__invoke']);
Route::post('/index', [IndexAPIController::class, '__invoke']);
Route::post('/auth/check', [CRMUserController::class, 'check']);

Route::group(['middleware' => 'auth:sanctum'], function() {

    Route::prefix('bx')->group(function () {
        Route::get('/users', [CRMUserController::class, 'search']);
        Route::get('/departments', [CRMDepartmentController::class, 'search']);
    });

    Route::apiResources([
        'reasons' => \App\Http\Controllers\ReasonController::class,
        'groups' => \App\Http\Controllers\GroupController::class,
        'messages' => \App\Http\Controllers\MessageController::class,
        'users' => \App\Http\Controllers\UserController::class,
        'managers' => \App\Http\Controllers\ManagerController::class,
        'template_messages' => \App\Http\Controllers\TemplateMessageController::class,
    ], [
        'except' => 'show'
    ]);

    Route::apiResource('tickets', \App\Http\Controllers\TicketController::class)->only([
        'index', 'store', 'update'
    ]);

    Route::apiResource('manager_groups', ManagerGroupController::class)->only([
        'index', 'store', 'destroy'
    ]);

    Route::apiResource('participants', ParticipantController::class)->only([
        'store'
    ]);

    Route::get('/roles', [\App\Http\Controllers\RoleController::class, 'index']);
});
