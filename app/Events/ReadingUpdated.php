<?php
namespace App\Events;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use App\Models\ChatSession;
class ReadingUpdated implements ShouldBroadcastNow {
    public int $conversationId;
    public int $clientId;
    public int $counselorId;
    public string $status;
    public function __construct(ChatSession $session) {
        $this->conversationId=$session->conversationId();
        $this->clientId=$session->client_id; $this->counselorId=$session->counselor_id; $this->status=$session->status;
    }
    public function broadcastOn(): array { return [new PrivateChannel('chat.'.$this->conversationId),new PrivateChannel('App.Models.User.'.$this->clientId),new PrivateChannel('App.Models.User.'.$this->counselorId)]; }
    public function broadcastAs(): string { return 'ReadingUpdated'; }
}
