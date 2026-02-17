<?php

namespace Dynart\Micro\Entities\Test\Unit;

use Dynart\Micro\Entities\Attribute\Column;
use Dynart\Micro\Entities\Entity;
use Dynart\Micro\Entities\EntityManager;
use Dynart\Micro\Entities\EntityManagerException;
use Dynart\Micro\Entities\Test\Entities\TestUser;
use Dynart\Micro\Entities\Test\TestHelper;
use PHPUnit\Framework\TestCase;

class CompositeEntity extends Entity {
    public int $a = 0;
    public int $b = 0;
}

class EntityManagerTest extends TestCase {

    private EntityManager $em;

    protected function setUp(): void {
        $this->em = TestHelper::createEntityManager();
        TestHelper::registerEntity($this->em, TestUser::class);
    }

    public function testAddColumnRegistersTable(): void {
        $this->assertSame('testuser', $this->em->tableName(TestUser::class));
    }

    public function testTableNameByClassWithPrefix(): void {
        $em = TestHelper::createEntityManager('pre_');
        TestHelper::registerEntity($em, TestUser::class);
        $this->assertSame('pre_testuser', $em->tableName(TestUser::class));
    }

    public function testTableNameByClassWithoutPrefix(): void {
        $this->assertSame('testuser', $this->em->tableNameByClass(TestUser::class, false));
    }

    public function testTableNameByClassHashMode(): void {
        $em = TestHelper::createEntityManager();
        $em->setUseEntityHashName(true);
        TestHelper::registerEntity($em, TestUser::class);
        $this->assertSame('#TestUser', $em->tableName(TestUser::class));
    }

    public function testTableNameThrowsForUnregistered(): void {
        $this->expectException(EntityManagerException::class);
        $this->em->tableName('NonExistentClass');
    }

    public function testTableColumnsThrowsForUnregistered(): void {
        $this->expectException(EntityManagerException::class);
        $this->em->tableColumns('NonExistentClass');
    }

    public function testTableNamesReturnsAll(): void {
        $names = $this->em->tableNames();
        $this->assertArrayHasKey(TestUser::class, $names);
    }

    public function testAllTableColumnsReturnsAll(): void {
        $all = $this->em->allTableColumns();
        $this->assertArrayHasKey(TestUser::class, $all);
    }

    public function testPrimaryKeySingle(): void {
        $this->assertSame('id', $this->em->primaryKey(TestUser::class));
    }

    public function testPrimaryKeyComposite(): void {
        $this->em->addColumn(CompositeEntity::class, 'a', new Column(type: Column::TYPE_INT, primaryKey: true));
        $this->em->addColumn(CompositeEntity::class, 'b', new Column(type: Column::TYPE_INT, primaryKey: true));
        $this->assertSame(['a', 'b'], $this->em->primaryKey(CompositeEntity::class));
    }

    public function testPrimaryKeyNone(): void {
        $this->em->addColumn('NoPkClass', 'name', new Column(type: Column::TYPE_STRING, size: 100));
        $this->assertNull($this->em->primaryKey('NoPkClass'));
    }

    public function testPrimaryKeyIsCached(): void {
        $first  = $this->em->primaryKey(TestUser::class);
        $second = $this->em->primaryKey(TestUser::class);
        $this->assertSame($first, $second);
    }

    public function testIsPrimaryKeyAutoIncrementTrue(): void {
        $this->assertTrue($this->em->isPrimaryKeyAutoIncrement(TestUser::class));
    }

    public function testIsPrimaryKeyAutoIncrementFalse(): void {
        $this->em->addColumn('ManualPkClass', 'id', new Column(type: Column::TYPE_INT, primaryKey: true));
        $this->assertFalse($this->em->isPrimaryKeyAutoIncrement('ManualPkClass'));
    }

    public function testIsPrimaryKeyAutoIncrementComposite(): void {
        $this->em->addColumn(CompositeEntity::class, 'a', new Column(type: Column::TYPE_INT, primaryKey: true));
        $this->em->addColumn(CompositeEntity::class, 'b', new Column(type: Column::TYPE_INT, primaryKey: true));
        $this->assertFalse($this->em->isPrimaryKeyAutoIncrement(CompositeEntity::class));
    }

    public function testPrimaryKeyConditionSingle(): void {
        $this->assertSame('`id` = :pkValue', $this->em->primaryKeyCondition(TestUser::class));
    }

    public function testPrimaryKeyConditionComposite(): void {
        $this->em->addColumn(CompositeEntity::class, 'a', new Column(type: Column::TYPE_INT, primaryKey: true));
        $this->em->addColumn(CompositeEntity::class, 'b', new Column(type: Column::TYPE_INT, primaryKey: true));
        $this->assertSame('`a` = :pkValue0 and `b` = :pkValue1', $this->em->primaryKeyCondition(CompositeEntity::class));
    }

    public function testPrimaryKeyConditionParamsSingle(): void {
        $this->assertSame([':pkValue' => 42], $this->em->primaryKeyConditionParams(TestUser::class, 42));
    }

    public function testPrimaryKeyConditionParamsComposite(): void {
        $this->em->addColumn(CompositeEntity::class, 'a', new Column(type: Column::TYPE_INT, primaryKey: true));
        $this->em->addColumn(CompositeEntity::class, 'b', new Column(type: Column::TYPE_INT, primaryKey: true));
        $expected = [':pkValue0' => 1, ':pkValue1' => 2];
        $this->assertSame($expected, $this->em->primaryKeyConditionParams(CompositeEntity::class, [1, 2]));
    }

    public function testPrimaryKeyValueSingle(): void {
        $data = ['id' => 7, 'name' => 'Alice', 'email' => '', 'active' => false, 'created_at' => null];
        $this->assertSame(7, $this->em->primaryKeyValue(TestUser::class, $data));
    }

    public function testPrimaryKeyValueComposite(): void {
        $this->em->addColumn(CompositeEntity::class, 'a', new Column(type: Column::TYPE_INT, primaryKey: true));
        $this->em->addColumn(CompositeEntity::class, 'b', new Column(type: Column::TYPE_INT, primaryKey: true));
        $this->assertSame([1, 2], $this->em->primaryKeyValue(CompositeEntity::class, ['a' => 1, 'b' => 2]));
    }

    public function testSafeTableName(): void {
        $this->assertSame('`testuser`', $this->em->safeTableName(TestUser::class));
    }

    public function testFetchDataArray(): void {
        $user = new TestUser();
        $user->id = 1;
        $user->name = 'Alice';
        $user->email = 'alice@example.com';
        $user->active = true;
        $user->created_at = '2024-01-01 00:00:00';
        $data = $this->em->fetchDataArray($user);
        $this->assertSame([
            'id'         => 1,
            'name'       => 'Alice',
            'email'      => 'alice@example.com',
            'active'     => true,
            'created_at' => '2024-01-01 00:00:00',
        ], $data);
    }

    public function testSetByDataArray(): void {
        $user = new TestUser();
        $this->em->setByDataArray($user, ['name' => 'Bob', 'id' => 5]);
        $this->assertSame('Bob', $user->name);
        $this->assertSame(5, $user->id);
        $this->assertFalse($user->isDirty($this->em->fetchDataArray($user)));
    }

    public function testSetByDataArrayThrowsForUnknownColumn(): void {
        $this->expectException(EntityManagerException::class);
        $user = new TestUser();
        $this->em->setByDataArray($user, ['nonexistent' => 'value']);
    }
}
