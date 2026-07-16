<?php

use Illuminate\Support\Facades\Broadcast;

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