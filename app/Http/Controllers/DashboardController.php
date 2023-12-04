<?php

namespace App\Http\Controllers;

use App\Http\Requests\Dashboard\RedistributionRequest;
use App\Models\Ticket;
use App\Traits\DashboardTrait;
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
            ->leftJoin('bx_users', 'bx_users.user_id', 'users.id')
            ->whereIn('users.id', $all_ids)
            ->selectRaw('users.id, users.email, IFNULL(bx_users.crm_id, users.crm_id) AS crm_id')
            ->get();
        unset($all_ids);

        foreach ($tickets as $ticket) {
            $m = $users_with_emails->where('id', $ticket->new_manager_id)->first();
            $user = UserTrait::tryToDefineUserEverywhere($m->crm_id, $m->email);
            $ticket->manager = $user ?? [
                'crm_id' => $m->crm_id,
                'user_id' => $m->id,
                'name' => 'Неопределённый менеджер',
            ];
            if (in_array($ticket->new_manager_id, $managers)) {
                array_push($active, $ticket);
            } else {
                array_push($lost, $ticket);
            }
        }

        $solvedLost = [];
        foreach ($lost as $l) {
            $name = $l->manager->name;
            $reason = $l->reason_name;
            $solvedLost[$name][$reason] = $l;
        }
        $solvedActive = [];
        foreach ($active as $a) {
            $name = $a->manager->name;
            $reason = $a->reason_name;
            $solvedActive[$name][$reason] = $a;
        }

        // dd($active, $lost);
        return response()->json([
            'status' => true,
            'data' => [
                // 'lost' => $lost,
                // 'active' => $active,
                'lost' => $solvedLost,
                'active' => $solvedActive,
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
        $prefix = env('APP_PREFIX');
        Cache::store('file')->forget("{$prefix}_users");
        Cache::store('file')->forget("{$prefix}_departments");
        // Cache::store('file')->forget('crm_all_users');
        Cache::store('file')->forget('reasons');
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

        $res = array_filter($result, fn($r, $key) => $result[$key] >= 20, ARRAY_FILTER_USE_BOTH);

        return response()->json([
            'data' => $res
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

        if ($data[0]->time_avg > 86400) {
            $data[0]->time_avg /= 3;
        }

        $start = new \DateTime();
        $start->setTimestamp(0);

        $end = new \DateTime();
        $end->setTimestamp($data[0]->time_avg);
        $median = date_diff($end, $start);

        // dd($data, $median, $median->format('%dд. %h:%I:%S'));

        $result = $data[0]->time_avg > 86400
            ? $median->format('%dд. %h:%I:%S')
            : $median->format('%h:%I:%S');
        return response()->json([
            'data' => $result
        ]);
    }

    public function GetAvgMaxMinTicketsPerDay()
    {
        $data = DB::table('hidden_chat_messages AS hcm')
            ->where('hcm.content', 'Тикет создан')
            ->whereRaw('WEEKDAY(hcm.created_at) IN (0,1,2,3,4)')
            ->selectRaw('COUNT(hcm.id) AS count, DATE(hcm.created_at) AS date')
            ->groupBy('date')
            ->get();

        $sum = 0;
        $max = 0;
        $min = $data[0]->count;
        $today = 0;

        foreach ($data as $d) {
            $sum += $d->count;
            $max = $max < $d->count ? $d->count : $max;
            $min = $min > $d->count ? $d->count : $min;

            $d1 = new \DateTime($d->date);
            $d2 = new \DateTime(now());
            // dd($d1, $d2, $d1->format("Y-m-d"), $d2->format("Y-m-d"));
            if ($d1->format("Y-m-d") == $d2->format("Y-m-d"))
                $today += $d->count;
        }

        $avg = $sum / count($data);

        return response()->json([
            'data' => [
                'avg' => number_format((float) $avg, 2, '.', ''),
                'max' => $max,
                'min' => $min,
                'today' => $today,
            ]
        ]);
    }

    public function GetAvgTimeByReason()
    {
        $resolved_tickets = DB::table('resolved_tickets')
            ->leftJoin(
                'messages',
                fn($q) => $q->on('messages.ticket_id', 'resolved_tickets.old_ticket_id')
                    ->whereRaw('messages.id IN (SELECT MIN(m.id) FROM messages m join resolved_tickets t on t.old_ticket_id = m.ticket_id GROUP BY t.old_ticket_id)')
            )
            ->leftJoin(
                'hidden_chat_messages',
                fn($q) => $q->on('hidden_chat_messages.ticket_id', 'resolved_tickets.old_ticket_id')
                    ->whereRaw('hidden_chat_messages.id IN (SELECT MAX(m.id) FROM hidden_chat_messages m join resolved_tickets t on t.old_ticket_id = m.ticket_id WHERE m.content LIKE "Тикет завершён" GROUP BY t.old_ticket_id)')
            )
            ->join('reasons', 'reasons.id', 'resolved_tickets.reason_id')
            ->selectRaw('TIMEDIFF(IFNULL(hidden_chat_messages.created_at, NOW()), messages.created_at) AS time, reasons.name AS name');
        $tickets = DB::table('tickets')
            ->leftJoin(
                'messages',
                fn($q) => $q->on('messages.ticket_id', 'tickets.id')
                    ->whereRaw('messages.id IN (SELECT MIN(m.id) FROM messages m join tickets t on t.id = m.ticket_id GROUP BY t.id)')
            )
            ->leftJoin(
                'hidden_chat_messages',
                fn($q) => $q->on('hidden_chat_messages.ticket_id', 'tickets.id')
                    ->whereRaw('hidden_chat_messages.id IN (SELECT MAX(m.id) FROM hidden_chat_messages m join tickets t on t.id = m.ticket_id WHERE m.content LIKE "%пометил тикет как решённый" GROUP BY t.id)')
            )
            ->join('reasons', 'reasons.id', 'tickets.reason_id')
            ->selectRaw('TIMEDIFF(IFNULL(hidden_chat_messages.created_at, NOW()), messages.created_at) AS time, reasons.name AS name')
            ->union($resolved_tickets);

        $data = DB::query()->fromSub($tickets, 't')
            ->selectRaw('AVG(t.time) AS avg_time, t.name AS name')
            ->groupBy('name')
            ->orderByDesc('avg_time')
            ->get();

        // dd($data);
        foreach ($data as $d) {
            $time = $d->avg_time > 86400 ? $d->avg_time / 3 : $d->avg_time;
            $d->avg_time = sprintf('%02d:%02d:%02d', ($time / 3600), ($time / 60 % 60), $time % 60);
        }
        return response()->json([
            'data' => $data
        ]);
    }

    public function GetStatsByReasonsAndManagersPerDay()
    {
        $avgSolvingTimeByUsers = DashboardTrait::getAvgSolvingTimeByUsers();
        $avgSolvingTimeByReasons = DashboardTrait::getAvgSolvingTimeByReasons();
        $newTicketsCountByUsers = DashboardTrait::getNewTicketsCountByUsers();
        $newTicketsCountByReasons = DashboardTrait::getNewTicketsCountByReasons();
        $resolvedTicketsCountByUsers = DashboardTrait::getResolvedTicketsCountByUsers();
        $resolvedTicketsCountByReasons = DashboardTrait::getResolvedTicketsCountByReasons();

        return response()->json([
            'data' => [
                'avgSolvingTimeByUsers' => $avgSolvingTimeByUsers,
                'avgSolvingTimeByReasons' => $avgSolvingTimeByReasons,
                'newTicketsCountByUsers' => $newTicketsCountByUsers,
                'newTicketsCountByReasons' => $newTicketsCountByReasons,
                'resolvedTicketsCountByUsers' => $resolvedTicketsCountByUsers,
                'resolvedTicketsCountByReasons' => $resolvedTicketsCountByReasons,
            ]
        ]);
    }

    public function GetTicketsByDepartments()
    {
        $resolved_tickets = \App\Models\ResolvedTicket::join('users', 'users.id', 'resolved_tickets.new_user_id')
            ->selectRaw('users.email, COUNT(resolved_tickets.id) AS count')
            ->groupBy('email');

        $data = Ticket::join('users', 'users.id', 'tickets.new_user_id')
            ->selectRaw('users.email, COUNT(tickets.id) AS count')
            ->union($resolved_tickets)
            ->groupBy('email')
            ->orderBy('email')
            ->get()->toArray();

        $res = [];
        foreach ($data as $d) {
            if (!isset($res[$d['email']])) {
                $res[$d['email']] = 0;
            }
            $res[$d['email']] += $d['count'];
        }
        unset($data);

        $support_test_users = Cache::store('file')->get('support_test_users');
        $support_users = Cache::store('lfp')->get('support_users');
        $users = array_merge($support_users->data, $support_test_users->data);
        unset($support_users, $support_test_users);

        $deps = [];
        foreach ($users as $user) {
            if (!isset($res[$user->email]))
                continue;

            $d = $user->departments[0];
            if (!isset($deps[$d])) {
                $deps[$d] = 0;
            }
            $deps[$d] += $res[$user->email];
            // array_push($deps[$d], [$user->email => $res[$user->email]]);
        }
        unset($users, $res);

        $support_departments = Cache::store('lfp')->get('support_departments');
        $named_deps = [];
        foreach ($support_departments->data as $d) {
            if (!isset($named_deps[$d->name])) {
                $named_deps[$d->name] = 0;
            }
            if (!isset($deps[$d->id]))
                continue;
            $named_deps[$d->name] += $deps[$d->id];
        }
        unset($support_departments, $deps);
        $result_deps = array_filter($named_deps, fn($d) => $d > 0);

        return response()->json([
            'data' => $result_deps,
        ]);
    }
}