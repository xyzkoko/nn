<?php

namespace App\Model;

use Illuminate\Database\Eloquent\Model;

class UserBet extends Model
{
    //use \App\Traits\HasCompositePrimaryKey;

    protected $primaryKey = 'user_id';         // 设置组合主键

    public $keyType = 'string';
    public $incrementing = false;
}
