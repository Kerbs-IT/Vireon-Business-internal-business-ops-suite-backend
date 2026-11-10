<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Tasks extends Model
{
    //
    use SoftDeletes;
    protected $fillable = [
        'project_id',
        'title',
        'description',
        'assigned_to',
        'status',
        'priority',
        'due_date'
    ];

    public function project(){
       return $this->belongsTo(Projects::class,'project_id','id');
    }

    public function assignee(){
        return $this -> belongsTo(User::class,'assigned_to','id');
    }
    public function comments(){
        return $this->hasMany(Comments::class,'task_id')->oldest();
    }
}
