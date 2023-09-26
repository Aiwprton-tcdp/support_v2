<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreMessageRequest;
use App\Http\Requests\UpdateMessageRequest;
use App\Http\Resources\MessageResource;
use App\Models\Message;
use App\Models\Participant;
use App\Traits\TicketTrait;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class MessageController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $ticket_id = intval($this->prepare(request('ticket')));
        $limit = intval($this->prepare(request('limit')));

        $data = Message::with('attachments')
            ->when(!empty($ticket_id), fn($q) => $q->whereTicketId($ticket_id))
            ->paginate($limit < 1 ? 100 : $limit);

        return response()->json([
            'status' => true,
            'data' => MessageResource::collection($data)->response()->getData()
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreMessageRequest $request)
    {
        // dd($request->validated(), $_FILES);
        if (count($_FILES) > 5) {
            return [
                'status' => 'error',
                'data' => null,
                'message' => 'Файлов больше 5!',
            ];
        }

        $validated = $request->validated();
        $validated['content'] ??= '';
        $validated['new_user_id'] = Auth::user()->id;
        $user_with_email = DB::table('users')
          ->join('bx_users', 'bx_users.user_id', 'users.id')
          ->where('users.id', $validated['new_user_id'])
          ->select('users.id', 'users.email', 'bx_users.crm_id')
          ->first();
        $validated['user_crm_id'] = $user_with_email->crm_id;
        $ticket = \App\Models\Ticket::whereId($validated['ticket_id'])
            ->whereActive(true)->first();

            // dd($ticket);
        if (!isset($ticket)) {
            return response()->json([
                'status' => false,
                'data' => $ticket,
                'message' => 'Тикет уже завершён',
            ]);
        }

        $data = Message::create($validated);

        $attachments = [];
        foreach ($_FILES as $file) {
            $attachment_path = TicketTrait::SaveAttachment($data->id, $file);
            array_push($attachments, $attachment_path);
        }
        $data->attachments = collect($attachments);

        
        $recipient_ids = DB::table('users')
            ->join('bx_users', 'bx_users.user_id', 'users.id')
            ->whereIn('users.id', [$ticket->new_user_id, $ticket->new_manager_id])
            ->select('users.id', 'users.email', 'bx_users.crm_id')
            ->get();
    
        $creator = $recipient_ids->where('id', $ticket->new_user_id)->first();
        $manager = $recipient_ids->where('id', $ticket->new_manager_id)->first();

        $message = "Новое сообщение в тикете №{$ticket->id}";
        $resource = MessageResource::make($data);

        if ($ticket->new_user_id != $data->new_user_id) {
            TicketTrait::SendMessageToWebsocket("{$creator->crm_id}.message", [
                'message' => $resource,
            ]);
            TicketTrait::SendNotification($creator->crm_id, $message, $ticket->id);
        }
        if ($ticket->new_manager_id != $data->new_user_id) {
            TicketTrait::SendMessageToWebsocket("{$manager->crm_id}.message", [
                'message' => $resource,
            ]);
            TicketTrait::SendNotification($manager->crm_id, $message, $ticket->id);
        }

        $another_recipients = Participant::whereTicketId($ticket->id)
            ->whereNot('user_id', $data->new_user_id)
            ->join('users', 'users.id', 'participants.user_id')
            ->join('bx_users', 'bx_users.user_id', 'users.id')
            ->pluck('bx_users.crm_id')->toArray();
        foreach ($another_recipients as $id) {
            TicketTrait::SendMessageToWebsocket("{$id}.message", [
                'message' => $resource,
            ]);
            // TicketTrait::SendNotification($id, $message, $ticket->id);
        }
        
        return response()->json([
            'status' => true,
            'data' => $resource
        ]);
    }

    /**
     * Display the specified resource.
     */
    public function show(Message $message)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateMessageRequest $request, $id)
    {
        $data = Message::findOrFail($id);
        $data->fill($request->validated());
        $data->save();

        return response()->json([
            'status' => true,
            'data' => MessageResource::make($data)
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        $data = Message::findOrFail($id);
        $result = $data->delete();

        return response()->json([
            'status' => true,
            'data' => $result
        ]);
    }
}