<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreHiddenChatMessageRequest;
use App\Http\Requests\UpdateHiddenChatMessageRequest;
use App\Http\Resources\MessageResource;
use App\Models\HiddenChatMessage;
use Illuminate\Support\Facades\Auth;

class HiddenChatMessageController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $ticket_id = intval(htmlspecialchars(trim(request('ticket'))));
        $limit = intval(htmlspecialchars(trim(request('limit'))));

        $data = \Illuminate\Support\Facades\DB::table('hidden_chat_messages')
            ->when(!empty($ticket_id), fn($q) => $q->whereTicketId($ticket_id))
            ->paginate($limit < 1 ? 100 : $limit);

        $search = \App\Traits\UserTrait::search();
        $users_collection = array();
    
        foreach ($search->data as $user) {
            $users_collection[$user->crm_id] = $user;
        }
        unset($search);
    
        foreach ($data as $message) {
            if ($message->user_crm_id == 0) continue;
            $message->user = $users_collection[$message->user_crm_id];
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

        $validated['user_crm_id'] = Auth::user()->crm_id;
        $data = HiddenChatMessage::create($validated);
        $data->user = \App\Traits\UserTrait::tryToDefineUserEverywhere($data->user_crm_id);

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