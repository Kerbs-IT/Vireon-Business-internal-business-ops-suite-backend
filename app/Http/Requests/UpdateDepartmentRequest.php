<?php

namespace App\Http\Requests;

use App\Models\Departments;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

class UpdateDepartmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        Log::info('route params', [
            'params' => $this->route()?->parameters(),
            'uri' => $this->route()?->uri(),
        ]);
        
        return [
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('departments', 'name')->ignore($this->route('department')),
            ],
            'manager_id' => ['nullable', 'integer', 'exists:users,id'],
        ];
    }
}
