<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class ProjectRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return in_array($this->user()?->role_id, [1, 2]);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            //
            'name' => ['required','string','max:50'],
            'description' => ['required','string','max:1000'],
            'status' => ['required', Rule::in(['active','completed','on_hold',])],
            'end_date' => ['nullable','date', $this->isMethod('post') ? 'after_or_equal:today' : 'sometimes',],
            'members'   => ['required', 'array', 'min:1'],
            'members.*' => [
                'integer',
                Rule::exists('users', 'id')->where(function ($q) {
                    $user = $this->user();
                    $q->where('role_id', '!=', 1); // never admins
                    if ($user->role_id == 2) {
                        $q->where('department_id', $user->department_id);
                    }
                }),
            ],
        ];
    }
}
