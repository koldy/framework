<?php

declare(strict_types=1);

namespace Tests\PhpUnit;

use PHPUnit\Framework\TestCase;

class ExampleTest extends TestCase
{

    public function testTrueIsTrue(): void
    {
        $this->assertTrue(true);
    }

}

