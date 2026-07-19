<?php

namespace App\Http\Controllers;

use App\Models\ChatSession;
use App\Models\Message;
use App\Events\MessageSent;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ChatController extends Controller
{
    // Load the chat room
    public function show(ChatSession $chatSession, Request $request)
    {
        // Ensure only the client or counselor involved can access this
        if ($request->user()->id !== $chatSession->client_id && $request->user()->id !== $chatSession->counselor_id) {
            abort(403, 'Unauthorized action.');
        }

        return Inertia::render('Chat/Room', [
            'session' => $chatSession,
            'initialMessages' => $chatSession->messages()->with('sender:id,name')->get(),
            'currentUser' => $request->user(),
        ]);
    }

    // Save and broadcast a new message
    public function store(Request $request, ChatSession $chatSession)
    {
        $request->validate(['content' => 'required|string']);

        $message = $chatSession->messages()->create([
            'sender_id' => $request->user()->id,
            'content' => $request->content,
        ]);

        // Eager load sender for the broadcast payload
        $message->load('sender:id,name');

        // Broadcast to the other person in the room
        broadcast(new MessageSent($message))->toOthers();

        return response()->json(['message' => $message]);
    }
}