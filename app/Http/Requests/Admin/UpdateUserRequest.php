<?php

namespace App\Http\Requests\Admin;

use App\Models\User;
use Illuminate\Container\Attributes\RouteParameter;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * An admin editing a user's account. The admin middleware on the route
 * decides who may do this.
 */
class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @param  User  $editedUser  The user being edited, bound from the route's {user}.
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(#[RouteParameter('user')] User $editedUser): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'string',
                'lowercase',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($editedUser),
            ],
            // Admins can't switch themselves off, so there's always an active admin.
            'is_active' => ['required', 'boolean', Rule::when($editedUser->is($this->user()), ['accepted'])],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'is_active.accepted' => "You can't deactivate your own account.",
        ];
    }
}
