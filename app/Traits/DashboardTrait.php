<?php

namespace App\Traits;

use App\Models\ResolvedTicket;
use App\Models\Ticket;
use Illuminate\Support\Facades\DB;

trait DashboardTrait
{
  public static function getAvgSolvingTimeByUsers(): array
  {
    $resolved_tickets = ResolvedTicket::join('users', 'users.id', 'resolved_tickets.new_manager_id')
      ->join(
        'hidden_chat_messages AS hcm_s',
        fn($q) => $q
          ->on('hcm_s.ticket_id', 'resolved_tickets.old_ticket_id')
          ->whereRaw('hcm_s.id IN (SELECT MAX(m.id) FROM hidden_chat_messages m join resolved_tickets t on t.old_ticket_id = m.ticket_id WHERE m.content = "Тикет создан" GROUP BY t.old_ticket_id)')
      )
      ->join(
        'hidden_chat_messages AS hcm',
        fn($q) => $q
          ->on('hcm.ticket_id', 'resolved_tickets.old_ticket_id')
          ->whereRaw('hcm.id IN (SELECT MAX(m.id) FROM hidden_chat_messages m join resolved_tickets t on t.old_ticket_id = m.ticket_id WHERE m.content = "Тикет завершён" GROUP BY t.old_ticket_id)')
      )
      ->whereRaw("hcm_s.created_at >= (NOW() - INTERVAL 1 MONTH)")
      ->selectRaw('users.name AS name,
      HOUR(AVG(TIMEDIFF(IFNULL(hcm.created_at, NOW()), hcm_s.created_at))) AS time,
      DATE(IFNULL(hcm.created_at, hcm_s.created_at)) AS date')
      ->groupBy('date', 'name');

    $data = Ticket::join('users', 'users.id', 'tickets.new_manager_id')
      ->join(
        'hidden_chat_messages AS hcm_s',
        fn($q) => $q
          ->on('hcm_s.ticket_id', 'tickets.id')
          ->whereRaw('hcm_s.id IN (SELECT MAX(m.id) FROM hidden_chat_messages m join tickets t on t.id = m.ticket_id WHERE m.content = "Тикет создан" GROUP BY t.id)')
      )
      ->join(
        'hidden_chat_messages AS hcm',
        fn($q) => $q
          ->on('hcm.ticket_id', 'tickets.id')
          ->whereRaw('hcm.id IN (SELECT MAX(m.id) FROM hidden_chat_messages m join tickets t on t.id = m.ticket_id WHERE m.content LIKE "%пометил тикет как решённый" GROUP BY t.id)')
      )
      ->whereRaw("hcm_s.created_at >= (NOW() - INTERVAL 1 MONTH)")
      ->selectRaw('users.name AS name,
      HOUR(AVG(TIMEDIFF(IFNULL(hcm.created_at, NOW()), hcm_s.created_at))) AS time,
      DATE(IFNULL(hcm.created_at, hcm_s.created_at)) AS date')
      ->groupBy('date', 'name')
      ->union($resolved_tickets)
      ->groupBy('date', 'name')
      ->get()->toArray();

    // return $data;
    $filter = array_filter($data, fn($d) => !in_array(null, [$d['name'], $d['date'], $d['time']]));
    return array_values($filter);
  }

  public static function getAvgSolvingTimeByReasons(): array
  {
    $resolved_tickets = ResolvedTicket::join('reasons', 'reasons.id', 'resolved_tickets.reason_id')
      ->join(
        'hidden_chat_messages AS hcm_s',
        fn($q) => $q
          ->on('hcm_s.ticket_id', 'resolved_tickets.old_ticket_id')
          ->whereRaw('hcm_s.id IN (SELECT MAX(m.id) FROM hidden_chat_messages m join resolved_tickets t on t.old_ticket_id = m.ticket_id WHERE m.content = "Тикет создан" GROUP BY t.old_ticket_id)')
      )
      ->join(
        'hidden_chat_messages AS hcm',
        fn($q) => $q
          ->on('hcm.ticket_id', 'resolved_tickets.old_ticket_id')
          ->whereRaw('hcm.id IN (SELECT MAX(m.id) FROM hidden_chat_messages m join resolved_tickets t on t.old_ticket_id = m.ticket_id WHERE m.content = "Тикет завершён" GROUP BY t.old_ticket_id)')
      )
      ->whereRaw("hcm_s.created_at >= (NOW() - INTERVAL 1 MONTH)")
      ->selectRaw('reasons.name AS name,
      HOUR(AVG(TIMEDIFF(IFNULL(hcm.created_at, NOW()), hcm_s.created_at))) AS time,
      DATE(IFNULL(hcm.created_at, hcm_s.created_at)) AS date')
      ->groupBy('date', 'name');

    $data = Ticket::join('reasons', 'reasons.id', 'tickets.reason_id')
      ->join(
        'hidden_chat_messages AS hcm_s',
        fn($q) => $q
          ->on('hcm_s.ticket_id', 'tickets.id')
          ->whereRaw('hcm_s.id IN (SELECT MAX(m.id) FROM hidden_chat_messages m join tickets t on t.id = m.ticket_id WHERE m.content = "Тикет создан" GROUP BY t.id)')
      )
      ->join(
        'hidden_chat_messages AS hcm',
        fn($q) => $q
          ->on('hcm.ticket_id', 'tickets.id')
          ->whereRaw('hcm.id IN (SELECT MAX(m.id) FROM hidden_chat_messages m join tickets t on t.id = m.ticket_id WHERE m.content LIKE "%пометил тикет как решённый" GROUP BY t.id)')
      )
      ->whereRaw("hcm_s.created_at >= (NOW() - INTERVAL 1 MONTH)")
      ->selectRaw('reasons.name AS name,
      HOUR(AVG(TIMEDIFF(IFNULL(hcm.created_at, NOW()), hcm_s.created_at))) AS time,
      DATE(IFNULL(hcm.created_at, hcm_s.created_at)) AS date')
      ->groupBy('date', 'name')
      ->union($resolved_tickets)
      ->groupBy('date', 'name')
      ->get()->toArray();

    // return $data;
    $filter = array_filter($data, fn($d) => !in_array(null, [$d['name'], $d['date'], $d['time']]));
    return array_values($filter);
  }
  
  public static function getNewTicketsCountByUsers(): array
  {
    $resolved_tickets = ResolvedTicket::join('users', 'users.id', 'resolved_tickets.new_manager_id')
      ->join(
        'hidden_chat_messages AS hcm_s',
        fn($q) => $q
          ->on('hcm_s.ticket_id', 'resolved_tickets.old_ticket_id')
          ->whereRaw('hcm_s.id IN (SELECT MAX(m.id) FROM hidden_chat_messages m join resolved_tickets t on t.old_ticket_id = m.ticket_id WHERE m.content = "Тикет создан" GROUP BY t.old_ticket_id)')
      )
      ->join(
        'hidden_chat_messages AS hcm',
        fn($q) => $q
          ->on('hcm.ticket_id', 'resolved_tickets.old_ticket_id')
          ->whereRaw('hcm.id IN (SELECT MAX(m.id) FROM hidden_chat_messages m join resolved_tickets t on t.old_ticket_id = m.ticket_id WHERE m.content = "Тикет завершён" GROUP BY t.old_ticket_id)')
      )
      ->whereRaw("hcm_s.created_at >= (NOW() - INTERVAL 1 MONTH)")
      ->selectRaw('users.name AS name,
      COUNT(resolved_tickets.id) AS count,
      DATE(IFNULL(hcm.created_at, hcm_s.created_at)) AS date')
      ->groupBy('date', 'name');

    $data = Ticket::join('users', 'users.id', 'tickets.new_manager_id')
      ->join(
        'hidden_chat_messages AS hcm_s',
        fn($q) => $q
          ->on('hcm_s.ticket_id', 'tickets.id')
          ->whereRaw('hcm_s.id IN (SELECT MAX(m.id) FROM hidden_chat_messages m join tickets t on t.id = m.ticket_id WHERE m.content = "Тикет создан" GROUP BY t.id)')
      )
      ->join(
        'hidden_chat_messages AS hcm',
        fn($q) => $q
          ->on('hcm.ticket_id', 'tickets.id')
          ->whereRaw('hcm.id IN (SELECT MAX(m.id) FROM hidden_chat_messages m join tickets t on t.id = m.ticket_id WHERE m.content LIKE "%пометил тикет как решённый" GROUP BY t.id)')
      )
      ->whereRaw("hcm_s.created_at >= (NOW() - INTERVAL 1 MONTH)")
      ->selectRaw('users.name AS name,
      COUNT(tickets.id) AS count,
      DATE(IFNULL(hcm.created_at, hcm_s.created_at)) AS date')
      ->groupBy('date', 'name')
      ->union($resolved_tickets)
      ->groupBy('date', 'name')
      ->get()->toArray();

    $filter = array_filter($data, fn($d) => !in_array(null, [$d['name'], $d['date'], $d['count']]));
    return array_values($filter);
  }
  
  public static function getNewTicketsCountByReasons(): array
  {
    $resolved_tickets = ResolvedTicket::join('reasons', 'reasons.id', 'resolved_tickets.reason_id')
      ->join(
        'hidden_chat_messages AS hcm_s',
        fn($q) => $q
          ->on('hcm_s.ticket_id', 'resolved_tickets.old_ticket_id')
          ->whereRaw('hcm_s.id IN (SELECT MAX(m.id) FROM hidden_chat_messages m join resolved_tickets t on t.old_ticket_id = m.ticket_id WHERE m.content = "Тикет создан" GROUP BY t.old_ticket_id)')
      )
      ->join(
        'hidden_chat_messages AS hcm',
        fn($q) => $q
          ->on('hcm.ticket_id', 'resolved_tickets.old_ticket_id')
          ->whereRaw('hcm.id IN (SELECT MAX(m.id) FROM hidden_chat_messages m join resolved_tickets t on t.old_ticket_id = m.ticket_id WHERE m.content = "Тикет завершён" GROUP BY t.old_ticket_id)')
      )
      ->whereRaw("hcm_s.created_at >= (NOW() - INTERVAL 1 MONTH)")
      ->selectRaw('reasons.name AS name,
      COUNT(resolved_tickets.id) AS count,
      DATE(IFNULL(hcm.created_at, hcm_s.created_at)) AS date')
      ->groupBy('date', 'name');

    $data = Ticket::join('reasons', 'reasons.id', 'tickets.reason_id')
      ->join(
        'hidden_chat_messages AS hcm_s',
        fn($q) => $q
          ->on('hcm_s.ticket_id', 'tickets.id')
          ->whereRaw('hcm_s.id IN (SELECT MAX(m.id) FROM hidden_chat_messages m join tickets t on t.id = m.ticket_id WHERE m.content = "Тикет создан" GROUP BY t.id)')
      )
      ->join(
        'hidden_chat_messages AS hcm',
        fn($q) => $q
          ->on('hcm.ticket_id', 'tickets.id')
          ->whereRaw('hcm.id IN (SELECT MAX(m.id) FROM hidden_chat_messages m join tickets t on t.id = m.ticket_id WHERE m.content LIKE "%пометил тикет как решённый" GROUP BY t.id)')
      )
      ->whereRaw("hcm_s.created_at >= (NOW() - INTERVAL 1 MONTH)")
      ->selectRaw('reasons.name AS name,
      COUNT(tickets.id) AS count,
      DATE(IFNULL(hcm.created_at, hcm_s.created_at)) AS date')
      ->groupBy('date', 'name')
      ->union($resolved_tickets)
      ->groupBy('date', 'name')
      ->get()->toArray();

    $filter = array_filter($data, fn($d) => !in_array(null, [$d['name'], $d['date'], $d['count']]));
    return array_values($filter);
  }

  public static function getResolvedTicketsCountByUsers(): array
  {
    $data = ResolvedTicket::join('users', 'users.id', 'resolved_tickets.new_manager_id')
      ->join(
        'hidden_chat_messages AS hcm_s',
        fn($q) => $q
          ->on('hcm_s.ticket_id', 'resolved_tickets.old_ticket_id')
          ->whereRaw('hcm_s.id IN (SELECT MAX(m.id) FROM hidden_chat_messages m join resolved_tickets t on t.old_ticket_id = m.ticket_id WHERE m.content = "Тикет создан" GROUP BY t.old_ticket_id)')
      )
      ->join(
        'hidden_chat_messages AS hcm',
        fn($q) => $q
          ->on('hcm.ticket_id', 'resolved_tickets.old_ticket_id')
          ->whereRaw('hcm.id IN (SELECT MAX(m.id) FROM hidden_chat_messages m join resolved_tickets t on t.old_ticket_id = m.ticket_id WHERE m.content = "Тикет завершён" GROUP BY t.old_ticket_id)')
      )
      ->whereRaw("hcm_s.created_at >= (NOW() - INTERVAL 1 MONTH)")
      ->selectRaw('users.name AS name,
      COUNT(resolved_tickets.id) AS count,
      DATE(IFNULL(hcm.created_at, hcm_s.created_at)) AS date')
      ->groupBy('date', 'name')
      ->get()->toArray();

    $filter = array_filter($data, fn($d) => !in_array(null, [$d['name'], $d['date'], $d['count']]));
    return array_values($filter);
  }

  public static function getResolvedTicketsCountByReasons(): array
  {
    $data = ResolvedTicket::join('reasons', 'reasons.id', 'resolved_tickets.reason_id')
      ->join(
        'hidden_chat_messages AS hcm_s',
        fn($q) => $q
          ->on('hcm_s.ticket_id', 'resolved_tickets.old_ticket_id')
          ->whereRaw('hcm_s.id IN (SELECT MAX(m.id) FROM hidden_chat_messages m join resolved_tickets t on t.old_ticket_id = m.ticket_id WHERE m.content = "Тикет создан" GROUP BY t.old_ticket_id)')
      )
      ->join(
        'hidden_chat_messages AS hcm',
        fn($q) => $q
          ->on('hcm.ticket_id', 'resolved_tickets.old_ticket_id')
          ->whereRaw('hcm.id IN (SELECT MAX(m.id) FROM hidden_chat_messages m join resolved_tickets t on t.old_ticket_id = m.ticket_id WHERE m.content = "Тикет завершён" GROUP BY t.old_ticket_id)')
      )
      ->whereRaw("hcm_s.created_at >= (NOW() - INTERVAL 1 MONTH)")
      ->selectRaw('reasons.name AS name,
      COUNT(resolved_tickets.id) AS count,
      DATE(IFNULL(hcm.created_at, hcm_s.created_at)) AS date')
      ->groupBy('date', 'name')
      ->get()->toArray();

    $filter = array_filter($data, fn($d) => !in_array(null, [$d['name'], $d['date'], $d['count']]));
    return array_values($filter);
  }
}