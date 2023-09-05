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
                $user = UserTrait::tryToDefineUserEverywhere($ticket->manager_id);
                $ticket->manager = $user //;
                    // $ticket->manager = $users[$ticket->manager_id]
                    ?? [
                        'crm_id' => $ticket->manager_id,
                        'name' => 'Неопределённый менеджер',
                    ];
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

        return response()->json([
            'data' => $data
        ]);
    }

    public function getTicketsByGroups()
    {
        $resolved_tickets = DB::table('resolved_tickets')
            ->join('reasons', 'reasons.id', 'resolved_tickets.reason_id')
            ->join('groups', 'groups.id', 'reasons.group_id')
            ->selectRaw('resolved_tickets.old_ticket_id AS id,
                reasons.group_id AS group_id,
                groups.name AS group_name,
                -1 AS status');
        $tickets = DB::table('tickets')
            ->join('reasons', 'reasons.id', 'tickets.reason_id')
            ->join('groups', 'groups.id', 'reasons.group_id')
            ->selectRaw('tickets.id AS id,
                reasons.group_id AS group_id,
                groups.name AS group_name,
                tickets.active AS status')
            ->union($resolved_tickets);

        $data = DB::query()->fromSub($tickets, 't')
            ->selectRaw('COUNT(t.id) AS value, t.status, t.group_id, t.group_name')
            ->groupBy('t.group_id', 't.group_name', 't.status')
            ->orderBy('t.group_name')
            ->get();

        $result = [];
        foreach ($data as $d) {
            $result[$d->group_name][$d->status] = $d->value;
        }

        return response()->json([
            'data' => $result
        ]);
    }

    public function getMarksPercentage()
    {
        $data = DB::table('resolved_tickets AS rt')
            ->selectRaw('COUNT(rt.old_ticket_id) AS count, rt.mark')
            ->groupBy('rt.mark')
            ->get();

        $map = array_map(fn($d) => $d->count, $data->all());
        $sum = array_sum($map);
        $statuses = ['Без оценки', 'Плохо', 'Нормально', 'Отлично'];
        // $sum = array_sum(array_filter($map, fn($f, $key) => $key != 0, ARRAY_FILTER_USE_BOTH));
        $res = [];
        foreach ($map as $key => $value) {
            $res[$statuses[$key]] = number_format($value / $sum * 100, 2, '.', '');
        }
        // dd($res);
        $result = $res;
        // $result = array_reverse($res);

        return response()->json([
            'data' => $result
        ]);
    }
}