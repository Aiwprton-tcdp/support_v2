<?php

namespace App\Http\Controllers;

use App\Http\Requests\Dashboard\RedistributionRequest;
use App\Models\Ticket;
use App\Traits\TicketTrait;
use App\Traits\UserTrait;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    // Детализация по активным тикетам
    public function getActiveTickets()
    {
        $managers = \App\Models\Manager::pluck('crm_id')->toArray();

        $tickets = Ticket::join('reasons', 'reasons.id', 'tickets.reason_id')
            ->selectRaw('reasons.id reason_id, reasons.name reason_name, manager_id, COUNT(tickets.id) tickets_count')
            ->groupBy('manager_id', 'reasons.id')->get();

        $search_users = array_values(
            array_filter(
                UserTrait::search()->data,
                fn($e) => in_array($e->crm_id, $managers)
            )
        );

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
        info('Кеш очищен');

        UserTrait::search();
        UserTrait::departments();
        UserTrait::withFired();
        info('Кеш обновлён');

        return response()->json([
            'status' => true,
            'data' => null,
            'message' => 'Кеш успешно обновлён',
        ]);
    }

    // Обновление кеша
    public function getTicketsCountByReasons()
    {
        $resolved_tickets = DB::table('resolved_tickets')
            ->join('reasons', 'reasons.id', 'resolved_tickets.reason_id')
            ->selectRaw('resolved_tickets.old_ticket_id AS id, reasons.name AS name');
        $tickets = DB::table('tickets')
            ->join('reasons', 'reasons.id', 'tickets.reason_id')
            ->selectRaw('tickets.id AS id, reasons.name AS name')
            ->union($resolved_tickets);

        $data = DB::query()->fromSub($tickets, 't')
            ->selectRaw('COUNT(t.id) AS value, t.name AS name')
            ->groupBy('name')
            ->orderBy('name')
            ->get();

        // dd($data);

        return response()->json([
            'data' => $data
        ]);
    }
}