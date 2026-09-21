<?php
namespace App\Services;
use Illuminate\Support\Facades\DB;
class AppNotifications {
 public static function send(int $userId,string $key,string $title,string $body,string $url): void {
  DB::table('app_notifications')->insertOrIgnore(['user_id'=>$userId,'event_key'=>$key,'title'=>$title,'body'=>$body,'url'=>$url,'created_at'=>now(),'updated_at'=>now()]);
 }
 public static function reading(\App\Models\ChatSession $s): void {
  $url=route('chat.room',$s->conversationId(),false);
  if($s->status==='pending') self::send($s->counselor_id,'reading:'.$s->id.':pending','New reading request','A user requested a reading. Open the conversation to review it.',$url);
  if($s->status==='active') self::send($s->client_id,'reading:'.$s->id.':active','Your reading was accepted','Your counselor accepted. Open the conversation to continue.',$url);
  if(in_array($s->status,['completed','rejected'])) foreach([$s->client_id,$s->counselor_id] as $id) self::send($id,'reading:'.$s->id.':'.$s->status,'Reading ended',$s->end_reason==='insufficient_credits'?'The reading stopped because the user’s balance ran out.':'Your conversation history remains available. Open it to review the reading or request to continue.',$url);
 }
}
