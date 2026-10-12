<?php
namespace App\Http\Controllers;
use Illuminate\Http\Request;
class MusicPreferenceController extends Controller {
 public function show(Request $request) {
  return response()->json(['muted' => (bool) $request->user()->music_muted])->header('Cache-Control', 'private, no-store');
 }
 public function update(Request $request) {
  $data = $request->validate(['muted' => 'required|boolean']);
  $request->user()->forceFill(['music_muted' => $data['muted']])->save();
  return $this->show($request);
 }
}
