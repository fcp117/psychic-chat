<?php

namespace Database\Seeders;

use App\Models\TarotCard;
use Illuminate\Database\Seeder;

class TarotCardSeeder extends Seeder
{
    public function run(): void
    {
        $cards = [
            ['the-fool', 'The Fool', 'Beginnings, Curiosity, Openness',
                'The Fool invites you to meet a new beginning with curiosity. You do not need every answer before taking a thoughtful first step. Openness can live alongside preparation and sensible boundaries.',
                'Choose one small, low-risk step toward something you have wanted to explore. Gather what you need, then give yourself permission to be a beginner.',
                'What could I try today if I did not expect myself to get it perfect?'],
            ['strength', 'Strength', 'Courage, Patience, Compassion',
                'Strength offers a reminder that courage does not always look forceful. A calm response, a clear boundary, or asking for support can express quiet confidence. Meet yourself with the kindness you would offer a friend.',
                'Pause before responding to one difficult moment today. Notice what you feel, take a breath, and choose a response that respects both you and the other person.',
                'Where might gentleness help me more than pushing harder?'],
            ['the-star', 'The Star', 'Hope, Renewal, Trust',
                'The Star makes space for hope without asking you to ignore what is difficult. Small sources of comfort can help you reconnect with what matters. You are allowed to rebuild at your own pace.',
                'Make time for one restoring activity: step outside, write a few lines, or reach out to someone you trust. Notice one thing that still feels possible.',
                'What small source of hope would I like to nurture today?'],
            ['the-sun', 'The Sun', 'Joy, Warmth, Clarity',
                'The Sun invites you to notice what brings warmth and clarity to your day. Joy does not have to be earned through constant productivity. Let a simple pleasure or an honest connection have your attention.',
                'Acknowledge a small success and share appreciation with someone. Leave a little room for an activity you enjoy, without needing it to achieve anything.',
                'What is already bringing light into my life that I could appreciate more?'],
            ['the-hermit', 'The Hermit', 'Reflection, Stillness, Perspective',
                'The Hermit suggests making room to hear your own thoughts beneath outside noise. A quiet pause can help you identify what matters to you. Solitude can be restorative while connection and support remain available.',
                'Set aside a few distraction-free minutes. Write down one concern, what you know about it, and one question you would like to explore without rushing an answer.',
                'What do I notice when I stop looking for an immediate answer?'],
        ];
        foreach ($cards as [$slug, $name, $keywords, $meaning, $guidance, $reflection]) {
            // Repeat deployments never overwrite an administrator's edits.
            TarotCard::firstOrCreate(['slug' => $slug], compact('name', 'keywords', 'meaning', 'guidance', 'reflection') + [
                'category' => 'Major Arcana', 'image_path' => 'images/tarot/'.$slug.'.png', 'is_active' => true,
            ]);
        }
    }
}
