<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Booking extends Model {
 protected $guarded=[];
 protected $casts=['starts_at'=>'datetime','ends_at'=>'datetime','client_joined_at'=>'datetime','coach_joined_at'=>'datetime','reviewed_at'=>'datetime','minutes'=>'integer','client_id'=>'integer','coach_id'=>'integer','penalty_units'=>'integer'];
 public function client(){return $this->belongsTo(User::class,'client_id');}
 public function coach(){return $this->belongsTo(User::class,'coach_id');}
 public function session(){return $this->hasOne(ChatSession::class);}
}
