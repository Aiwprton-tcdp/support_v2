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
}
