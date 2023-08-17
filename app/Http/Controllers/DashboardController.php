<?php

namespace App\Http\Controllers;

use App\Http\Requests\Dashboard\RedistributionRequest;
use App\Models\Ticket;
use App\Traits\TicketTrait;
use App\Traits\UserTrait;
use Illuminate\Support\Facades\Cache;

class DashboardController extends Controller
{
    // Детализация по активным тикетам
    public function activeTickets()
    {
        $managers = \App\Models\Manager::pluck('crm_id')->toArray();

        $tickets = Ticket::join('reasons', 'reasons.id', 'tickets.reason_id')
            ->selectRaw('reasons.id reason_id, reasons.name reason_name, manager_id, COUNT(tickets.id) tickets_count')
            ->groupBy('manager_id', 'reasons.id')->get();

        $search_users = array_values(array_filter(
            UserTrait::search()->data,
            fn($e) => in_array($e->crm_id, $managers)
        ));

        $users = [];
        $lost = [];
        $active = [];
        
        foreach ($search_users as $user) {
            $users[$user->crm_id] = $user;
        }
        unset($search_users);

        foreach ($tickets as $ticket) {
            if (in_array($ticket->manager_id, $managers)) {
                $ticket->manager = $users[$ticket->manager_id];
                unset($ticket->manager_id);
                array_push($active, $ticket);
            } else {
                $user = UserTrait::tryToDefineUserEverywhere($ticket->manager_id);
                $ticket->manager = $user;
                array_push($lost, $ticket);
            }
        }

        return response()->json([
            'status' => true,
            'data' => [
                'lost' => $lost,
                'active' => $active
            ],
        ]);
    }

    // Перераспределение тикетов по теме
    public function redistribute(RedistributionRequest $request)
    {
        $validated = $request->validated();
        // 59 + (28 + 35,5)
        $result = TicketTrait::TryToRedistributeByReason(
            $validated['reason_id'],
            $validated['user_crm_id'],
            $validated['new_crm_ids'],
            $validated['count']
        );
        return response()->json($result);
    }

    // Обновление кеша
    public function cacheReload()
    {
        Cache::store('file')->forget('crm_users');
        Cache::store('file')->forget('crm_departments');
        Cache::store('file')->forget('crm_all_users');

        UserTrait::search();
        UserTrait::departments();
        UserTrait::withFired();

        return response()->json([
            'status' => true,
            'data' => null,
            'message' => 'Кеш успешно обновлён',
        ]);
    }
}