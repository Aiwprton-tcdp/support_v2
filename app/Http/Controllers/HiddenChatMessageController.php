<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreHiddenChatMessageRequest;
use App\Http\Requests\UpdateHiddenChatMessageRequest;
use App\Http\Resources\MessageResource;
use App\Models\HiddenChatMessage;
use App\Traits\TicketTrait;
use App\Traits\UserTrait;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class HiddenChatMessageController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $ticket_id = intval($this->prepare(request('ticket')));
        $limit = intval($this->prepare(request('limit')));

        $data = DB::table('hidden_chat_messages')
            ->when(!empty($ticket_id), fn($q) => $q->whereTicketId($ticket_id))
            ->paginate($limit < 1 ? 100 : $limit);

        $search = UserTrait::search();
        $users_collection = array();

        $all_ids = array_values(array_unique(array_map(fn($t) => $t->new_user_id, $data->all())));
        $users_with_emails = DB::table('users')
            ->join('bx_users', 'bx_users.user_id', 'users.id')
            ->whereIn('users.id', $all_ids)
            ->select('users.id', 'users.email', 'bx_users.crm_id')
            ->get();
        foreach ($search->data as $user) {
            $users_collection[$user->email] = $user;
        }
        unset($search);
        // dd($data, $users_with_emails, $users_collection);

        foreach ($data as $message) {
            if ($message->new_user_id == 1)
                continue;
            $u = $users_with_emails->where('id', $message->new_user_id)->first();
            $message->user = @$users_collection[$u->email] ?? UserTrait::tryToDefineUserEverywhere($u->crm_id, $u->email);
            $message->user_crm_id = $u->crm_id; // чтобы отвязаться от одноимённого поля в таблице 'hidden_chat_messages'
        }
        unset($users_collection);

        return response()->json([
            'status' => true,
            'data' => MessageResource::collection($data)->response()->getData()
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreHiddenChatMessageRequest $request)
    {
        $validated = $request->validated();
        $ticket = \App\Models\Ticket::whereId($validated['ticket_id'])
            ->whereActive(true)->first();

        if (!isset($ticket)) {
            return response()->json([
                'status' => false,
                'data' => $ticket,
                'message' => 'Тикет уже завершён',
            ]);
        }

        $validated['new_user_id'] = Auth::user()->id;
        $user_with_email = DB::table('users')
          ->join('bx_users', 'bx_users.user_id', 'users.id')
          ->where('users.id', $validated['new_user_id'])
          ->select('users.id', 'users.email', 'bx_users.crm_id')
          ->first();
        $validated['user_crm_id'] = $user_with_email->crm_id;
        $data = HiddenChatMessage::create($validated);

        $recipient_ids = DB::table('users')
            ->join('bx_users', 'bx_users.user_id', 'users.id')
            ->whereIn('users.id', [$data->new_user_id, $ticket->new_manager_id])
            ->select('users.id', 'users.email', 'bx_users.crm_id')
            ->get();
        $sender = $recipient_ids->where('id', $data->new_user_id)->first();
        $manager = $recipient_ids->where('id', $ticket->new_manager_id)->first();

        $data->user = UserTrait::tryToDefineUserEverywhere($sender->crm_id, $sender->email);

        $message = "Новое сообщение в системном чате тикета №{$ticket->id}";
        $resource = MessageResource::make($data);

        if ($ticket->new_manager_id != $data->new_user_id) {
            TicketTrait::SendMessageToWebsocket("{$manager->crm_id}.hidden_message", [
                'message' => $resource,
            ]);
            TicketTrait::SendNotification($manager->crm_id, $message, $ticket->id);
        }

        $another_recipients = \App\Models\Participant::whereTicketId($ticket->id)
            ->whereNot('participants.user_id', $data->new_user_id)
            ->join('users', 'users.id', 'participants.user_id')
            ->join('bx_users', 'bx_users.user_id', 'users.id')
            ->pluck('bx_users.crm_id')->toArray();
        foreach ($another_recipients as $id) {
            TicketTrait::SendMessageToWebsocket("{$id}.hidden_message", [
                'message' => $resource,
            ]);
            // TicketTrait::SendNotification($id, $message, $ticket->id);
        }

        return response()->json([
            'status' => true,
            'data' => MessageResource::make($data)
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
    public function update(UpdateHiddenChatMessageRequest $request, $id)
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