<?php

namespace App\Http\Middleware;

use App\Models\UserDetail;
use App\Services\SystemLogService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Audits admin console changes in system_logs (module ADMIN, admin_id set,
 * user_id null). One place for every admin write instead of a log call in
 * each controller. Only successful (2xx) requests to the routes listed
 * below are recorded; reads are not.
 *
 * Sign-in and sign-out are logged by Admin\AdminAuthController itself.
 */
class LogAdminAction
{
    /**
     * route name => [action, reference table, route parameter holding the id,
     * or for a create: the path to the new row's id in the response JSON]
     */
    private const ACTIONS = [
        'admin.roles.store' => ['ROLE_CREATE', 'roles', 'response:data.id'],
        'admin.user-details.store' => ['USER_CREATE', 'user_details', 'response:data.user.user_id'],
        'admin.user-details.update' => ['USER_UPDATE', 'user_details', 'user_detail'],
        'admin.user-details.destroy' => ['USER_DELETE', 'user_details', 'user_detail'],
        'admin.user-details.status' => ['USER_STATUS_CHANGE', 'user_details', 'user_detail'],
        'admin.head-employee-mappings.store' => ['HEAD_ASSIGN', 'head_employee_mappings', null],
        'admin.head-employee-mappings.update' => ['HEAD_ASSIGN', 'head_employee_mappings', 'head_employee_mapping'],
        'admin.cash-head-employee-mappings.store' => ['CASH_HEAD_ASSIGN', 'cash_head_employee_mappings', null],
        'admin.cash-head-employee-mappings.update' => ['CASH_HEAD_ASSIGN', 'cash_head_employee_mappings', 'cash_head_employee_mapping'],
        'admin.cash-head-employee-mappings.destroy' => ['CASH_HEAD_UNASSIGN', 'cash_head_employee_mappings', 'cash_head_employee_mapping'],
        'admin.account.password' => ['ADMIN_PASSWORD_CHANGE', 'admins', null],
        'admin.account.special-password' => ['ADMIN_SPECIAL_PASSWORD_CHANGE', 'admins', null],
    ];

    /** Account changes carry secrets only; their body is never stored. */
    private const NO_PAYLOAD = ['ADMIN_PASSWORD_CHANGE', 'ADMIN_SPECIAL_PASSWORD_CHANGE'];

    public function handle(Request $request, Closure $next): Response
    {
        $routeName = $request->route()?->getName();
        $spec = self::ACTIONS[$routeName] ?? null;

        // A deleted user's name is gone after the request, so read it first.
        $subject = null;
        if ($spec && $spec[0] === 'USER_DELETE') {
            $subject = UserDetail::where('user_id', $request->route($spec[2]))->first(['user_id', 'name', 'user_name']);
        }

        $response = $next($request);

        if ($spec && $response->isSuccessful()) {
            $this->record($request, $response, $spec, $subject);
        }

        return $response;
    }

    private function record(Request $request, Response $response, array $spec, ?UserDetail $subject): void
    {
        [$action, $table, $param] = $spec;
        $admin = $request->user();

        $referenceId = match (true) {
            $table === 'admins' => $admin->id,
            $param !== null && str_starts_with($param, 'response:') => data_get(
                json_decode((string) $response->getContent(), true),
                substr($param, strlen('response:')),
            ),
            $param !== null => (int) $request->route($param),
            default => $request->integer('employee_id') ?: null,
        };

        $payload = in_array($action, self::NO_PAYLOAD, true)
            ? null
            : $request->except(array_keys($request->allFiles()));

        $metadata = array_filter([
            'route' => $request->route()->getName(),
            'special_password_verified' => (bool) $request->attributes->get('special_password_verified', false),
            'user_name' => $subject?->user_name,
            'name' => $subject?->name,
        ], fn ($v) => $v !== null);

        $target = $subject?->name ?? ($referenceId ? "#{$referenceId}" : '');

        SystemLogService::log(
            'ADMIN',
            $action,
            null,
            $referenceId ? (int) $referenceId : null,
            $table,
            trim("Admin {$admin->name}: " . str_replace('_', ' ', strtolower($action)) . " {$target}"),
            $payload,
            $metadata,
            adminId: $admin->id,
        );
    }
}
