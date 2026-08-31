<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Campaign extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_id',
        'whatsapp_account_id',
        'sender_number',
        'name',
        'message',
        'media_path',
        'media_name',
        'media_paths',
        'status',
        'total',
        'sent',
        'success',
        'failed',
        'pending',
        'delay_min',
        'delay_max',
        'error',
        'started_at',
        'finished_at',
    ];

    protected $casts = [
        'media_paths' => 'array',
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
    ];

    public const STATUS_DRAFT = 'draft';
    public const STATUS_RUNNING = 'running';
    public const STATUS_PAUSED = 'paused';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_STOPPED = 'stopped';
    public const STATUS_FAILED = 'failed';

    public function messages(): HasMany
    {
        return $this->hasMany(CampaignMessage::class)->orderBy('id');
    }
}