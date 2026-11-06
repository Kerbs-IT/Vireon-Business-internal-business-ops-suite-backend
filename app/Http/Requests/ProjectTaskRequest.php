<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class ProjectTaskRequest extends FormRequest
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
    protected function prepareForValidation(): void
    {
        // status has no DB default, so supply one if the form didn't send it
        $this->mergeIfMissing(['status' => 'todo']);
    }

    public function rules(): array
    {
        return [
            'project_id'  => ['required', 'integer', 'exists:projects,id'],
            'title'       => ['required', 'string', 'max:255'],
            'description' => ['required', 'string'],
            'assigned_to' => ['required', 'integer', 'exists:users,id'],
            'status'      => ['required', Rule::in(['todo', 'in_progress', 'review', 'done'])],
            'priority'    => ['required', Rule::in(['low', 'medium', 'high'])],
            'due_date'    => ['nullable', 'date', 'after_or_equal:today'],
        ];
    }

    public function messages(): array
    {
        return [
            'project_id.exists' => 'The selected project does not exist.',
            'assigned_to.exists' => 'The selected team member does not exist.',
        ];
    }
}
