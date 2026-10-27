<?php

namespace App\Services;

use App\Models\SystemLog;
use Illuminate\Support\Facades\Log;

class SystemLogService
{
    /**
     * Sensitive fields that must be masked before storing payloads.
     */
    protected static array $sensitiveKeys = [
        'password',
        'password_confirmation',
        'confirm_password',
        'new_password',
        'new_password_confirmation',
        'confirm_new_password',
        'otp',
        'token',
        'access_token',
        'secret',
        'authorization',
    ];

    /**
     * Record a system audit log entry.
     *
     * @param string $module 'CASH' or 'STOCK'
     * @param string $action Specific action e.g. 'STOCK_OUT', 'CASH_IN', 'GMS_OUT'
     * @param int|null $userId Acting user ID
     * @param int|null $referenceId Primary key of target transaction
     * @param string|null $referenceTable Table name e.g. 'stock_details'
     * @param string|null $description Human-readable message
     * @param array|null $payload Request data (will be automatically sanitized)
     * @param array|null $metadata Contextual summary (totals, grams, balances)
     * @return SystemLog|null
     */
    public static function log(
        string $module,
        string $action,
        ?int $userId = null,
        ?int $referenceId = null,
        ?string $referenceTable = null,
        ?string $description = null,
        ?array $payload = null,
        ?array $metadata = null
    ): ?SystemLog {
        try {
            $sanitizedPayload = $payload ? self::sanitize($payload) : null;

            $ipAddress = null;
            $userAgent = null;

            if (function_exists('request') && request()) {
                $ipAddress = request()->ip();
                $userAgent = request()->userAgent();
                if (!$userId && request()->user()) {
                    $userId = request()->user()->user_id ?? request()->user()->id;
                }
            }

            return SystemLog::create([
                'module' => strtoupper($module),
                'action' => strtoupper($action),
                'user_id' => $userId,
                'reference_id' => $referenceId,
                'reference_table' => $referenceTable,
                'description' => $description,
                'payload' => $sanitizedPayload,
                'metadata' => $metadata,
                'ip_address' => $ipAddress,
                'user_agent' => $userAgent,
                'created_at' => now(),
            ]);
        } catch (\Throwable $e) {
            Log::error('SystemLogService::log failed to record log', [
                'module' => $module,
                'action' => $action,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Recursively mask sensitive fields.
     */
    protected static function sanitize(array $data): array
    {
        $sanitized = [];

        foreach ($data as $key => $value) {
            if (is_string($key) && in_array(strtolower($key), self::$sensitiveKeys, true)) {
                $sanitized[$key] = '[REDACTED]';
            } elseif (is_array($value)) {
                $sanitized[$key] = self::sanitize($value);
            } else {
                $sanitized[$key] = $value;
            }
        }

        return $sanitized;
    }
}
