<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreResolvedTicketRequest;
use App\Http\Requests\UpdateResolvedTicketRequest;
use App\Http\Resources\ResolvedTicketResource;
use App\Models\ResolvedTicket;
use App\Traits\TicketTrait;
use App\Traits\UserTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ResolvedTicketController extends Controller
{
  /**
   * Display a listing of the resource.
   */
  public function index()
  {
    $search = $this->prepare(request('search'));
    $limit = intval($this->prepare(request('limit')));
    $user_id = Auth::user()->id;

    $id = empty($search) ? 0 : intval(trim(preg_replace('/[^0-9]+/', '', $search)));
    $name = empty($search) ? null : mb_strtolower(trim(preg_replace('/[^А-яA-z ]+/iu', '', $search)));

    // DB::enableQueryLog();
    $data = DB::table('resolved_tickets')
      ->join('reasons', 'reasons.id', 'resolved_tickets.reason_id')
      ->leftJoin('users AS u', 'u.id', 'resolved_tickets.new_user_id')
      ->leftJoin('users AS m', 'm.id', 'resolved_tickets.new_manager_id')
      ->leftJoin('bx_crms AS bxc', 'bxc.id', 'resolved_tickets.crm_id')
      ->leftJoin(
        'participants',
        fn($q) => $q->on('participants.ticket_id', 'resolved_tickets.id')
          ->where('participants.user_id', $user_id)
      )
      ->where(
        fn($q) => $q->where('resolved_tickets.new_manager_id', $user_id)
          ->orWhere('resolved_tickets.new_user_id', $user_id)
        // ->orWhere('participants.user_id', $user_id)
      )
      ->when($id > 0, fn($q) => $q->where('resolved_tickets.old_ticket_id', $id))
      ->when(
        isset($name) && $id == 0,
        fn($q) => $q->where(
          fn($r) => $r->whereRaw(
            "LOWER(u.name) LIKE ? OR LOWER(m.name) LIKE ?",
            ["%{$name}%", "%{$name}%"]
          )
        )
      )
      ->whereNotNull('resolved_tickets.id')
      ->select(
        'resolved_tickets.*',
        'reasons.name AS reason',
        'bxc.name AS bx_name',
        'bxc.acronym AS bx_acronym',
        'bxc.domain AS bx_domain',
      )
      ->orderByDesc('resolved_tickets.created_at')
      ->orderByDesc('resolved_tickets.id')
      ->paginate($limit < 1 ? 100 : $limit);

    // dd(DB::getQueryLog());
    $search = UserTrait::search();
    $users_collection = array();

    foreach ($search->data as $user) {
      $users_collection[$user->email] = $user;
    }
    unset($search);

    $all_ids = array_merge(...array_map(fn($t) => [$t->new_user_id, $t->new_manager_id], $data->all()));
    $users_with_emails = DB::table('users')
      ->leftJoin('bx_users', 'bx_users.user_id', 'users.id')
      ->whereIn('users.id', array_values(array_unique($all_ids)))
      ->selectRaw('users.id, users.email, IFNULL(bx_users.crm_id, users.crm_id) AS crm_id')
      ->get();
    unset($all_ids);

    foreach ($data as $ticket) {
      $u = $users_with_emails->where('id', $ticket->new_user_id)->first();
      $m = $users_with_emails->where('id', $ticket->new_manager_id)->first();
      $ticket->user_crm_id = $u->crm_id;
      $ticket->user = $users_collection[@$u->email]
        ?? UserTrait::tryToDefineUserEverywhere($u->crm_id, $u->email);
      $ticket->manager_crm_id = $m->crm_id;
      $ticket->manager = $users_collection[@$m->email]
        ?? UserTrait::tryToDefineUserEverywhere($m->crm_id, $m->email);
    }

    return response()->json([
      'status' => true,
      'data' => ResolvedTicketResource::collection($data)->response()->getData()
    ]);
  }

  /**
   * Store a newly created resource in storage.
   */
  public function store(StoreResolvedTicketRequest $request)
  {
    $val = $request->validated();
    $data = TicketTrait::FinishTicket($val['old_ticket_id'], $val['mark']);
    return response()->json($data);
  }

  /**
   * Display the specified resource.
   */
  public function show(string $id)
  {
    $data = ResolvedTicket::join('reasons', 'reasons.id', 'resolved_tickets.reason_id')
      ->select('resolved_tickets.*', 'reasons.name AS reason')
      ->firstWhere('old_ticket_id', $id);

    if (!isset($data)) {
      return response()->json([
        'status' => false,
        'data' => $data,
        'message' => 'Завершённый тикет не найден'
      ]);
    }

    $users_with_emails = DB::table('users')
      ->leftJoin('bx_users', 'bx_users.user_id', 'users.id')
      ->whereIn('users.id', [$data->new_user_id, $data->new_manager_id])
      ->selectRaw('users.id, users.email, IFNULL(bx_users.crm_id, users.crm_id) AS crm_id')
      ->get();
    $u = $users_with_emails->where('id', $data->new_user_id)->first();
    $m = $users_with_emails->where('id', $data->new_manager_id)->first();

    $data->user_crm_id = $u->crm_id;
    $data->user_crm_id = $m->crm_id;
    $data->user = UserTrait::tryToDefineUserEverywhere($u->crm_id, $u->email);
    $data->manager = UserTrait::tryToDefineUserEverywhere($m->crm_id, $m->email);

    return response()->json([
      'status' => true,
      'data' => $data
    ]);
  }

  /**
   * Update the specified resource in storage.
   */
  public function update(UpdateResolvedTicketRequest $request, $id)
  {
    $validated = $request->validated();
    $rt = ResolvedTicket::join('reasons', 'reasons.id', 'resolved_tickets.reason_id')
      ->where('resolved_tickets.id', $id)
      ->select('resolved_tickets.*', 'reasons.name AS reason')->first();

    if (!isset($rt)) {
      return response()->json([
        'status' => false,
        'data' => $rt,
        'message' => 'Тикет не найден'
      ]);
    }

    $rt->reason_id = $validated['reason_id'];
    $rt->save();

    return response()->json([
      'status' => true,
      'data' => ResolvedTicketResource::make($rt),
      'message' => 'Завершённый тикет успешно изменён',
    ]);
  }

  /**
   * Remove the specified resource from storage.
   */
  public function destroy(string $id)
  {
    //
  }
}