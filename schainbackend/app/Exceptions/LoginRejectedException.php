<?php

namespace App\Exceptions;

use Illuminate\Contracts\Debug\ShouldntReport;
use Illuminate\Http\JsonResponse;
use RuntimeException;

/**
 * A sign-in attempt that was refused. Renders itself as the API's usual
 * { success, message } envelope, so both login controllers can let it
 * propagate instead of building the same error responses twice.
 *
 * Not reported: a wrong password is an expected outcome, not an error, and
 * would otherwise write a stack trace to laravel.log on every typo.
 */
class LoginRejectedException extends RuntimeException implements ShouldntReport
{
    public function __construct(string $message, private readonly int $status)
    {
        parent::__construct($message);
    }

    public static function invalidCredentials(): self
    {
        return new self('Invalid username or password', 401);
    }

    public static function deactivated(): self
    {
        return new self('Your account has been deactivated. Please contact your administrator.', 403);
    }

    public function status(): int
    {
        return $this->status;
    }

    public function render(): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $this->getMessage(),
        ], $this->status);
    }
}
