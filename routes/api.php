<?php

use App\Http\Controllers\CRM\InstallController as CRMInstallController;
use App\Http\Controllers\CRM\IndexAPIController as CRMIndexController;
use App\Http\Controllers\CRM\DepartmentController as CRMDepartmentController;
use App\Http\Controllers\CRM\UserController as CRMUserController;
use App\Http\Controllers\AttachmentController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DetalizationController;
use App\Http\Controllers\HiddenChatMessageController;
use App\Http\Controllers\ManagerGroupController;
use App\Http\Controllers\ParticipantController;
use App\Http\Controllers\ResolvedTicketController;
use App\Http\Controllers\SocketController;
use App\Http\Controllers\TicketController;
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

Route::post('/install', [CRMInstallController::class, 'install']);
Route::post('/index', [CRMIndexController::class, '__invoke']);
Route::post('/auth/check', [CRMUserController::class, 'check']);
Route::get('/cache_reload', [DashboardController::class, 'cacheReload']);

Route::group(['middleware' => 'auth:sanctum'], function () {
  Route::prefix('bx')->group(function () {
    Route::get('/users', [CRMUserController::class, 'search']);
    Route::get('/departments', [CRMDepartmentController::class, 'search']);
  });

  Route::apiResource('tickets', TicketController::class);
  // ->except([
  //   'destroy'
  // ]);
  // Route::delete('/tickets/{id}/{mark}', [TicketController::class, 'destroy']);

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

  Route::apiResource('manager_groups', ManagerGroupController::class)->only([
    'index',
    'store',
    'destroy'
  ]);
  Route::apiResource('attachments', AttachmentController::class)->only([
    'index',
    'store',
    'update'
  ]);
  Route::apiResource('resolved_tickets', ResolvedTicketController::class)->only([
    'index',
    'store',
    'show'
  ]);
  Route::apiResources([
    'participants' => ParticipantController::class,
    'hidden_chat_messages' => HiddenChatMessageController::class,
  ], [
    'only' => ['index', 'store']
  ]);

  Route::get('/roles', [\App\Http\Controllers\RoleController::class, 'index']);

  // Route::post('send_message', [SocketController::class, 'MesageUpload']);

  Route::any('/websocket/subscribe', [SocketController::class, 'Subscribe']);
  Route::any('/websocket/refresh', [SocketController::class, 'Refresh']);

  Route::prefix('detalization')->group(function () {
    Route::get('/', [DetalizationController::class, 'index']);
  });
});

Route::prefix('statistics')->group(function () {
  Route::get('/active_tickets', [DashboardController::class, 'getActiveTickets']);
  Route::post('/redistribute', [DashboardController::class, 'redistribute']);
  Route::get('/cache_reload', [DashboardController::class, 'cacheReload']);

  Route::get('/tickets_by_reasons', [DashboardController::class, 'getTicketsCountByReasons']);
  Route::get('/tickets_by_groups', [DashboardController::class, 'getTicketsByGroups']);
  Route::get('/marks_percentage', [DashboardController::class, 'getMarksPercentage']);
  Route::get('/average_solving_time', [DashboardController::class, 'getAverageSolvingTime']);

  Route::get('/count_of_tickets_by_days', [DashboardController::class, 'getCountOfTicketsByDays']);
  Route::get('/count_of_tickets_by_managers', [DashboardController::class, 'getCountOfTicketsByManagers']);
  Route::get('/fastest_and_slowest_tickets', [DashboardController::class, 'getFastestAndSlowestTickets']);
  Route::get('/tickets_solving_time_median', [DashboardController::class, 'getTicketsSolvingTimeMedian']);
  Route::get('/avg_max_min_tickets_per_day', [DashboardController::class, 'GetAvgMaxMinTicketsPerDay']);
  Route::get('/avg_time_by_reasons', [DashboardController::class, 'GetAvgTimeByReason']);
  Route::get('/stats_by_reasons_and_managers_per_day', [DashboardController::class, 'GetStatsByReasonsAndManagersPerDay']);
});

Route::get('/set_users_ids', [CRMUserController::class, 'setUsersIds']);