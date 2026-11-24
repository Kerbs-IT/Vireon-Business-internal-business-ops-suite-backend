<?php

namespace App\Models;

use App\Observers\TaskObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

#[ObservedBy([TaskObserver::class])]
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
        'due_date',
        'completed_at'
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
