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
        // $managers = \App\Models\Manager::join('bx_users', 'bx_users.user_id', 'managers.user_id')
        //     ->pluck('bx_users.crm_id')->toArray();
        $managers = \App\Models\Manager::leftJoin('users', 'users.id', 'managers.user_id')
            ->whereNot('users.email', '')
            ->pluck('user_id', 'users.email')->toArray();

        // dd($managers);
        $tickets = DB::table('tickets')
            ->join('reasons', 'reasons.id', 'tickets.reason_id')
            ->selectRaw('reasons.id reason_id, reasons.name reason_name, tickets.new_manager_id, COUNT(tickets.id) tickets_count')
            ->groupBy('tickets.new_manager_id', 'reasons.id')->get();

        $map = array_keys($managers);
        $search_users = array_values(
            array_filter(
                UserTrait::search()->data,
                fn($e) => in_array($e->email, $map)
            )
        );

        $users = [];
        $lost = [];
        $active = [];

        foreach ($search_users as $user) {
            $users[$user->email] = $user;
        }
        unset($search_users);

        $all_ids = array_map(fn($t) => $t->new_manager_id, $tickets->all());
        $users_with_emails = DB::table('users')
            ->join('bx_users', 'bx_users.user_id', 'users.id')
            ->whereIn('users.id', $all_ids)
            ->select('users.id', 'users.email', 'bx_users.crm_id')
            ->get();
        unset($all_ids);

        foreach ($tickets as $ticket) {
            // dd($users, $ticket);
            // $email = array_values(array_filter($users, fn($u) => $u->crm_id == $ticket->manager_id))[0]->email;
            // $email = \App\Models\User::find($ticket->new_manager_id)->email;
            $m = $users_with_emails->where('id', $ticket->new_manager_id)->first();
            if (in_array($ticket->new_manager_id, $managers)) {
                $user = UserTrait::tryToDefineUserEverywhere($m->crm_id, $m->email);
                $ticket->manager = $user ?? [
                    'crm_id' => $m->crm_id,
                    'user_id' => $m->id,
                    'name' => 'Неопределённый менеджер',
                ];
                // unset($ticket->new_manager_id);
                array_push($active, $ticket);
            } else {
                $user = UserTrait::tryToDefineUserEverywhere($m->crm_id, $m->email);
                $ticket->manager = $user;
                array_push($lost, $ticket);
            }
        }
        // dd($active, $lost);
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
            $validated['user_id'],
            $validated['new_users_ids'],
            $validated['count']
        );
        return response()->json($result);
    }

    // Обновление кеша
    public function cacheReload()
    {
        Cache::store('file')->forget('crm_users');
        Cache::store('file')->forget('crm_departments');
        // Cache::store('file')->forget('crm_all_users');
        info('Кеш очищен');

        UserTrait::search();
        UserTrait::departments();
        // UserTrait::withFired();
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

    public function getAverageSolvingTime()
    {
        $data = DB::table('resolved_tickets AS rt')
            ->join('users', 'users.id', 'rt.new_manager_id')
            ->selectRaw('users.name, rt.mark, COUNT(rt.mark) AS count')
            ->groupBy('rt.mark', 'users.id')
            ->get();

        $statuses = ['Без оценки', 'Плохо', 'Нормально', 'Отлично'];
        $result = [];
        foreach ($data as $d) {
            $result[$d->name][$statuses[$d->mark]] = $d->count;
        }

        return response()->json([
            'data' => $result
        ]);
    }

    public function getCountOfTicketsByDays()
    {
        $data = DB::table('hidden_chat_messages AS hcm')
            ->where('hcm.content', 'Тикет создан')
            ->selectRaw('COUNT(hcm.id) AS count, DATE(hcm.created_at) as date')
            ->groupBy('date')
            ->get();

        return response()->json([
            'data' => $data
        ]);
    }

    public function getCountOfTicketsByManagers()
    {
        $resolved_tickets = \App\Models\ResolvedTicket::join('users', 'users.id', 'resolved_tickets.new_manager_id')
            ->selectRaw('users.name AS name, COUNT(resolved_tickets.id) AS count')
            ->groupBy('name');

        $data = Ticket::join('users', 'users.id', 'tickets.new_manager_id')
            ->selectRaw('users.name AS name, COUNT(tickets.id) AS count')
            ->groupBy('name')
            ->union($resolved_tickets)
            ->get();

        $result = [];
        foreach ($data as $d) {
            $result[$d->name] = @$result[$d->name] + $d->count;
        }

        return response()->json([
            'data' => $result
        ]);
    }

    public function getTicketsSolvingTimeMedian()
    {
        $data = DB::table('resolved_tickets AS rt')
            ->leftJoin('messages', function ($q) {
                $q->on('messages.ticket_id', 'rt.old_ticket_id')
                    ->whereRaw('messages.id IN (SELECT MIN(m.id) FROM messages m join resolved_tickets t on t.old_ticket_id = m.ticket_id GROUP BY t.old_ticket_id)');
            })
            ->selectRaw('AVG(UNIX_TIMESTAMP(rt.created_at) - UNIX_TIMESTAMP(messages.created_at)) AS time_avg')
            ->get();

        $start = new \DateTime();
        $start->setTimestamp(0);

        $end = new \DateTime();
        $end->setTimestamp($data[0]->time_avg);
        $median = date_diff($end, $start);

        // dd($data, $median, $median->format('%dд. %h:%I:%S'));

        return response()->json([
            'data' => $median->format('%dд. %h:%I:%S')
        ]);
    }
}