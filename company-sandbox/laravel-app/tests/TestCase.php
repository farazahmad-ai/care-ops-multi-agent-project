<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        $this->afterApplicationCreated(function (): void {
            $db = (string) config('database.connections.mysql.database');
            if ($db !== 'careops_legacy_test') {
                throw new RuntimeException("Refusing to run tests against non-test database '{$db}'.");
            }
        });

        parent::setUp();
    }
}
