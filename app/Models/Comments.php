<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Comments extends Model
{
    //
    protected $fillable = [
        'task_id',
        'user_id',
        'comments'
    ];

    public function task(){
        return $this-> belongsTo(Tasks::class,'task_id','id');
    }
    public function user(){
        return $this-> belongsTo(User::class,'user_id','id');
    }
}
