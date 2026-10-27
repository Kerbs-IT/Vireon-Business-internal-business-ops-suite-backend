<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Override;

class Leave_requests extends Model
{
    //

    use HasFactory;

    protected $fillable = [
        'user_id',
        'leave_type_id',
        'start_date',
        'end_date',
        'reason',
        'status',
        'approved_by',
        'approved_at'
    ];

 
    protected function casts():array
    {
        return [
            'start_date'=>'date',
            'end_date' =>'date',
            'approved_at' => 'datetime'
        ];
    }

    public function user(){
        return $this-> belongsTo(User::class,'user_id','id');
    }

    public function leave_type(){
        return $this-> belongsTo(Leave_types::class,'leave_type_id','id');
    }

    public function approved_by_user(){
        return $this -> belongsTo(User::class,'approved_by','id');
    }
}
