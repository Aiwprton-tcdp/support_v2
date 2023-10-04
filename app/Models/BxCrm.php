<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BxCrm extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'acronym',
        'domain',
        'app_domain',
        'marketplace_id',
        'webhook_id',
    ];

    protected $hidden = [
        'created_at',
        'updated_at',
        'deleted_at',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    protected $table = 'bx_crms';
}