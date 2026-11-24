<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class Activities extends Model
{
    //
    public const UPDATED_AT = null;
    protected $fillable = [
        'user_id',
        'action',
        'subject_type',   // missing
        'subject_id',     // missing
        'subject_label',
        'department_id',
    ];

    // a small helper on the Activity model
    public static function log(string $action, Model $subject, string $label, ?int $departmentId = null): void
    {
        static::create([
            'user_id'       => Auth::user()->id,
            'action'        => $action,
            'subject_type'  => $subject->getMorphClass(),
            'subject_id'    => $subject->getKey(),
            'subject_label' => $label,
            'department_id' => $departmentId ?? Auth::user()?->department_id,
        ]);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function subject()
    {
        return $this->morphTo();
    }
}
