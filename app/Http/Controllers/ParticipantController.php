<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreParticipantRequest;
use App\Models\HiddenChatMessage;
use App\Models\Participant;
use App\Models\Ticket;
use App\Traits\TicketTrait;
use App\Traits\UserTrait;
use Illuminate\Support\Facades\DB;

class ParticipantController extends Controller
{
  /**
   * Display a listing of the resource.
   */
  public function index()
  {
    $ticket_id = intval($this->prepare(request('ticket_id')));

    $data = DB::table('participants')
      ->join('users', 'users.id', 'participants.user_id')
      ->where('ticket_id', $ticket_id)->get();

    $search = UserTrait::search();
    $users_collection = array();
    foreach ($search->data as $user) {
      $users_collection[$user->email] = $user;
    }
    unset($search);

    $all_ids = array_values(array_unique(array_map(fn($t) => $t->user_id, $data->all())));
    $users_with_emails = DB::table('users')
      ->leftJoin('bx_users', 'bx_users.user_id', 'users.id')
      ->whereIn('users.id', $all_ids)
      ->selectRaw('users.id, users.email, IFNULL(bx_users.crm_id, users.crm_id) AS crm_id')
      ->get();

    foreach ($data as $key => $participants) {
      $p = $users_with_emails->where('id', $participants->user_id)->first();
      $data[$key] = (object) array_merge(
        (array) $participants,
        (array) @$users_collection[@$p->email] ?? UserTrait::tryToDefineUserEverywhere($p->crm_id, $p->email)
      );
    }
    // unset($users_collection);

    return response()->json([
      'status' => true,
      'data' => $data,
    ]);
  }

  /**
   * Store a newly created resource in storage.
   */
  public function store(StoreParticipantRequest $request)
  {
    $validated = $request->validated();
    $result = UserTrait::changeTheResponsive($validated['ticket_id'], $validated['user_id']);

    return response()->json([
      'status' => true,
      'data' => $result,
      'message' => 'Участник успешно добавлен'
    ]);
  }

  /**
   * Display the specified resource.
   */
  public function show($id)
  {
    //
  }

  /**
   * Update the specified resource in storage.
   */
  public function update(Request $request, $id)
  {
    //
  }

  /**
   * Remove the specified resource from storage.
   */
  public function destroy($id)
  {
    //
  }
}