<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class CreditPurchase extends Model {
    public $incrementing=false;
    protected $keyType='string';
    protected $guarded=[];
    protected $casts=['amount'=>'integer','credits'=>'integer','paid_at'=>'datetime'];
}
