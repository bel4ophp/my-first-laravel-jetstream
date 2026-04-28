<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreUserRequest extends FormRequest
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
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array|string>
     */
    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:users,email',
            'team_id' => 'required|exists:teams,id',
            'role' => 'required|string',
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'The user name is required.',
            'name.string' => 'The user name must be a string.',
            'name.max' => 'The user name may not be greater than 255 characters.',
            
            'email.required' => 'The email address is required.',
            'email.email' => 'The email address must be a valid email format.',
            'email.max' => 'The email address may not be greater than 255 characters.',
            'email.unique' => 'This email address is already in use.',
            
            'team_id.required' => 'A team must be selected.',
            'team_id.exists' => 'The selected team does not exist.',
            
            'role.required' => 'A role must be assigned.',
            'role.string' => 'The role must be a string.',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => 'user name',
            'email' => 'email address',
            'team_id' => 'team',
            'role' => 'user role',
        ];
    }
}
