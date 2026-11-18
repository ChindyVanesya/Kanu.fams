<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class AttendanceReportRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'type' => ['required', 'in:weekly,monthly'],
            'period' => [
                'required',
                'date_format:'.($this->input('type') === 'monthly' ? 'Y-m' : 'Y-m-d'),
            ],
            'search' => ['nullable', 'string', 'max:100'],
        ];
    }
}
