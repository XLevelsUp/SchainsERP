<?php

namespace App\Http\Requests\Admin;

use App\Support\AdminPasswordPolicy;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/** PUT /api/v1/admin/account/password */
class UpdateAdminPasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'current_password' => ['required', 'string'],
            'password' => AdminPasswordPolicy::passwordRules(),
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator) {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }
                $admin = $this->user();

                if (!AdminPasswordPolicy::checkPassword($admin, (string) $this->input('current_password'))) {
                    $validator->errors()->add('current_password', 'The current password is incorrect.');
                    return;
                }
                if (AdminPasswordPolicy::checkPassword($admin, (string) $this->input('password'))) {
                    $validator->errors()->add('password', 'The new password must be different from the current one.');
                }
                if (AdminPasswordPolicy::checkSpecialPassword($admin, (string) $this->input('password'))) {
                    $validator->errors()->add('password', 'The password must be different from your special password.');
                }
            },
        ];
    }
}
