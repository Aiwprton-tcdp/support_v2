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
    $order_by_time = $this->prepare(request('order_by_time'));

    // DB::enableQueryLog();
    $tickets_ids = range($min_id, $max_id <= $min_id ? $min_id + 1 : $max_id);
    $weights = range($min_w, $max_w <= $min_w ? $min_w + 1 : $max_w);
    $from_date = date("Y-m-d", strtotime($from_date));
    $to_date = date("Y-m-d", strtotime($to_date));
    $dates = [$from_date, $to_date <= $from_date ? $from_date : $to_date];

    $resolved_tickets = \App\Models\ResolvedTicket::leftJoin('bx_crms', 'bx_crms.id', 'crm_id')
      ->filter($user_id, $tickets_ids, $weights, $users, $reasons, $dates, $search)
      ->selectRaw('resolved_tickets.new_user_id, resolved_tickets.new_manager_id,
        resolved_tickets.old_ticket_id AS tid, resolved_tickets.mark AS mark,
        NULL AS active, resolved_tickets.weight, resolved_tickets.crm_id AS crm_id, resolved_tickets.created_at,
        reasons.id AS reason_id, reasons.name AS reason, NULL AS user, NULL AS manager,
        messages.created_at AS start_date,
        TIMEDIFF(IFNULL(hidden_chat_messages.created_at, NOW()), messages.created_at) AS time,
        bx_crms.name AS bx_name, bx_crms.acronym AS bx_acronym');

    $data = \App\Models\Ticket::leftJoin('bx_crms', 'bx_crms.id', 'crm_id')
      ->filter($user_id, $tickets_ids, $weights, $users, $reasons, $dates, $active, $inactive, $search)
      ->selectRaw('tickets.new_user_id, tickets.new_manager_id, tickets.id AS tid, NULL AS mark,
        tickets.active AS active, tickets.weight, tickets.crm_id AS crm_id, tickets.created_at,
        reasons.id AS reason_id, reasons.name AS reason, NULL AS user, NULL AS manager,
        messages.created_at AS start_date,
        TIMEDIFF(IFNULL(hidden_chat_messages.created_at, NOW()), messages.created_at) AS time,
        bx_crms.name AS bx_name, bx_crms.acronym AS bx_acronym')
      ->when($resolved, fn($q) => $q->union($resolved_tickets))
      ->when(isset($order_by_time) && !empty($order_by_time), fn($q) =>
        $q->orderBy('time', filter_var($order_by_time, FILTER_VALIDATE_BOOLEAN) ? 'ASC' : 'DESC'))
      ->orderByDesc('tid')
      // ->paginate();
      ->paginate($limit < 1 ? 100 : $limit);

    // dd(DB::getQueryLog());
    $users_collection = array();
    $search = UserTrait::search()->data;

    $users_by_emails = DB::table('users')
      ->whereIn('users.email', array_map(fn($d) => $d->email, $search))
      ->pluck('users.id', 'users.email');
    foreach ($search as $user) {
      if (!isset($users_by_emails[$user->email]))
        continue;
      $user->user_id = $users_by_emails[$user->email];
      $users_collection[$user->user_id] = $user;
    }
    unset($search);

    foreach ($data as $key => $ticket) {
      if (!isset($ticket->tid)) {
        unset($data[$key]);
        continue;
      }
      $ticket->user = $users_collection[$ticket->new_user_id]
        ?? ['name' => 'Неопределённый пользователь'];
      $ticket->manager = $users_collection[$ticket->new_manager_id]
        ?? ['name' => 'Неопределённый менеджер'];
    }
    unset($users_collection);

    return response()->json([
      'status' => true,
      'data' => DetalizationResource::collection($data)->response()->getData(),
    ]);
  }
}