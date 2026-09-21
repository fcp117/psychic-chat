<?php
namespace App\Http\Controllers\Auth;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
class VerificationEmailController extends Controller {
 public function update(Request $r) {
  abort_if($r->user()->hasVerifiedEmail(),403);
  $r->merge(['email'=>strtolower(trim((string)$r->email))]);
  $v=$r->validate(['email'=>['required','email','max:255',Rule::unique('users')->ignore($r->user()->id)],'password'=>['required','current_password']]);
  DB::transaction(function()use($r,$v){
   $u=User::whereKey($r->user()->id)->lockForUpdate()->firstOrFail();abort_if($u->hasVerifiedEmail(),403);
   if($u->email!==$v['email']) {$u->email=$v['email'];$u->save();DB::table('email_verification_codes')->where('user_id',$u->id)->delete();}
  });
  try{$r->user()->fresh()->sendEmailVerificationNotification();}catch(ValidationException $e){return redirect()->route('verification.notice')->withErrors($e->errors());}
  return redirect()->route('verification.notice')->with('status','verification-code-sent');
 }
}
