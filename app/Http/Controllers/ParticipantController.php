<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreParticipantRequest;
use App\Models\HiddenChatMessage;
use App\Models\Participant;
use App\Models\Ticket;
use App\Models\User;
use App\Traits\TicketTrait;
use Illuminate\Support\Facades\Auth;

class ParticipantController extends Controller
{
  /**
   * Display a listing of the resource.
   */
  public function index()
  {
    $ticket_id = intval(htmlspecialchars(trim(request('ticket_id'))));

    $data = \Illuminate\Support\Facades\DB::table('participants')
      ->join('users', 'users.crm_id', 'participants.user_crm_id')
      ->where('ticket_id', $ticket_id)->get();

    $search = \App\Traits\UserTrait::search();
    // dd($data, $search);
    $users_collection = array();

    foreach ($search->data as $user) {
      $users_collection[$user->crm_id] = $user;
    }
    unset($search);

    foreach ($data as $key => $ticket) {
      $data[$key] = (object) array_merge(
        (array) $ticket,
        (array) $users_collection[$ticket->crm_id]
      );
    }
    unset($users_collection);

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
    $ticket_id = $validated['ticket_id'];

    $ticket = Ticket::find($ticket_id);
    
    if (!isset($ticket)) {
      return response()->json([
        'status' => false,
        'data' => $ticket,
        'message' => 'Тикет не доступен для редактирования'
      ]);
    } elseif ($ticket->user_id == $validated['user_crm_id']) {
      return response()->json([
        'status' => false,
        'data' => null,
        'message' => 'Нельзя назначить ответственным самого же создателя тикета'
      ]);
    }

    $new_participant_id = $ticket->manager_id;
    $ticket->manager_id = $validated['user_crm_id'];
    $ticket->save();

    $participant = Participant::firstOrNew($validated);
    if (
      $participant->exists
      && $participant->user_crm_id == $new_participant_id
    ) {
      $participant->delete();
    } else {
      $participant->user_crm_id = $new_participant_id;
      $participant->save();
    }

    TicketTrait::SendNotification($validated['user_crm_id'], "Вы стали ответственным за тикет №{$ticket->id}", $ticket->id);

    $manager = \App\Traits\UserTrait::tryToDefineUserEverywhere($ticket->manager_id);

    HiddenChatMessage::create([
      'content' => "Новый ответственный: {$manager->name}",
      'user_crm_id' => 0,
      'ticket_id' => $ticket->id,
    ]);

    $result = [
      'ticket_id' => $ticket->id,
      'new_manager' => $manager,
      'new_participant_id' => $new_participant_id,
    ];

    TicketTrait::SendMessageToWebsocket("{$ticket->manager_id}.participant", [
      'participant' => $result,
    ]);
    TicketTrait::SendMessageToWebsocket("{$ticket->user_id}.participant", [
      'participant' => $result,
    ]);
    $part_ids = Participant::whereTicketId($ticket->id)
      ->pluck('user_crm_id')->toArray();
    foreach ($part_ids as $id) {
      TicketTrait::SendMessageToWebsocket("{$id}.participant", [
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