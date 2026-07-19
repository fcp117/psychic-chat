<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use App\Models\User;
use App\Models\ChatSession;
use App\Models\Message;


class ChatSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 1. Fetch the users you already seeded
        $client = User::where('role', 'client')->first();
        $counselor = User::where('role', 'counselor')->first();

        // Safety check just in case the database is empty
        if (!$client || !$counselor) {
            $this->command->error('Users not found. Please run UserSeeder first.');
            return;
        }

        // 2. Create an active chat session
        $session = ChatSession::create([
            'client_id' => $client->id,
            'counselor_id' => $counselor->id,
            'status' => 'active', 
            'started_at' => Carbon::now()->subMinutes(5),
            'ended_at' => null,
        ]);

        // 3. Populate the room with some initial messages
        Message::create([
            'chat_session_id' => $session->id,
            'sender_id' => $client->id,
            'content' => 'Hello! I was hoping to get a reading today.',
            'created_at' => Carbon::now()->subMinutes(4),
        ]);

        Message::create([
            'chat_session_id' => $session->id,
            'sender_id' => $counselor->id,
            'content' => 'Welcome. I am here to help. What is on your mind?',
            'created_at' => Carbon::now()->subMinutes(3),
        ]);

        Message::create([
            'chat_session_id' => $session->id,
            'sender_id' => $client->id,
            'content' => 'I have a big decision to make regarding my career path.',
            'created_at' => Carbon::now()->subMinutes(2),
        ]);

        $this->command->info('Chat session seeded successfully! Session ID is: ' . $session->id);
    }
}
