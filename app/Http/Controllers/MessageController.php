<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreMessageRequest;
use App\Http\Requests\UpdateMessageRequest;
use App\Http\Resources\MessageResource;
use App\Models\BxCrm;
use App\Models\Message;
use App\Models\Participant;
use App\Traits\TicketTrait;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

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

        $app_domain = BxCrm::leftJoin('tickets', 'tickets.crm_id', 'bx_crms.id')
            ->leftJoin('resolved_tickets AS rt', 'rt.crm_id', 'bx_crms.id')
            ->where('tickets.id', $ticket_id)
            ->orWhere('rt.old_ticket_id', $ticket_id)
            ->first();

        foreach ($data as $d) {
            $d->attachments_domain = @$app_domain->app_domain;
        }

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
            ->leftJoin('bx_users', 'bx_users.user_id', 'users.id')
            ->where('users.id', $validated['new_user_id'])
            ->selectRaw('users.id, users.email, IFNULL(bx_users.crm_id, users.crm_id) AS crm_id')
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

        $app_domain = BxCrm::join('tickets', 'tickets.crm_id', 'bx_crms.id')
            ->where('tickets.id', $data->ticket_id)
            ->first()->app_domain;
        preg_match('#https:\/\/([^\.]*)\.#', $app_domain, $match);
        $attachments = [];
        foreach ($_FILES as $file) {
            $attachment_path = TicketTrait::SaveAttachment($data->id, $file, $app_domain, $match[1]);
            array_push($attachments, $attachment_path);
        }
        $data->attachments = collect($attachments);
        $data->attachments_domain = $app_domain;

        $recipient_ids = DB::table('users')
            ->leftJoin('bx_users', 'bx_users.user_id', 'users.id')
            ->whereIn('users.id', [$ticket->new_user_id, $ticket->new_manager_id])
            ->selectRaw('users.id, users.email, IFNULL(bx_users.crm_id, users.crm_id) AS crm_id')
            ->get();

        $creator = $recipient_ids->where('id', $ticket->new_user_id)->first();
        $manager = $recipient_ids->where('id', $ticket->new_manager_id)->first();

        $message = "Новое сообщение в тикете №{$ticket->id}";
        $resource = MessageResource::make($data);

        if ($ticket->new_user_id != $data->new_user_id) {
            TicketTrait::SendMessageToWebsocket("{$creator->email}.message", [
                'message' => $resource,
            ]);
            TicketTrait::SendNotification($creator->id, $message, $ticket->id);
        }
        if ($ticket->new_manager_id != $data->new_user_id) {
            TicketTrait::SendMessageToWebsocket("{$manager->email}.message", [
                'message' => $resource,
            ]);
            TicketTrait::SendNotification($manager->id, $message, $ticket->id);
        }

        $another_recipients = Participant::whereTicketId($ticket->id)
            ->whereNot('participants.user_id', $data->new_user_id)
            ->join('users', 'users.id', 'participants.user_id')
            // ->join('bx_users', 'bx_users.user_id', 'users.id')
            // ->pluck('bx_users.crm_id')->toArray();
            ->pluck('users.email')->toArray();
        foreach ($another_recipients as $email) {
            TicketTrait::SendMessageToWebsocket("{$email}.message", [
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