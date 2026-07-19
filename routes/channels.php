<?php

use Illuminate\Support\Facades\Broadcast;
use App\Models\ChatSession;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

Broadcast::channel('counselors.online', function ($user) {
    // Check if the user is authenticated. 
    // If they are, return the data you want the Vue frontend to see.
    if ($user) {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'rate_per_minute' => $user->rate_per_minute,
            'role' => $user->role,
        ];
    }
    
    // Returning null or false denies access
    return null; 
});

Broadcast::channel('chat.{sessionId}', function ($user, $sessionId) {
    $session = ChatSession::find($sessionId);
    
    if (!$session) return false;

    // Only grant access if the user is the client or counselor for this specific session
    return $user->id === $session->client_id || $user->id === $session->counselor_id;
});