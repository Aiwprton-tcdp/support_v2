<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Ticket extends Model
{
  use HasFactory;

  protected $fillable = [
    'user_id',
    'manager_id',
    'new_user_id',
    'new_manager_id',
    'crm_id',
    'reason_id',
    'weight',
    'active',
    'anydesk',
    'created_at',
  ];

  protected $hidden = [
    'updated_at',
  ];

  protected $casts = [
    'created_at' => 'datetime',
    'updated_at' => 'datetime',
  ];

  protected $table = 'tickets';

  public function scopeFilter(
    \Illuminate\Database\Eloquent\Builder $query,
    int $user_id,
    array $tickets_ids,
    array $weights,
    array $users,
    array $reasons,
    array $dates,
    bool $active,
    bool $inactive,
    string $search
  ): void {
    $query->join('reasons', 'reasons.id', 'tickets.reason_id')
      // ->rightJoin('users AS u', 'u.id', 'tickets.user_id')
      // ->rightJoin('users AS m', 'm.id', 'tickets.manager_id')
      // ->leftJoin('participants', 'participants.ticket_id', 'tickets.id')
      ->leftJoin('messages', function ($q) {
        $q->on('messages.ticket_id', 'tickets.id')
          ->whereRaw('messages.id IN (SELECT MIN(m.id) FROM messages m join tickets t on t.id = m.ticket_id GROUP BY t.id)');
      })
      ->leftJoin('hidden_chat_messages', function ($q) {
        $q->on('hidden_chat_messages.ticket_id', 'tickets.id')
          ->whereRaw('hidden_chat_messages.id IN (SELECT MAX(m.id) FROM hidden_chat_messages m join tickets t on t.id = m.ticket_id WHERE m.content LIKE "%пометил тикет как решённый" GROUP BY t.id)');
      })
      ->whereNotNull('tickets.id')
      ->when($weights[0] != 0, fn($q) => $q->whereIn('tickets.weight', $weights))
      ->when(!empty($reasons[0]), fn($q) => $q->whereIn('tickets.reason_id', $reasons))
      // ->when(
      //   $active || $inactive,
      //   fn($r) => $r
      //     ->where('tickets.active', $active)
      //     ->orWhere('tickets.active', !$inactive)
      // )
      ->when(
        $active || $inactive,
        fn($r) => $r->where(
          fn($e) => $e
            ->where('tickets.active', $active)
            ->orWhere('tickets.active', !$inactive)
        )
      )
      ->when(
        !$active && !$inactive,
        fn($r) => $r
          ->whereNot('tickets.active', $active)
          ->whereNot('tickets.active', !$inactive)
      )
      ->when(
        !empty($users[0]),
        fn($r) => $r->where(
          fn($e) => $e->whereIn('tickets.new_manager_id', $users)
            ->orWhereIn('tickets.new_user_id', $users)
            // ->orWhereIn('participants.user_crm_id', $users)
        )
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