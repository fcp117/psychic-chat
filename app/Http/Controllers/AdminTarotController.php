<?php

namespace App\Http\Controllers;

use App\Models\TarotCard;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class AdminTarotController extends Controller
{
    public function index()
    {
        return Inertia::render('Admin/TarotCards', ['cards' => TarotCard::orderBy('id')->get()]);
    }

    public function store(Request $request)
    {
        return $this->save($request, new TarotCard);
    }

    public function update(Request $request, TarotCard $card)
    {
        return $this->save($request, $card);
    }

    private function save(Request $request, TarotCard $card)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'category' => ['required', 'string', 'max:60'],
            'keywords' => ['required', 'string', 'max:255'],
            'meaning' => ['required', 'string', 'max:3000'],
            'guidance' => ['required', 'string', 'max:2000'],
            'reflection' => ['required', 'string', 'max:1000'],
            'is_active' => ['required', 'boolean'],
            'image' => [$card->exists ? 'nullable' : 'required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120', 'dimensions:max_width=4096,max_height=4096'],
        ]);
        unset($data['image']);
        // Never overwrite old files: existing reading snapshots keep their artwork.
        if ($request->hasFile('image')) $data['image_path'] = $request->file('image')->store('tarot', 'public');
        if (!$card->exists) $data['slug'] = Str::slug($data['name']).'-'.Str::lower(Str::random(8));
        DB::transaction(function () use ($card, $data, $request) {
            $before = $card->exists ? $card->only(['name', 'is_active', 'image_path']) : null;
            $card->fill($data)->save();
            DB::table('admin_audits')->insert([
                'actor_id' => $request->user()->id, 'action' => 'tarot.save', 'target' => 'tarot:'.$card->id,
                'before' => $before ? json_encode($before) : null,
                'after' => json_encode($card->only(['name', 'is_active', 'image_path'])),
                'created_at' => now(), 'updated_at' => now(),
            ]);
        });
        return redirect()->route('admin.tarot')->with('success', 'Tarot card saved. Existing readings are unchanged.');
    }
}
