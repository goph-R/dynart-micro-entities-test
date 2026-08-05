<?php

namespace Dynart\Micro\Entities\Test\Integration;

use Dynart\Micro\Entities\Database\MariaDatabase;
use Dynart\Micro\Entities\EntityManagerException;
use Dynart\Micro\Entities\PdoBuilder;
use Dynart\Micro\Entities\Test\Entities\TestUser;
use Dynart\Micro\Entities\Test\StubLogger;
use PDOStatement;
use RuntimeException;

class DatabaseTest extends IntegrationTestCase {

    protected function setUp(): void {
        parent::setUp();
        $this->db->query($this->qb->createTable(TestUser::class));
    }

    protected function tearDown(): void {
        $this->dropTable(TestUser::class);
    }

    private function insertUser(string $name, string $email = '', bool $active = false): void {
        $this->db->insert($this->em->tableName(TestUser::class), [
            'name'   => $name,
            'email'  => $email,
            'active' => (int)$active,
        ]);
    }

    // --- Connection ---

    public function testNotConnectedInitially(): void {
        $freshDb = new MariaDatabase($this->config, new StubLogger(), new PdoBuilder());
        $this->assertFalse($freshDb->connected());
    }

    public function testConnectedAfterQuery(): void {
        $this->assertTrue($this->db->connected());
    }

    // --- #ClassName substitution ---

    public function testHashClassNameSubstitution(): void {
        $count = $this->db->fetchOne('select count(1) from #TestUser');
        $this->assertEquals(0, $count);
    }

    public function testHashClassNameNotReplacedInsideStringLiteral(): void {
        // '#TestUser' followed by ' — lookahead in regex fails, so not replaced
        $result = $this->db->fetchOne("select '#TestUser' as t");
        $this->assertEquals('#TestUser', $result);
    }

    // --- query() ---

    public function testQueryReturnsPdoStatement(): void {
        $stmt = $this->db->query('select 1');
        $this->assertInstanceOf(PDOStatement::class, $stmt);
    }

    // --- fetch() ---

    public function testFetchAssoc(): void {
        $this->insertUser('Alice', 'alice@test.com');
        $row = $this->db->fetch(
            'select * from ' . $this->safeTableName(TestUser::class) . ' where `name` = :name',
            [':name' => 'Alice']
        );
        $this->assertIsArray($row);
        $this->assertEquals('Alice', $row['name']);
        $this->assertEquals('alice@test.com', $row['email']);
    }

    public function testFetchWithClassName(): void {
        $this->insertUser('Bob');
        $user = $this->db->fetch(
            'select * from ' . $this->safeTableName(TestUser::class) . ' where `name` = :name',
            [':name' => 'Bob'],
            TestUser::class
        );
        $this->assertInstanceOf(TestUser::class, $user);
        $this->assertEquals('Bob', $user->name);
    }

    public function testFetchNoMatch(): void {
        $result = $this->db->fetch(
            'select * from ' . $this->safeTableName(TestUser::class) . ' where `name` = :name',
            [':name' => 'Nobody']
        );
        $this->assertFalse($result);
    }

    // --- fetchAll() ---

    public function testFetchAll(): void {
        $this->insertUser('Alice');
        $this->insertUser('Bob');
        $rows = $this->db->fetchAll('select * from ' . $this->safeTableName(TestUser::class));
        $this->assertCount(2, $rows);
        $this->assertIsArray($rows[0]);
    }

    public function testFetchAllWithClassName(): void {
        $this->insertUser('Alice');
        $this->insertUser('Bob');
        $users = $this->db->fetchAll(
            'select * from ' . $this->safeTableName(TestUser::class),
            [],
            TestUser::class
        );
        $this->assertCount(2, $users);
        $this->assertInstanceOf(TestUser::class, $users[0]);
    }

    // --- fetchColumn() ---

    public function testFetchColumn(): void {
        $this->insertUser('Alice');
        $this->insertUser('Bob');
        $names = $this->db->fetchColumn(
            'select `name` from ' . $this->safeTableName(TestUser::class) . ' order by `name`'
        );
        $this->assertEquals(['Alice', 'Bob'], $names);
    }

    // --- fetchOne() ---

    public function testFetchOne(): void {
        $this->insertUser('Alice');
        $this->insertUser('Bob');
        $count = $this->db->fetchOne('select count(1) from ' . $this->safeTableName(TestUser::class));
        $this->assertEquals(2, $count);
    }

    // --- insert() + lastInsertId() ---

    public function testInsertAndLastInsertId(): void {
        $this->db->insert($this->em->tableName(TestUser::class), ['name' => 'Alice']);
        $id = $this->db->lastInsertId();
        $this->assertNotFalse($id);
        $this->assertIsNumeric($id);
        $this->assertGreaterThan(0, (int)$id);
    }

    // --- booleans ---

    /**
     * `false` is not an empty string
     *
     * `PDOStatement::execute()` binds every value as a string, and `false` as a string is `''`.
     * A server in strict mode - the default in MySQL since 5.7 and MariaDB since 10.2 - refuses
     * that for an integer column, so writing a `bool` field was an error there while a lenient
     * server coerced it to 0 and said nothing. It went unnoticed until the first install onto a
     * correctly configured database, which failed on the first row it wrote.
     *
     * Strict mode is set for this connection rather than assumed, so the test means the same
     * thing on whatever the suite is pointed at.
     */
    public function testABooleanIsWrittenAsANumberEvenInStrictMode(): void {
        $previous = (string)$this->db->fetchOne('select @@session.sql_mode');
        $this->db->query("set session sql_mode = 'STRICT_TRANS_TABLES'");
        try {
            $tableName = $this->em->tableName(TestUser::class);
            $this->db->insert($tableName, ['name' => 'Alice', 'active' => false]);
            $this->db->insert($tableName, ['name' => 'Bob', 'active' => true]);
            $this->assertSame(
                ['0', '1'],
                array_map('strval', $this->db->fetchColumn(
                    'select active from ' . $this->safeTableName(TestUser::class) . ' order by name'
                ))
            );
        } finally {
            // the value came from the server and is a list of mode names - checked all the same,
            // because putting a value into SQL by hand is putting a value into SQL by hand
            $restore = preg_match('/^[A-Z_,]*$/', $previous) === 1 ? $previous : '';
            $this->db->query("set session sql_mode = '$restore'");
        }
    }

    /**
     * The same for a boolean bound in a condition rather than written
     */
    public function testABooleanConditionMatchesTheRowsItNames(): void {
        $this->insertUser('Alice', '', true);
        $this->insertUser('Bob', '', false);
        $names = $this->db->fetchColumn(
            'select name from ' . $this->safeTableName(TestUser::class) . ' where active = :active',
            [':active' => true]
        );
        $this->assertSame(['Alice'], array_map('strval', $names));
    }

    // --- update() ---

    public function testUpdateWithCondition(): void {
        $this->insertUser('Alice');
        $this->insertUser('Bob');
        $this->db->update(
            $this->em->tableName(TestUser::class),
            ['name' => 'Charlie'],
            '`name` = :oldName',
            [':oldName' => 'Alice']
        );
        $names = $this->db->fetchColumn(
            'select `name` from ' . $this->safeTableName(TestUser::class) . ' order by `name`'
        );
        $this->assertContains('Charlie', $names);
        $this->assertContains('Bob', $names);
        $this->assertNotContains('Alice', $names);
    }

    public function testUpdateWithoutCondition(): void {
        $this->insertUser('Alice');
        $this->insertUser('Bob');
        $this->db->update($this->em->tableName(TestUser::class), ['email' => 'updated@test.com']);
        $count = $this->db->fetchOne(
            'select count(1) from ' . $this->safeTableName(TestUser::class) . ' where `email` = :email',
            [':email' => 'updated@test.com']
        );
        $this->assertEquals(2, $count);
    }

    /**
     * The condition is written by the caller and the SET placeholders are generated, so a
     * condition searching by the same column that is being updated used to overwrite the new
     * value with the one being searched for - the row was "updated" to what it already was, with
     * no error anywhere.
     */
    public function testUpdateWithAConditionOnTheColumnBeingUpdated(): void {
        $this->insertUser('Alice');
        $this->insertUser('Bob');
        $this->db->update(
            $this->em->tableName(TestUser::class),
            ['name' => 'Charlie'],
            '`name` = :name',
            [':name' => 'Alice']
        );
        $names = $this->db->fetchColumn(
            'select `name` from ' . $this->safeTableName(TestUser::class) . ' order by `name`'
        );
        $this->assertContains('Charlie', $names);
        $this->assertContains('Bob', $names);
        $this->assertNotContains('Alice', $names);
    }

    public function testUpdateThrowsWhenAConditionParamCollidesWithAGeneratedOne(): void {
        $this->insertUser('Alice');
        $this->expectException(EntityManagerException::class);
        $this->db->update(
            $this->em->tableName(TestUser::class),
            ['name' => 'Charlie'],
            '`name` = :set_name',
            [':set_name' => 'Alice']
        );
    }

    // --- getInConditionAndParams() ---

    public function testGetInConditionAndParams(): void {
        [$condition, $params] = $this->db->getInConditionAndParams([1, 2, 3]);
        $this->assertEquals(':in0,:in1,:in2', $condition);
        $this->assertEquals([':in0' => 1, ':in1' => 2, ':in2' => 3], $params);
    }

    // --- Transactions ---

    public function testBeginTransactionAndCommit(): void {
        $safeName = $this->safeTableName(TestUser::class);
        $this->db->beginTransaction();
        $this->insertUser('Alice');
        $this->db->commit();
        $count = $this->db->fetchOne("select count(1) from $safeName");
        $this->assertEquals(1, $count);
    }

    public function testBeginTransactionAndRollback(): void {
        $safeName = $this->safeTableName(TestUser::class);
        $this->db->beginTransaction();
        $this->insertUser('Alice');
        $this->db->rollBack();
        $count = $this->db->fetchOne("select count(1) from $safeName");
        $this->assertEquals(0, $count);
    }

    public function testRunInTransactionSuccess(): void {
        $tableName = $this->em->tableName(TestUser::class);
        $safeName = $this->safeTableName(TestUser::class);
        $this->db->runInTransaction(function() use ($tableName) {
            $this->db->insert($tableName, ['name' => 'Alice']);
        });
        $count = $this->db->fetchOne("select count(1) from $safeName");
        $this->assertEquals(1, $count);
    }

    public function testRunInTransactionException(): void {
        $tableName = $this->em->tableName(TestUser::class);
        $safeName = $this->safeTableName(TestUser::class);
        try {
            $this->db->runInTransaction(function() use ($tableName) {
                $this->db->insert($tableName, ['name' => 'Alice']);
                throw new RuntimeException('Forced failure');
            });
            $this->fail('Expected RuntimeException not thrown');
        } catch (RuntimeException $e) {
            $this->assertEquals('Forced failure', $e->getMessage());
        }
        $count = $this->db->fetchOne("select count(1) from $safeName");
        $this->assertEquals(0, $count);
    }

    // --- escapeLike() ---

    public function testEscapeLike(): void {
        $this->assertEquals('\%foo\%', $this->db->escapeLike('%foo%'));
    }
}
