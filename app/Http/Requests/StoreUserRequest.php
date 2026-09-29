<?php

namespace App\Http\Requests;

use App\Enums\TeamRole;
use App\Models\Team;
use App\Models\User;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Laravel\Jetstream\Rules\Role;

class StoreUserRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('create', User::class);
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
            'role' => ['required', 'string', new Role],
        ];
    }

    /**
     * Leave approval routes to a team's single manager, so a second one would
     * leave requests with an ambiguous approver.
     *
     * @return array<int, \Closure>
     */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                if ($this->input('role') !== TeamRole::Manager->value) {
                    return;
                }

                if (Team::find($this->input('team_id'))?->hasManagerBesides()) {
                    $validator->errors()->add('role', __('Only one manager is allowed per team.'));
                }
            },
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
