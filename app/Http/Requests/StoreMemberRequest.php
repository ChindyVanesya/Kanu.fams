<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreMemberRequest extends FormRequest
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
            'member_code' => ['required', 'string', 'max:255', 'unique:members,member_code'],
            'name' => ['required', 'string', 'max:255'],
            'birth_date' => ['nullable', 'date'],
            'phone' => ['nullable', 'string', 'max:32'],
            'has_jersey' => ['required', 'boolean'],
            'registration_payment_amount' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'status' => ['sometimes', 'in:active,inactive'],
        ];
    }
}
