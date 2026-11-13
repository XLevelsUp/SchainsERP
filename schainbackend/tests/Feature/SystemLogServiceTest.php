<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Services\SystemLogService;
use Illuminate\Database\Events\TransactionBeginning;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * BACKEND_TODO §16: a failed log write must not take the business write
 * with it. On PostgreSQL a failed statement aborts the whole transaction, so
 * the log insert runs in its own nested transaction (a SAVEPOINT). SQLite
 * doesn't abort on a failed statement, so this checks the savepoint is used
 * and the surrounding write survives; the abort itself was checked on
 * PostgreSQL (see PENDING_WORK).
 */
class SystemLogServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_failed_log_write_leaves_the_business_write_intact(): void
    {
        $levels = [];
        Event::listen(TransactionBeginning::class, function (TransactionBeginning $e) use (&$levels) {
            $levels[] = $e->connection->transactionLevel();
        });

        Schema::drop('system_logs'); // every log write now fails

        DB::transaction(function () use (&$levels) {
            $levels = [];
            Role::create(['role' => 'BUSINESS_WRITE']);
            $this->assertNull(SystemLogService::log('TEST', 'FAILS', null, null, null, 'no table'));
            Role::create(['role' => 'AFTER_THE_FAILED_LOG']);
        });

        // The log write opened a nested transaction (a savepoint) inside the business one.
        $this->assertNotEmpty(array_filter($levels, fn ($level) => $level >= 2 + DB::transactionLevel()));
        $this->assertDatabaseHas('roles', ['role' => 'BUSINESS_WRITE']);
        $this->assertDatabaseHas('roles', ['role' => 'AFTER_THE_FAILED_LOG']);
    }
}
