<?php
namespace App\Services;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
class IncidentReporter {
 public static function record(\Throwable $e): void {
  try {
   if(app()->runningUnitTests())return;
   $r=app()->bound('request')?request():null;
   $route=$r?->route()?->getName();
   $key='incident:'.hash('sha256',get_class($e).'|'.$route);
   if(RateLimiter::tooManyAttempts($key,1))return;
   RateLimiter::hit($key,60);
   DB::table('system_incidents')->insert(['id'=>(string)Str::uuid(),'type'=>class_basename($e),'route'=>$route,'method'=>$r?->method(),'created_at'=>now()]);
  } catch(\Throwable) { /* Keep reporting failures from breaking the original response. */ }
 }
}
