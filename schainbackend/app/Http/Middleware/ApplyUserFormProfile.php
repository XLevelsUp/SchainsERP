<?php

namespace App\Http\Middleware;

use App\Models\UserDetail;
use App\Support\UserFormProfiles;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Symfony\Component\HttpFoundation\Response;

/**
 * Enforces the role's user-form profile on admin user create / update
 * (App\Support\UserFormProfiles): input the role doesn't allow — flags it
 * leaves out, sections it doesn't have — is dropped before the controller
 * runs. Nothing outside a role's profile can be set through the API, even
 * by a request the console wouldn't send.
 *
 * The role is the one being saved: role_id from the request, or on an edit
 * that doesn't send one, the user's current role.
 *
 * Runs before RequireSpecialPassword, so dropped head mappings don't ask for
 * the special password.
 */
class ApplyUserFormProfile
{
    public function handle(Request $request, Closure $next): Response
    {
        $roleId = $request->input('role_id');

        if ($request->route('user_detail')) {
            $currentRoleId = UserDetail::where('user_id', $request->route('user_detail'))->value('role_id');

            // Some roles can't be changed once the user exists (ADMIN).
            $requested = ($roleId === null || $roleId === '') ? null : (int) $roleId;
            if ($requested !== null && $currentRoleId !== null && $requested !== (int) $currentRoleId
                && UserFormProfiles::forRoleId($currentRoleId)['lock_role_on_edit']) {
                return response()->json([
                    'success' => false,
                    'message' => "This user's role can't be changed.",
                    'errors' => ['role_id' => ["This user's role can't be changed."]],
                ], 422);
            }

            if ($requested === null) {
                $roleId = $currentRoleId;
            }
        }

        $profile = UserFormProfiles::forRoleId($roleId);
        $drop = UserFormProfiles::disallowedInputs($profile);

        if ($drop !== []) {
            // input() only: except() would copy uploaded files into the input bag.
            $request->replace(Arr::except($request->input(), $drop));
            foreach ($drop as $key) {
                $request->files->remove($key);
            }
        }

        $request->attributes->set('user_form_profile', $profile['role']);

        return $next($request);
    }
}
