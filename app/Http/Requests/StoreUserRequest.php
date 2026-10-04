<?php

namespace App\Http\Requests;

use App\Enums\TeamRole;
use App\Models\Team;
use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

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
     * @return array<string, ValidationRule|array|string>
     */
    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:users,email',
            // Personal teams are Jetstream scaffolding, not places to put staff.
            'team_id' => ['required', Rule::exists('teams', 'id')->where(fn (Builder $query) => $query->where('personal_team', false))],
            'role' => ['required', 'string', Rule::in(TeamRole::assignableBy($this->user()))],
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
            'name.required' => __('The user name is required.'),
            'name.string' => __('The user name must be a string.'),
            'name.max' => __('The user name may not be greater than 255 characters.'),

            'email.required' => __('The email address is required.'),
            'email.email' => __('The email address must be a valid email format.'),
            'email.max' => __('The email address may not be greater than 255 characters.'),
            'email.unique' => __('This email address is already in use.'),

            'team_id.required' => __('A team must be selected.'),
            'team_id.exists' => __('The selected team does not exist.'),

            'role.required' => __('A role must be assigned.'),
            'role.string' => __('The role must be a string.'),
            'role.in' => __('The selected role cannot be assigned.'),
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
            'name' => __('user name'),
            'email' => __('email address'),
            'team_id' => __('team'),
            'role' => __('user role'),
        ];
    }
}
