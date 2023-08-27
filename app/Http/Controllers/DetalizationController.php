<?php

namespace App\Http\Controllers;

use App\Http\Resources\DetalizationResource;
use App\Traits\UserTrait;

class DetalizationController extends Controller
{
  /**
   * Display a listing of the resource.
   */
  public function index()
  {
    $search = $this->prepare(request('search'));
    $limit = intval($this->prepare(request('limit')));
    $limit = intval($this->prepare(request('limit')));
    $user_id = intval($this->prepare(request('user_id')));
    $min_id = intval($this->prepare(request('min_id')));
    $max_id = intval($this->prepare(request('max_id')));
    $min_w = intval($this->prepare(request('min_w')));
    $max_w = intval($this->prepare(request('max_w')));
    $active = filter_var($this->prepare(request('active')), FILTER_VALIDATE_BOOLEAN);
    $inactive = filter_var($this->prepare(request('inactive')), FILTER_VALIDATE_BOOLEAN);
    $resolved = filter_var($this->prepare(request('resolved')), FILTER_VALIDATE_BOOLEAN);
    $users = explode(',', $this->prepare(request('users')));
    $reasons = explode(',', $this->prepare(request('reasons')));
    $from_date = $this->prepare(request('from_date'));
    $to_date = $this->prepare(request('to_date'));
    
    $tickets_ids = range($min_id, $max_id <= $min_id ? $min_id + 1 : $max_id);
    $weights = range($min_w, $max_w <= $min_w ? $min_w + 1 : $max_w);
    $from_date = date("Y-m-d", strtotime($from_date));
    $to_date = date("Y-m-d", strtotime($to_date));
    $dates = [$from_date, $to_date <= $from_date ? $from_date : $to_date];

    $resolved_tickets = \App\Models\ResolvedTicket::filter($user_id, $tickets_ids, $weights, $users, $reasons, $dates, $search)
      ->selectRaw('resolved_tickets.user_id, resolved_tickets.manager_id,
        resolved_tickets.old_ticket_id AS tid, resolved_tickets.mark AS mark,
        NULL AS active, resolved_tickets.weight, resolved_tickets.created_at,
        reasons.id AS reason_id, reasons.name AS reason, NULL AS user, NULL AS manager,
        messages.created_at AS start_date,
        TIMEDIFF(hidden_chat_messages.created_at, messages.created_at) AS time');

    $data = \App\Models\Ticket::filter($user_id, $tickets_ids, $weights, $users, $reasons, $dates, $active, $inactive, $search)
      ->selectRaw('tickets.user_id, tickets.manager_id, tickets.id AS tid, NULL AS mark,
        tickets.active AS active, tickets.weight, tickets.created_at,
        reasons.id AS reason_id, reasons.name AS reason, NULL AS user, NULL AS manager,
        messages.created_at AS start_date,
        TIMEDIFF(IFNULL(hidden_chat_messages.created_at, NOW()), messages.created_at) AS time')
      ->when($resolved, fn($q) => $q->union($resolved_tickets))
      ->orderByDesc('tid')
      // ->paginate();
      ->paginate($limit < 1 ? 100 : $limit);

    $users_collection = array();

    foreach (UserTrait::search()->data as $user) {
      $users_collection[$user->crm_id] = $user;
    }

    foreach ($data as $key => $ticket) {
      if (!isset($ticket->tid)) {
        unset($data[$key]);
        continue;
      }
      $ticket->user = $users_collection[$ticket->user_id]
        ?? ['name' => 'Удалённый пользователь'];
      $ticket->manager = $users_collection[$ticket->manager_id];
    }
    unset($users_collection);

    return response()->json([
      'status' => true,
      'data' => DetalizationResource::collection($data)->response()->getData(),
    ]);
  }
}