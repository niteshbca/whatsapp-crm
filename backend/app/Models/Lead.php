<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Lead extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_id',
        'name',
        'phone',
        'email',
        'source',
        'stage',
        'notes',
        'value',
        'last_contacted_at',
        'owner_name',
    ];

    protected $casts = [
        'last_contacted_at' => 'datetime',
    ];
}
