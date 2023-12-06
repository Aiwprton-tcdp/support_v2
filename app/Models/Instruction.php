<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Instruction extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'content',
        'reason_id',
    ];

    protected $hidden = [
        'created_at',
        'updated_at',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    protected $table = 'instructions';
    
    public function checked()
    {
        return $this->hasMany(CheckedInstruction::class, 'instruction_id', 'id');
    }
}
