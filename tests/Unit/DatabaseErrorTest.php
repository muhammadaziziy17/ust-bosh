<?php

namespace Tests\Unit;

use App\Support\DatabaseError;
use Illuminate\Database\QueryException;
use PDOException;
use PHPUnit\Framework\TestCase;

class DatabaseErrorTest extends TestCase
{
    public function test_detects_sqlite_unique_violation(): void
    {
        $exception = $this->queryException(
            'SQLSTATE[23000]: Integrity constraint violation: 19 UNIQUE constraint failed: sales.shop_id, sales.cashier_id'
        );

        $this->assertTrue(DatabaseError::isUniqueViolation($exception));
    }

    public function test_detects_mysql_duplicate_entry(): void
    {
        $exception = $this->queryException(
            "SQLSTATE[23000]: Integrity constraint violation: 1062 Duplicate entry 'S-1' for key 'sales_unique'"
        );

        $this->assertTrue(DatabaseError::isUniqueViolation($exception));
    }

    public function test_detects_unique_violation_from_driver_error_code(): void
    {
        $exception = $this->queryException('SQLSTATE[23505]: Unique violation', 23505);

        $this->assertTrue(DatabaseError::isUniqueViolation($exception));
    }

    public function test_does_not_treat_other_query_errors_as_unique_violation(): void
    {
        $exception = $this->queryException('SQLSTATE[HY000]: General error: 1 no such table: sales');

        $this->assertFalse(DatabaseError::isUniqueViolation($exception));
    }

    private function queryException(string $message, ?int $driverCode = null): QueryException
    {
        $previous = new PDOException($message);

        if ($driverCode !== null) {
            $previous->errorInfo = ['23000', $driverCode, $message];
        }

        return new QueryException('sqlite', 'insert into "sales" ("sale_no") values (?)', ['S-1'], $previous);
    }
}
