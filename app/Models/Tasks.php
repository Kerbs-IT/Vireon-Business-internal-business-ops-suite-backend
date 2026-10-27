<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Tasks extends Model
{
    //
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
}
