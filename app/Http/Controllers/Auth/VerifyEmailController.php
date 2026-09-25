<?php
namespace App\Http\Controllers\Auth;
use App\Http\Controllers\Controller;
use App\Services\EmailVerificationCodes;
use Illuminate\Http\Request;

class VerifyEmailController extends Controller 
{
    public function __invoke(Request $request,EmailVerificationCodes $codes) 
    {
        $data=$request->validate(['code'=>['required','string','regex:/^[0-9]{6}$/']]);
        $codes->verify($request->user(),$data['code']);
        return redirect()->route('home');
    }
}
