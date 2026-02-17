<?php

namespace Dynart\Micro\Entities\Test\Integration;

use PHPUnit\Framework\TestCase;

class DatabaseTest extends TestCase {

    protected function setUp(): void {
        $this->markTestSkipped('Integration tests require a MariaDB connection — configure configs/test.ini first.');
    }

    public function testPlaceholder(): void {}
}
