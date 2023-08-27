<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreResolvedTicketRequest;
use App\Http\Resources\ResolvedTicketResource;
use App\Jobs\TicketClosingJob;
use App\Models\ResolvedTicket;
use App\Traits\TicketTrait;
use App\Traits\UserTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ResolvedTicketController extends Controller
{
  /**
   * Display a listing of the resource.
   */
  public function index()
  {
    $search = htmlspecialchars(trim(request('search')));
    $limit = intval(htmlspecialchars(trim(request('limit'))));
    $user_crm_id = Auth::user()->crm_id;

    $data = DB::table('resolved_tickets')
      ->join('reasons', 'reasons.id', 'resolved_tickets.reason_id')
      ->rightJoin('users AS u', 'u.crm_id', 'resolved_tickets.manager_id')
      ->rightJoin('users AS m', 'm.crm_id', 'resolved_tickets.manager_id')
      ->leftJoin('participants', function ($q) use ($user_crm_id) {
        $q->on('participants.ticket_id', 'resolved_tickets.old_ticket_id')
          ->where('participants.user_crm_id', $user_crm_id);
      })
      ->where(function ($q) use ($user_crm_id) {
        $q->whereManagerId($user_crm_id)
          ->orWhere('user_id', $user_crm_id)
          ->orWhere('participants.user_crm_id', $user_crm_id);
      })
      ->when(!empty($search), function ($q) use ($search) {
        $id = intval(trim(preg_replace('/[^0-9]+/', '', $search)));
        $name = mb_strtolower(trim(preg_replace('/[^А-яA-z ]+/iu', '', $search)));

        $q->when($id > 0, fn($r) => $r->where('resolved_tickets.old_ticket_id', $id))
          ->when(!empty($name), function ($y) use ($name) {
            $y->orWhereRaw('LOWER(u.name) LIKE ?', ["%{$name}%"])
              ->orWhereRaw('LOWER(m.name) LIKE ?', ["%{$name}%"]);
          });
      })
      ->select('resolved_tickets.*', 'reasons.name AS reason')
      ->orderBy('resolved_tickets.updated_at')
      ->paginate($limit < 1 ? 100 : $limit);

    $search = UserTrait::search();
    $users_collection = array();

    foreach ($search->data as $user) {
      $users_collection[$user->crm_id] = $user;
    }
    unset($search);

    foreach ($data as $ticket) {
      $ticket->user = $users_collection[$ticket->user_id]
        ?? ['name' => 'Удалённый пользователь'];
      $ticket->manager = $users_collection[$ticket->manager_id];
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
    Log::info('StoreResolvedTicket: ' . $data['message']);
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

    $data->user = UserTrait::tryToDefineUserEverywhere($data->user_id);
    $data->manager = UserTrait::tryToDefineUserEverywhere($data->manager_id);

    return response()->json([
      'status' => true,
      'data' => $data
    ]);
  }

  /**
   * Update the specified resource in storage.
   */
  public function update(Request $request, string $id)
  {
    //
  }

  /**
   * Remove the specified resource from storage.
   */
  public function destroy(string $id)
  {
    //
  }
}