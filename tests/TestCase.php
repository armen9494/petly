<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Tests\Concerns\InteractsWithPixelforge;

abstract class TestCase extends BaseTestCase
{
    use InteractsWithPixelforge;

    protected function setUp(): void
    {
        parent::setUp();

        $this->configurePixelforge();
    }
}
