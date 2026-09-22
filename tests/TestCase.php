<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * Production boots the application for every request; a test serves all its
     * requests from one, so what a request leaves in it is cleared as it ends.
     *
     * Only the token guard forgets its user. The session guard keeps its user in
     * memory, which is how actingAs() and a login carry over from one test
     * request to the next.
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->app->terminating(function (): void {
            tenancy()->end();
            auth()->guard('sanctum')->forgetUser();
        });
    }
}
