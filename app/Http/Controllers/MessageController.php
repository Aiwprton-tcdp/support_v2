<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreMessageRequest;
use App\Http\Requests\UpdateMessageRequest;
use App\Http\Resources\MessageResource;
use App\Models\Message;
use App\Models\Participant;
use App\Traits\TicketTrait;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class MessageController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $ticket_id = intval(htmlspecialchars(trim(request('ticket'))));
        $limit = intval(htmlspecialchars(trim(request('limit'))));

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
        if (count($_FILES) > 5) {
            return [
                'status' => 'error',
                'data' => null,
                'message' => 'Файлов больше 5!',
            ];
        }

        $validated = $request->validated();
        $validated['content'] ??= '';
        $validated['user_crm_id'] = Auth::user()->crm_id;
        $ticket = \App\Models\Ticket::whereId($validated['ticket_id'])
            ->whereActive(true)->first();

        if (empty($ticket)) {
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
        $data['attachments'] = collect($attachments);

        $recipient_ids = [];
        if (!in_array($data->user_crm_id, [$ticket->user_id, $ticket->manager_id])) {
            $recipient_ids = [$ticket->user_id, $ticket->manager_id];
        } else {
            array_push($recipient_ids, $ticket->user_id == $data->user_crm_id ? $ticket->manager_id : $ticket->user_id);
        }

        $message = 'Новое сообщение в тикете';
        $resource = MessageResource::make($data);

        foreach ($recipient_ids as $id) {
            TicketTrait::SendMessageToWebsocket("{$id}.message", [
                'message' => $resource,
            ]);
            // TicketTrait::SendNotification($id, $message, $ticket->id);
        }

        $another_recipients = Participant::whereTicketId($ticket->id)
            ->whereNot('user_crm_id', $data->user_crm_id)
            ->get('user_crm_id');
        foreach ($another_recipients as $rec) {
            $id = $rec->user_crm_id;
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