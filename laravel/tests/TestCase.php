<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * Reference tables that every write depends on — type_logs, used by the
     * audit trail — are seeded here once the module task creates them:
     *
     *     protected bool $seed = true;
     *     protected string $seeder = TypeLogSeeder::class;
     */
}
