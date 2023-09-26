<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Message extends Model
{
    use HasFactory;

    protected $fillable = [
        'content',
        'user_crm_id',
        'new_user_id',
        'ticket_id',
        'created_at',
        'updated_at',
    ];

    protected $hidden = [
        'deleted_at',
    ];
    
    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];
    
    protected $table = 'messages';

    public function attachments()
    {
        return $this->hasMany(Attachment::class);
    }
}
