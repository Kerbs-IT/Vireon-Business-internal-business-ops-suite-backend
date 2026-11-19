<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Departments extends Model
{
    //
    use SoftDeletes;

    protected $fillable = [
        'name',
        'manager_id',
        
    ];

    public function manager(){
        return $this -> belongsTo(User::class,'manager_id','id');
    }

    public function users(){
        return $this -> hasMany(User::class,'department_id');
    }
}
