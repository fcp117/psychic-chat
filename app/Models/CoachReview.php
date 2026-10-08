<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
class CoachReview extends Model {
    use SoftDeletes;
    protected $guarded = ['id'];
    protected $casts = ['rating'=>'integer','publish_consent'=>'boolean','highlighted'=>'boolean','reviewed_at'=>'datetime'];
}
