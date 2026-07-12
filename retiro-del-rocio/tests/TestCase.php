<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // SiteContent memoises key => value for the life of the process, so it
        // would otherwise carry one test's settings into the next.
        \App\Models\SiteContent::flush();
    }
}
