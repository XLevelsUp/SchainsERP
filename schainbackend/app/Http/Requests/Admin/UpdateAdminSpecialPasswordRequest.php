<?php

namespace App\Http\Requests\Admin;

use App\Support\AdminPasswordPolicy;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/** PUT /api/v1/admin/account/special-password */
class UpdateAdminSpecialPasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'current_special_password' => ['required', 'string'],
            'special_password' => AdminPasswordPolicy::specialPasswordRules(),
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

                if (!AdminPasswordPolicy::checkSpecialPassword($admin, (string) $this->input('current_special_password'))) {
                    $validator->errors()->add('current_special_password', 'The current special password is incorrect.');
                    return;
                }
                if (AdminPasswordPolicy::checkPassword($admin, (string) $this->input('special_password'))) {
                    $validator->errors()->add('special_password', 'The special password must be different from your login password.');
                }
            },
        ];
    }
}
