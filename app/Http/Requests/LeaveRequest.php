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
        $startRules = ['required', 'date'];

        if ($this->isMethod('post')) {
            $startRules[] = 'after_or_equal:today';
        } else {
            // on edit, only enforce "not in the past" if the date actually changed
            $current = $this->route('leaveRequest');
            $unchanged = $current
                && $this->input('start_date') === \Illuminate\Support\Carbon::parse($current->start_date)->toDateString();

            if (!$unchanged) {
                $startRules[] = 'after_or_equal:today';
            }
        }

        return [
            'leave_type_id' => ['required', 'integer', 'exists:leave_types,id'],
            'start_date'    => $startRules,
            'end_date'      => ['required', 'date', 'after_or_equal:start_date'],
            'reason'        => ['required', 'string', 'max:255'],
        ];
    }
}
