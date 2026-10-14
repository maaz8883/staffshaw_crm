<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TeamTrelloIntegration extends Model
{
    protected $fillable = [
        'team_id',
        'api_key',
        'api_token',
        'account_email',
        'trello_member_id',
        'trello_username',
        'trello_full_name',
        'is_active',
        'connected_by',
        'connected_at',
        'last_verified_at',
    ];

    protected $casts = [
        'api_key' => 'encrypted',
        'api_token' => 'encrypted',
        'is_active' => 'boolean',
        'connected_at' => 'datetime',
        'last_verified_at' => 'datetime',
    ];

    protected $hidden = [
        'api_key',
        'api_token',
    ];

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function connectedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'connected_by');
    }

    public function isReady(): bool
    {
        return $this->is_active
            && filled($this->api_key)
            && filled($this->api_token);
    }

    public function displayAccount(): string
    {
        return $this->account_email
            ?: ($this->trello_full_name ?: ($this->trello_username ? '@'.$this->trello_username : 'Connected'));
    }
}
