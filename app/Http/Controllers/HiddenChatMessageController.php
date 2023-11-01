<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreHiddenChatMessageRequest;
use App\Http\Requests\UpdateHiddenChatMessageRequest;
use App\Http\Resources\MessageResource;
use App\Models\BxCrm;
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
            ->leftJoin('bx_users', 'bx_users.user_id', 'users.id')
            ->whereIn('users.id', $all_ids)
            ->selectRaw('users.id, users.email, IFNULL(bx_users.crm_id, users.crm_id) AS crm_id')
            ->get();
        foreach ($search->data as $user) {
            $users_collection[$user->email] = $user;
        }
        unset($search);
        // dd($data, $users_with_emails, $users_collection);

        // $app_domain = BxCrm::leftJoin('tickets', 'tickets.crm_id', 'bx_crms.id')
        //     ->leftJoin('resolved_tickets AS rt', 'rt.crm_id', 'bx_crms.id')
        //     ->where('tickets.id', $ticket_id)
        //     ->first()->app_domain;

        foreach ($data as $message) {
            // $message->attachments_domain = $app_domain;
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
            ->leftJoin('bx_users', 'bx_users.user_id', 'users.id')
            ->where('users.id', $validated['new_user_id'])
            ->selectRaw('users.id, users.email, IFNULL(bx_users.crm_id, users.crm_id) AS crm_id')
            ->first();
        $validated['user_crm_id'] = $user_with_email->crm_id;
        $data = HiddenChatMessage::create($validated);

        $recipient_ids = DB::table('users')
            ->leftJoin('bx_users', 'bx_users.user_id', 'users.id')
            ->whereIn('users.id', [$data->new_user_id, $ticket->new_manager_id])
            ->selectRaw('users.id, users.email, IFNULL(bx_users.crm_id, users.crm_id) AS crm_id')
            ->get();
        $sender = $recipient_ids->where('id', $data->new_user_id)->first();
        $manager = $recipient_ids->where('id', $ticket->new_manager_id)->first();

        $data->user = UserTrait::tryToDefineUserEverywhere($sender->crm_id, $sender->email);
        // $app_domain = BxCrm::leftJoin('tickets', 'tickets.crm_id', 'bx_crms.id')
        //     ->leftJoin('resolved_tickets AS rt', 'rt.crm_id', 'bx_crms.id')
        //     ->where('tickets.id', $data->ticket_id)
        //     ->first()->app_domain;
        // $data->attachments_domain = $app_domain;

        $message = "Новое сообщение в системном чате тикета №{$ticket->id}";
        $resource = MessageResource::make($data);

        if ($ticket->new_manager_id != $data->new_user_id) {
            TicketTrait::SendMessageToWebsocket("{$manager->email}.hidden_message", [
                'message' => $resource,
            ]);
            TicketTrait::SendNotification($manager->id, $message, $ticket->id);
        }

        $another_recipients = \App\Models\Participant::whereTicketId($ticket->id)
            ->whereNot('participants.user_id', $data->new_user_id)
            ->join('users', 'users.id', 'participants.user_id')
            // ->join('bx_users', 'bx_users.user_id', 'users.id')
            // ->pluck('bx_users.crm_id')->toArray();
            ->pluck('users.email')->toArray();
        foreach ($another_recipients as $email) {
            TicketTrait::SendMessageToWebsocket("{$email}.hidden_message", [
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