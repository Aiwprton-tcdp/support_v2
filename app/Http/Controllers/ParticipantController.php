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
      ->join('bx_users', 'bx_users.user_id', 'users.id')
      ->whereIn('users.id', $all_ids)
      ->select('users.id', 'users.email', 'bx_users.crm_id')
      ->get();

    foreach ($data as $key => $participants) {
      $p = $users_with_emails->where('id', $participants->user_id)->first();
      $data[$key] = (object) array_merge(
        (array) $participants,
        (array) @$users_collection[$p->email] ?? UserTrait::tryToDefineUserEverywhere($p->crm_id, $p->email)
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
    $ticket = Ticket::findOrFail($validated['ticket_id']);

    if (!isset($ticket)) {
      return response()->json([
        'status' => false,
        'data' => $ticket,
        'message' => 'Тикет не доступен для редактирования'
      ]);
    } elseif ($ticket->new_user_id == $validated['user_id']) {
      return response()->json([
        'status' => false,
        'data' => null,
        'message' => 'Нельзя назначить ответственным самого же создателя тикета'
      ]);
    }

    $users_with_email = DB::table('users')
      ->join('bx_users', 'bx_users.user_id', 'users.id')
      ->whereIn('users.id', [$ticket->new_user_id, $validated['user_id'], $ticket->new_manager_id])
      ->select('users.id', 'users.email', 'bx_users.crm_id')
      ->get();

    $creator = $users_with_email->where('id', $ticket->new_user_id)->first();
    $manager = $users_with_email->where('id', $validated['user_id'])->first();
    $participant = $users_with_email->where('id', $ticket->new_manager_id)->first();

    // dd($ticket, $creator->email, $manager, $participant);
    unset($users_with_email);

    $ticket->new_manager_id = $manager->id;
    $ticket->save();

    $old_participant = Participant::firstOrNew($validated);
    if ($old_participant->exists) {
      $old_participant->delete();
    }

    $new_participant = Participant::firstOrNew([
      'ticket_id' => $validated['ticket_id'],
      'user_id' => $participant->id,
    ]);
    if (!$new_participant->exists) {
      $new_participant->user_crm_id = $participant->crm_id;
      $new_participant->save();
    }

    TicketTrait::SendNotification($manager->id, "Вы стали ответственным за тикет №{$ticket->id}", $ticket->id);

    $user = UserTrait::tryToDefineUserEverywhere($manager->id, $manager->email);

    HiddenChatMessage::create([
      'content' => "Новый ответственный: {$user->name}",
      'user_crm_id' => 0,
      'new_user_id' => 1,
      'ticket_id' => $ticket->id,
    ]);

    $result = [
      'ticket_id' => $ticket->id,
      'new_manager' => $user,
      'new_manager_id' => $manager->id,
      'new_participant_id' => $ticket->new_manager_id,
    ];

    // TicketTrait::SendMessageToWebsocket("{$manager->crm_id}.ticket", [
    //   'participant' => $result,
    // ]);
    // $ticket->reason = \App\Models\Reason::find($ticket->reason_id)->name;
    // $ticket->user = UserTrait::tryToDefineUserEverywhere($creator->crm_id, $creator->email);
    // $ticket->manager = UserTrait::tryToDefineUserEverywhere($manager->crm_id, $manager->email);

    // $bx_crm_data = DB::table('bx_crms')
    //   ->join('bx_users AS bx', 'bx.bx_crm_id', 'bx_crms.id')
    //   ->where('bx.user_id', $creator->id)
    //   ->select('bx_crms.name', 'bx_crms.acronym', 'bx_crms.domain')
    //   ->first();
    // $ticket->bx_name = $bx_crm_data->name;
    // $ticket->bx_acronym = $bx_crm_data->acronym;
    // $ticket->bx_domain = $bx_crm_data->domain;

    // $resource = \App\Http\Resources\TicketResource::make($ticket);
    TicketTrait::SendMessageToWebsocket("{$manager->email}.participant", [
      // 'ticket' => $resource,
      'participant' => $result,
    ]);
    TicketTrait::SendMessageToWebsocket("{$creator->email}.participant", [
      'participant' => $result,
    ]);
    // TicketTrait::SendMessageToWebsocket("{$participant->email}.participant", [
    //   'participant' => $result,
    // ]);
    $part_emails = Participant::join('users', 'users.id', 'participants.user_id')
      ->whereTicketId($ticket->id)
      // ->join('bx_users', 'bx_users.user_id', 'users.id')
      // ->pluck('bx_users.crm_id')->toArray();
      ->pluck('users.email')->toArray();
    foreach ($part_emails as $email) {
      TicketTrait::SendMessageToWebsocket("{$email}.participant", [
        'participant' => $result,
      ]);
    }

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