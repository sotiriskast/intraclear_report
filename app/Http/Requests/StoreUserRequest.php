<?php
namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'user_type' => ['required', 'string', Rule::in(['admin', 'super-admin', 'merchant'])],
            'merchant_id' => ['nullable', 'exists:merchants,id', 'required_if:user_type,merchant'],
            'active' => ['boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'merchant_id.required_if' => 'Merchant selection is required when user type is merchant.',
            'password.confirmed' => 'Password confirmation does not match.',
        ];
    }
}
