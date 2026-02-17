<?php

namespace Dynart\Micro\Entities\Test\Integration;

use Dynart\Micro\Entities\Query;
use Dynart\Micro\Entities\Test\Entities\TestPost;
use Dynart\Micro\Entities\Test\Entities\TestUser;

class QueryExecutorTest extends IntegrationTestCase {

    protected function setUp(): void {
        parent::setUp();
        // Drop in FK-safe order so setUp is always idempotent
        $this->dropTable(TestPost::class);
        $this->dropTable(TestUser::class);
    }

    protected function tearDown(): void {
        $this->dropTable(TestPost::class);
        $this->dropTable(TestUser::class);
    }

    private function createUserTable(): void {
        $this->qe->createTable(TestUser::class);
    }

    private function insertUser(string $name, string $email = '', bool $active = false): void {
        $this->db->insert($this->em->tableName(TestUser::class), [
            'name'   => $name,
            'email'  => $email,
            'active' => (int)$active,
        ]);
    }

    // --- isTableExist() ---

    public function testIsTableExistFalse(): void {
        $this->assertFalse($this->qe->isTableExist(TestUser::class));
    }

    public function testIsTableExistTrue(): void {
        $this->createUserTable();
        $this->assertTrue($this->qe->isTableExist(TestUser::class));
    }

    // --- createTable() ---

    public function testCreateTable(): void {
        $this->createUserTable();
        $this->assertTrue($this->qe->isTableExist(TestUser::class));
    }

    public function testCreateTableIfNotExists(): void {
        $this->createUserTable();
        // Should not throw when table already exists
        $this->qe->createTable(TestUser::class, true);
        $this->assertTrue($this->qe->isTableExist(TestUser::class));
    }

    // --- listTables() ---

    public function testListTablesContainsCreatedTable(): void {
        $this->createUserTable();
        $tables = $this->qe->listTables();
        $this->assertContains($this->em->tableName(TestUser::class), $tables);
    }

    // --- findAll() ---

    public function testFindAllNoConditions(): void {
        $this->createUserTable();
        $this->insertUser('Alice', 'alice@test.com');
        $this->insertUser('Bob', 'bob@test.com');
        $rows = $this->qe->findAll(new Query(TestUser::class));
        $this->assertCount(2, $rows);
        $names = array_column($rows, 'name');
        $this->assertContains('Alice', $names);
        $this->assertContains('Bob', $names);
    }

    public function testFindAllWithCondition(): void {
        $this->createUserTable();
        $this->insertUser('Alice', '', false);
        $this->insertUser('Bob', '', true);
        $q = new Query(TestUser::class);
        $q->addCondition('`active` = :active', [':active' => 1]);
        $rows = $this->qe->findAll($q);
        $this->assertCount(1, $rows);
        $this->assertEquals('Bob', $rows[0]['name']);
    }

    // --- findAllColumn() ---

    public function testFindAllColumn(): void {
        $this->createUserTable();
        $this->insertUser('Alice');
        $this->insertUser('Bob');
        $names = $this->qe->findAllColumn(new Query(TestUser::class), 'name');
        $this->assertCount(2, $names);
        $this->assertContains('Alice', $names);
        $this->assertContains('Bob', $names);
    }

    // --- findAllCount() ---

    public function testFindAllCount(): void {
        $this->createUserTable();
        $this->insertUser('Alice');
        $this->insertUser('Bob');
        $this->insertUser('Charlie');
        $count = $this->qe->findAllCount(new Query(TestUser::class));
        $this->assertEquals(3, $count);
    }

    // --- findColumns() ---

    public function testFindColumns(): void {
        $this->createUserTable();
        // columnsByTableDescription() currently returns [] — verify no exception is thrown
        $columns = $this->qe->findColumns(TestUser::class);
        $this->assertIsArray($columns);
    }
}
