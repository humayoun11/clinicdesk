<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    public function createApplication()
    {
        $app = parent::createApplication();
        if ($app['config']->get('database.connections.'.$app['config']->get('database.default').'.database') !== 'clinicdesk_test') {
            throw new \RuntimeException('Tests must use the isolated clinicdesk_test database.');
        }

return $app;
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }
}
