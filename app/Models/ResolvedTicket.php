<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ResolvedTicket extends Model
{
  use HasFactory;

  protected $fillable = [
    'old_ticket_id',
    'user_id',
    'manager_id',
    'new_user_id',
    'new_manager_id',
    'crm_id',
    'reason_id',
    'weight',
    'mark',
    'created_at',
  ];

  protected $hidden = [
    'updated_at',
  ];

  protected $casts = [
    'created_at' => 'datetime',
    'updated_at' => 'datetime',
  ];

  protected $table = 'resolved_tickets';

  public function scopeFilter(
    \Illuminate\Database\Eloquent\Builder $query,
    int $user_id,
    array $tickets_ids,
    array $weights,
    array $users,
    array $reasons,
    array $dates,
    string $search
  ): void {
    $query->join('reasons', 'reasons.id', 'resolved_tickets.reason_id')
      // ->rightJoin('users AS u', 'u.crm_id', 'resolved_tickets.manager_id')
      // ->rightJoin('users AS m', 'm.crm_id', 'resolved_tickets.manager_id')
      // ->rightJoin('users AS u', 'u.id', 'resolved_tickets.new_user_id')
      // ->rightJoin('users AS m', 'm.id', 'resolved_tickets.new_manager_id')
      // ->leftJoin('participants', 'participants.ticket_id', 'resolved_tickets.old_ticket_id')
      ->leftJoin('messages', function ($q) {
        $q->on('messages.ticket_id', 'resolved_tickets.old_ticket_id')
          ->whereRaw('messages.id IN (SELECT MIN(m.id) FROM messages m join resolved_tickets t on t.old_ticket_id = m.ticket_id GROUP BY t.old_ticket_id)');
      })
      ->leftJoin('hidden_chat_messages', function ($q) {
        $q->on('hidden_chat_messages.ticket_id', 'resolved_tickets.old_ticket_id')
          ->whereRaw('hidden_chat_messages.id IN (SELECT MAX(m.id) FROM hidden_chat_messages m join resolved_tickets t on t.old_ticket_id = m.ticket_id WHERE m.content LIKE "Тикет завершён" GROUP BY t.old_ticket_id)');
      })
      ->whereNotNull('resolved_tickets.old_ticket_id')
      // ->when($tickets_ids[0] != 0, fn($q) => $q->whereIn('resolved_tickets.old_ticket_id', $tickets_ids))
      ->when($weights[0] != 0, fn($q) => $q->whereIn('resolved_tickets.weight', $weights))
      ->when(!empty($reasons[0]), fn($q) => $q->whereIn('resolved_tickets.reason_id', $reasons))
      ->when(
        !empty($users[0]),
        fn($q) => $q->whereIn('resolved_tickets.new_manager_id', $users)
          ->orWhereIn('resolved_tickets.new_user_id', $users)
          // ->orWhereIn('participants.user_crm_id', $users)
      )
      ->when($dates[0] != '1970-01-01', fn($q) => $q->whereBetween('messages.created_at', $dates));
      // ->when(!empty($search), function ($q) use ($search) {
      //   $name = mb_strtolower(trim(preg_replace('/[^А-яA-z -]+/iu', '', $search)));

      //   $q->when(!empty($name), function ($r) use ($name) {
      //     $r->whereRaw('LOWER(u.name) LIKE ?', ["%{$name}%"])
      //       ->orWhereRaw('LOWER(m.name) LIKE ?', ["%{$name}%"]);
      //   });
      // });
  }
}