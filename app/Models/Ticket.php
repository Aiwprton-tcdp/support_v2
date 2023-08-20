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
    'reason_id',
    'weight',
    'active',
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
    string $search
  ): void {
    $query->join('reasons', 'reasons.id', 'tickets.reason_id')
      ->rightJoin('users AS u', 'u.crm_id', 'tickets.manager_id')
      ->rightJoin('users AS m', 'm.crm_id', 'tickets.manager_id')
      ->leftJoin('participants', function ($q) use ($user_id) {
        $q->on('participants.ticket_id', 'tickets.id')
          ->where('participants.user_crm_id', $user_id);
      })
      // ->whereNotNull('tickets.id')
      ->whereIn('tickets.id', $tickets_ids)
      ->when($user_id != 0, function ($q) use ($user_id) {
        $q->whereManagerId($user_id)
          ->orWhere('user_id', $user_id)
          ->orWhere('participants.user_crm_id', $user_id);
      })
      ->when(!empty($search), function ($q) use ($search) {
        $name = mb_strtolower(trim(preg_replace('/[^А-яA-z -]+/iu', '', $search)));

        $q->when(!empty($name), function ($r) use ($name) {
          $r->whereRaw('LOWER(u.name) LIKE ?', ["%{$name}%"])
            ->orWhereRaw('LOWER(m.name) LIKE ?', ["%{$name}%"]);
        });
      });
  }
}