<?php
namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class ProfilePhotoController extends Controller
{
    public function store(Request $request)
    {
        $photo = $request->file('photo');
        if ($photo && !$photo->isValid()) {
            $message = match ($photo->getError()) {
                UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'This image exceeds the server upload limit. Choose a photo smaller than 2 MB.',
                UPLOAD_ERR_PARTIAL => 'The upload was interrupted. Select the photo again and retry.',
                UPLOAD_ERR_NO_TMP_DIR, UPLOAD_ERR_CANT_WRITE => 'The server could not save the upload. Please contact an administrator.',
                default => 'The upload did not finish. Select the photo again and retry.',
            };
            throw ValidationException::withMessages(['photo' => $message]);
        }
        $request->validate(['photo' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048', 'dimensions:max_width=4096,max_height=4096']], [
            'photo.dimensions' => 'Choose a photo no larger than 4096 by 4096 pixels.',
            'photo.max' => 'Choose a photo smaller than 2 MB.',
            'photo.image' => 'This file could not be read as an image. Choose a JPG, PNG, or WebP photo.',
        ]);
        $path = $request->file('photo')->store('profile-photos', 'local');
        if (!$path) throw ValidationException::withMessages(['photo' => 'The photo could not be saved. Please try again.']);
        try {
            $old = DB::transaction(function () use ($request, $path) {
                $user = User::whereKey($request->user()->id)->lockForUpdate()->firstOrFail();
                $old = $user->profile_photo_path;
                $user->profile_photo_path = $path;
                $user->save();
                return $old;
            });
        } catch (\Throwable $e) { Storage::disk('local')->delete($path); throw $e; }
        if ($old) Storage::disk('local')->delete($old);
        return back()->with('success', 'Profile photo updated.');
    }

    public function destroy(Request $request)
    {
        $old = DB::transaction(function () use ($request) {
            $user = User::whereKey($request->user()->id)->lockForUpdate()->firstOrFail();
            $old = $user->profile_photo_path;
            $user->profile_photo_path = null;
            $user->save();
            return $old;
        });
        if ($old) Storage::disk('local')->delete($old);
        return back()->with('success', 'Profile photo removed.');
    }

    public function show(User $user)
    {
        abort_unless($user->profile_photo_path && Storage::disk('local')->exists($user->profile_photo_path), 404);
        return Storage::disk('local')->response($user->profile_photo_path, null, [
            'Cache-Control' => 'private, no-cache', 'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'",
        ]);
    }
}