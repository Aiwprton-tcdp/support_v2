<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreMessageRequest;
use App\Http\Requests\UpdateMessageRequest;
use App\Http\Resources\MessageResource;
use App\Models\Message;
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
        $ticket = intval(htmlspecialchars(trim(request('ticket'))));
        $limit = intval(htmlspecialchars(trim(request('limit'))));

        $data = Message::with('attachments')
            ->when(!empty($ticket), function ($q) use ($ticket) {
                $q->whereTicketId($ticket);
            })
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
        $validated['content'] = '';
        $validated['user_crm_id'] = Auth::user()->crm_id;
        // $validated['has_attachments'] = count($_FILES) > 0;
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

        // $channel_id = '#support.' . md5($data->user_id) . md5(env('CENTRIFUGE_SALT'));
        $recipient_id = $ticket->user_id == $data->user_crm_id ? $ticket->manager_id : $ticket->user_id;
        $channel_id = '#support.' . $recipient_id;
        $client = new \phpcent\Client(
            env('CENTRIFUGE_URL') . '/api',
            '8ffaffac-8c9e-4a9c-88ce-54658097096e',
            'ee93146a-0607-4ea3-aa4a-02c59980647e'
        );
        $client->setSafety(false);

        $data['attachments'] = collect($attachments);
        $client->publish($channel_id, [
            'message' => MessageResource::make($data),
        ]);

        TicketTrait::SendNotification($recipient_id, $data['content'], $ticket->id);

        return response()->json([
            'status' => true,
            'data' => MessageResource::make($data)
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