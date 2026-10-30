<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class LeaveRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user() !== null;
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
            'user_id' => ['required', 'exists:users,id'],
            'leave_type_id' => ['required','integer','exists:leave_types,id'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date'],
            'reason' => ['required','string'],
            'status' => ['required',Rule::in(['pending','approved','rejected'])],
            'approved_by' => ['nullable','integer','exists:users,id'],
            'comments' => ['nullable','string']
        ];
    }
}
