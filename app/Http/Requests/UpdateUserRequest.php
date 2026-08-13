<?php

namespace App\Http\Requests;

use App\Enums\Role;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateUserRequest extends FormRequest
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
        $target = $this->route('user');

        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'email' => ['sometimes', 'required', 'email', Rule::unique('users', 'email')->ignore($target?->id)],
            'mobile' => ['sometimes', 'nullable', 'string', 'max:20'],
            'employee_code' => [
                'sometimes',
                'nullable',
                'string',
                'max:50',
                Rule::unique('users', 'employee_code')->ignore($target?->id)->whereNull('deleted_at'),
            ],
            'department_id' => ['sometimes', 'nullable', 'uuid', 'exists:departments,id'],
        ];
    }

    /**
     * employee_code is required for role `user` and must stay null for
     * admin/superadmin — this depends on the target's existing role (this
     * endpoint never changes role), so it can't be expressed as a plain
     * rules() entry.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $target = $this->route('user');

            if (! $target) {
                return;
            }

            $employeeCode = $this->has('employee_code')
                ? $this->input('employee_code')
                : $target->employee_code;

            if ($target->hasRole(Role::User->value) && ! $employeeCode) {
                $validator->errors()->add('employee_code', 'Employee code is required for this user.');
            }

            if (! $target->hasRole(Role::User->value) && $employeeCode) {
                $validator->errors()->add('employee_code', 'Employee code must be empty for admin/superadmin users.');
            }
        });
    }
}
