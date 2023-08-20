<?php

namespace App\Http\Controllers;

use App\Http\Resources\DetalizationResource;
use App\Traits\UserTrait;
use Illuminate\Support\Facades\DB;

class DetalizationController extends Controller
{
  /**
   * Display a listing of the resource.
   */
  public function index()
  {
    $search = htmlspecialchars(trim(request('search')));
    $limit = intval(htmlspecialchars(trim(request('limit'))));
    $user_id = intval(htmlspecialchars(trim(request('user_id'))));
    $from_id = intval(htmlspecialchars(trim(request('from_id'))));
    $to_id = intval(htmlspecialchars(trim(request('to_id'))));
    // $active = htmlspecialchars(trim(request('active')));

    $tickets_ids = [$from_id < 1 ? 1 : $from_id, $to_id < 1 ? 1 : $to_id];
    // $is_active = boolval($active);

    // $auth_crm_id = \Illuminate\Support\Facades\Auth::user()->crm_id;

    $resolved_tickets = \App\Models\ResolvedTicket::filter($user_id, $tickets_ids, $search)
      ->selectRaw('resolved_tickets.user_id, resolved_tickets.manager_id,
        resolved_tickets.old_ticket_id AS tid, resolved_tickets.mark AS mark,
        NULL AS active, resolved_tickets.weight, resolved_tickets.created_at,
        reasons.id AS reason_id, reasons.name AS reason, NULL AS user, NULL AS manager');

    $data = \App\Models\Ticket::filter($user_id, $tickets_ids, $search)
      ->union($resolved_tickets)
      ->selectRaw('tickets.user_id, tickets.manager_id, tickets.id AS tid, NULL AS mark,
        tickets.active AS active, tickets.weight, tickets.created_at,
        reasons.id AS reason_id, reasons.name AS reason, NULL AS user, NULL AS manager')
      ->orderByDesc('tid')
      ->paginate($limit < 1 ? 100 : $limit);
      dd($data);
    $users_collection = array();

    foreach (UserTrait::search()->data as $user) {
      $users_collection[$user->crm_id] = $user;
    }

    foreach ($data as $ticket) {
      $ticket->user = $users_collection[$ticket->user_id];
      $ticket->manager = $users_collection[$ticket->manager_id];
    }
    unset($users_collection);

    return response()->json([
      'status' => true,
      'data' => DetalizationResource::collection($data)->response()->getData(),
    ]);
  }
}