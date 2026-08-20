<?php

namespace App\Http\Requests;

use App\Enums\Role;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', Rule::unique('users', 'email')],
            'password' => ['required', 'string', 'min:8'],
            'role' => ['required', Rule::in(Role::values())],
            'mobile' => ['sometimes', 'nullable', 'string', 'max:20'],
            'employee_code' => [
                'sometimes',
                'nullable',
                'string',
                'max:50',
                Rule::unique('users', 'employee_code')->whereNull('deleted_at'),
            ],
            'department_id' => ['sometimes', 'nullable', 'uuid', 'exists:departments,id'],
            'manager_id' => ['sometimes', 'nullable', 'uuid', 'exists:users,id'],
        ];
    }

    /**
     * employee_code is required for role `user` and must stay empty for
     * admin/superadmin — same rule as UpdateUserRequest, just checked
     * against the submitted role instead of an existing target's role.
     *
     * A plain `admin` may only create `user` or `admin` accounts — never
     * `superadmin` — so one admin can't mint a peer/superior for themself.
     * Only superadmin may create another superadmin.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $role = $this->input('role');
            $employeeCode = $this->input('employee_code');

            if ($role === Role::User->value && ! $employeeCode) {
                $validator->errors()->add('employee_code', 'Employee code is required for this user.');
            }

            if ($role !== Role::User->value && $employeeCode) {
                $validator->errors()->add('employee_code', 'Employee code must be empty for admin/superadmin users.');
            }

            $actor = $this->user();
            if ($role === Role::SuperAdmin->value && ! $actor?->hasRole(Role::SuperAdmin->value)) {
                $validator->errors()->add('role', 'Only a superadmin can create another superadmin.');
            }
        });
    }
}
