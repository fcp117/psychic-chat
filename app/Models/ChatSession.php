<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ChatSession extends Model
{
    use HasFactory;

    // Allow all fields to be mass-assigned
    protected $guarded = [];

    // Cast the datetime columns so Laravel handles them properly
    protected $casts = [
        'started_at' => 'datetime',
        'ended_at' => 'datetime',
        'agreed_at' => 'datetime', 'client_seen_at' => 'datetime', 'counselor_seen_at' => 'datetime',
        'agreed_rate' => 'integer', 'billed_units' => 'integer', 'billed_seconds' => 'integer',
    ];

    public function conversationQuery() {
        return static::where('client_id',$this->client_id)->where('counselor_id',$this->counselor_id);
    }
    public function conversationId(): int { return (int) $this->conversationQuery()->min('id'); }
    public function conversationMessages() {
        return Message::whereIn('chat_session_id',$this->conversationQuery()->select('id'));
    }

    public function client()
    {
        return $this->belongsTo(User::class, 'client_id');
    }

    public function counselor()
    {
        return $this->belongsTo(User::class, 'counselor_id');
    }

    public function messages()
    {
        return $this->hasMany(Message::class);
    }
}
