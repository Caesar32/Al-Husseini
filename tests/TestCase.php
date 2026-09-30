<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\DB;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->assertDatabaseIntegrityEnforced();
    }

    /**
     * Tests must run with the same integrity rules as production: SQLite foreign keys
     * and CHECK constraints (enum columns) enforced. A migration or helper that disables
     * them on the shared in-memory connection would make every later test meaningless.
     */
    protected function assertDatabaseIntegrityEnforced(): void
    {
        $connection = DB::connection();

        if ($connection->getDriverName() !== 'sqlite') {
            return;
        }

        $this->assertSame(1, (int) $connection->selectOne('PRAGMA foreign_keys')->foreign_keys,
            'SQLite foreign key enforcement is disabled on the test connection.');
        $this->assertSame(0, (int) $connection->selectOne('PRAGMA ignore_check_constraints')->ignore_check_constraints,
            'SQLite CHECK constraint enforcement is disabled on the test connection.');
    }
}
