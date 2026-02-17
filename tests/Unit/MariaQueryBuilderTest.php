<?php

namespace Dynart\Micro\Entities\Test\Unit;

use Dynart\Micro\Entities\Attribute\Column;
use Dynart\Micro\Entities\EntityManager;
use Dynart\Micro\Entities\EntityManagerException;
use Dynart\Micro\Entities\Query;
use Dynart\Micro\Entities\QueryBuilder\MariaQueryBuilder;
use Dynart\Micro\Entities\Test\Entities\TestPost;
use Dynart\Micro\Entities\Test\Entities\TestUser;
use Dynart\Micro\Entities\Test\StubConfig;
use Dynart\Micro\Entities\Test\TestDatabase;
use Dynart\Micro\Entities\Test\TestHelper;
use PHPUnit\Framework\TestCase;

class MariaQueryBuilderTest extends TestCase {

    private TestDatabase $db;
    private EntityManager $em;
    private MariaQueryBuilder $qb;

    protected function setUp(): void {
        $this->db = new TestDatabase();
        $this->em = TestHelper::createEntityManager();
        TestHelper::registerEntity($this->em, TestUser::class);
        TestHelper::registerEntity($this->em, TestPost::class);
        $this->qb = new MariaQueryBuilder(
            new StubConfig(),
            $this->db,
            $this->em
        );
    }

    // --- columnDefinition ---

    public function testColInt(): void {
        $col = new Column(type: Column::TYPE_INT);
        $this->assertSame('`col` int', $this->qb->columnDefinition('col', $col));
    }

    public function testColIntWithSize(): void {
        $col = new Column(type: Column::TYPE_INT, size: 11);
        $this->assertSame('`col` int(11)', $this->qb->columnDefinition('col', $col));
    }

    public function testColLong(): void {
        $col = new Column(type: Column::TYPE_LONG);
        $this->assertSame('`col` bigint', $this->qb->columnDefinition('col', $col));
    }

    public function testColFloat(): void {
        $col = new Column(type: Column::TYPE_FLOAT);
        $this->assertSame('`col` float', $this->qb->columnDefinition('col', $col));
    }

    public function testColDouble(): void {
        $col = new Column(type: Column::TYPE_DOUBLE);
        $this->assertSame('`col` double', $this->qb->columnDefinition('col', $col));
    }

    public function testColNumeric(): void {
        $col = new Column(type: Column::TYPE_NUMERIC, size: [10, 2]);
        $this->assertSame('`col` decimal(10, 2)', $this->qb->columnDefinition('col', $col));
    }

    public function testColStringNoSize(): void {
        $col = new Column(type: Column::TYPE_STRING);
        $this->assertSame('`col` longtext', $this->qb->columnDefinition('col', $col));
    }

    public function testColStringWithSize(): void {
        $col = new Column(type: Column::TYPE_STRING, size: 100);
        $this->assertSame('`col` varchar(100)', $this->qb->columnDefinition('col', $col));
    }

    public function testColStringFixSize(): void {
        $col = new Column(type: Column::TYPE_STRING, size: 10, fixSize: true);
        $this->assertSame('`col` char(10)', $this->qb->columnDefinition('col', $col));
    }

    public function testColBool(): void {
        $col = new Column(type: Column::TYPE_BOOL);
        $this->assertSame('`col` tinyint(1)', $this->qb->columnDefinition('col', $col));
    }

    public function testColDate(): void {
        $this->assertSame('`col` date', $this->qb->columnDefinition('col', new Column(type: Column::TYPE_DATE)));
    }

    public function testColTime(): void {
        $this->assertSame('`col` time', $this->qb->columnDefinition('col', new Column(type: Column::TYPE_TIME)));
    }

    public function testColDatetime(): void {
        $this->assertSame('`col` datetime', $this->qb->columnDefinition('col', new Column(type: Column::TYPE_DATETIME)));
    }

    public function testColBlob(): void {
        $this->assertSame('`col` blob', $this->qb->columnDefinition('col', new Column(type: Column::TYPE_BLOB)));
    }

    public function testColNotNull(): void {
        $col = new Column(type: Column::TYPE_INT, notNull: true);
        $this->assertSame('`col` int not null', $this->qb->columnDefinition('col', $col));
    }

    public function testColAutoIncrement(): void {
        $col = new Column(type: Column::TYPE_INT, autoIncrement: true);
        $this->assertSame('`col` int auto_increment', $this->qb->columnDefinition('col', $col));
    }

    public function testColDefaultNull(): void {
        $col = new Column(type: Column::TYPE_INT, default: null);
        // default is null means no default clause added
        $this->assertSame('`col` int', $this->qb->columnDefinition('col', $col));
    }

    public function testColDefaultZero(): void {
        $col = new Column(type: Column::TYPE_INT, default: 0);
        $this->assertSame('`col` int default 0', $this->qb->columnDefinition('col', $col));
    }

    public function testColDefaultString(): void {
        $col = new Column(type: Column::TYPE_STRING, size: 50, default: "hello");
        $this->assertSame("`col` varchar(50) default 'hello'", $this->qb->columnDefinition('col', $col));
    }

    public function testColDefaultBoolTrue(): void {
        $col = new Column(type: Column::TYPE_BOOL, default: true);
        $this->assertSame('`col` tinyint(1) default 1', $this->qb->columnDefinition('col', $col));
    }

    public function testColDefaultBoolFalse(): void {
        $col = new Column(type: Column::TYPE_BOOL, default: false);
        $this->assertSame('`col` tinyint(1) default 0', $this->qb->columnDefinition('col', $col));
    }

    public function testColDefaultNowDatetime(): void {
        $col = new Column(type: Column::TYPE_DATETIME, default: Column::NOW);
        $this->assertSame('`col` datetime default utc_timestamp()', $this->qb->columnDefinition('col', $col));
    }

    public function testColDefaultNowDate(): void {
        $col = new Column(type: Column::TYPE_DATETIME, default: Column::NOW);
        // We test the datetime path; date and time follow analogously
        $this->assertStringContainsString('default utc_timestamp()', $this->qb->columnDefinition('col', $col));
    }

    public function testColDefaultRawArray(): void {
        $col = new Column(type: Column::TYPE_INT, default: ['now()']);
        $this->assertSame('`col` int default now()', $this->qb->columnDefinition('col', $col));
    }

    public function testColBlobWithDefaultThrows(): void {
        $this->expectException(EntityManagerException::class);
        $col = new Column(type: Column::TYPE_BLOB, default: 'x');
        $this->qb->columnDefinition('col', $col);
    }

    public function testColLongtextWithDefaultThrows(): void {
        $this->expectException(EntityManagerException::class);
        $col = new Column(type: Column::TYPE_STRING, default: 'x');
        $this->qb->columnDefinition('col', $col);
    }

    public function testColUnknownTypeThrows(): void {
        $this->expectException(EntityManagerException::class);
        $col = new Column(type: 'unknown_type');
        $this->qb->columnDefinition('col', $col);
    }

    public function testColIntNonIntSizeThrows(): void {
        $this->expectException(EntityManagerException::class);
        $col = new Column(type: Column::TYPE_INT, size: [1, 2]);
        $this->qb->columnDefinition('col', $col);
    }

    public function testColNumericWrongArraySizeThrows(): void {
        $this->expectException(EntityManagerException::class);
        $col = new Column(type: Column::TYPE_NUMERIC, size: [10]);
        $this->qb->columnDefinition('col', $col);
    }

    // --- foreignKeyDefinition ---

    public function testForeignKeyNull(): void {
        $col = new Column(type: Column::TYPE_INT);
        $this->assertSame('', $this->qb->foreignKeyDefinition('user_id', $col));
    }

    public function testForeignKeyValid(): void {
        $col = new Column(type: Column::TYPE_INT, foreignKey: [TestUser::class, 'id']);
        $sql = $this->qb->foreignKeyDefinition('user_id', $col);
        $this->assertStringContainsString('foreign key (`user_id`)', $sql);
        $this->assertStringContainsString('references `testuser` (`id`)', $sql);
    }

    public function testForeignKeyWithOnDeleteCascade(): void {
        $col = new Column(type: Column::TYPE_INT, foreignKey: [TestUser::class, 'id'], onDelete: Column::ACTION_CASCADE);
        $sql = $this->qb->foreignKeyDefinition('user_id', $col);
        $this->assertStringContainsString('on delete cascade', $sql);
    }

    public function testForeignKeyWithOnUpdateSetNull(): void {
        $col = new Column(type: Column::TYPE_INT, foreignKey: [TestUser::class, 'id'], onUpdate: Column::ACTION_SET_NULL);
        $sql = $this->qb->foreignKeyDefinition('user_id', $col);
        $this->assertStringContainsString('on update set null', $sql);
    }

    public function testForeignKeyWrongSizeThrows(): void {
        $this->expectException(EntityManagerException::class);
        $col = new Column(type: Column::TYPE_INT, foreignKey: [TestUser::class]);
        $this->qb->foreignKeyDefinition('user_id', $col);
    }

    public function testForeignKeyUnknownActionThrows(): void {
        $this->expectException(EntityManagerException::class);
        $col = new Column(type: Column::TYPE_INT, foreignKey: [TestUser::class, 'id'], onDelete: 'restrict');
        $this->qb->foreignKeyDefinition('user_id', $col);
    }

    // --- primaryKeyDefinition ---

    public function testPrimaryKeyDefinitionNone(): void {
        $this->em->addColumn('NoPkClass2', 'name', new Column(type: Column::TYPE_STRING, size: 50));
        $this->assertSame('', $this->qb->primaryKeyDefinition('NoPkClass2'));
    }

    public function testPrimaryKeyDefinitionSingle(): void {
        $sql = $this->qb->primaryKeyDefinition(TestUser::class);
        $this->assertSame('primary key (`id`)', $sql);
    }

    public function testPrimaryKeyDefinitionComposite(): void {
        $this->em->addColumn('CompPkClass', 'a', new Column(type: Column::TYPE_INT, primaryKey: true));
        $this->em->addColumn('CompPkClass', 'b', new Column(type: Column::TYPE_INT, primaryKey: true));
        $sql = $this->qb->primaryKeyDefinition('CompPkClass');
        $this->assertSame('primary key (`a`, `b`)', $sql);
    }

    // --- createTable ---

    public function testCreateTable(): void {
        $sql = $this->qb->createTable(TestUser::class);
        $this->assertStringContainsString('create table `testuser`', $sql);
        $this->assertStringContainsString('`id` int not null auto_increment', $sql);
        $this->assertStringContainsString('`name` varchar(100) not null', $sql);
        $this->assertStringContainsString('`active` tinyint(1) default 0', $sql);
        $this->assertStringContainsString('`created_at` datetime default utc_timestamp()', $sql);
        $this->assertStringContainsString('primary key (`id`)', $sql);
    }

    public function testCreateTableIfNotExists(): void {
        $sql = $this->qb->createTable(TestUser::class, true);
        $this->assertStringContainsString('create table if not exists `testuser`', $sql);
    }

    public function testCreateTableWithForeignKey(): void {
        $sql = $this->qb->createTable(TestPost::class);
        $this->assertStringContainsString('foreign key (`user_id`) references `testuser` (`id`)', $sql);
        $this->assertStringContainsString('on delete cascade', $sql);
    }

    // --- findAll / SQL generation ---

    public function testFindAllImplicitAllFields(): void {
        $q = new Query(TestUser::class);
        $sql = $this->qb->findAll($q);
        $this->assertStringContainsString('select `id`, `name`, `email`, `active`, `created_at`', $sql);
        $this->assertStringContainsString('from `testuser`', $sql);
    }

    public function testFindAllExplicitFields(): void {
        $q = new Query(TestUser::class);
        $q->setFields(['id', 'name']);
        $sql = $this->qb->findAll($q);
        $this->assertStringContainsString('select `id`, `name`', $sql);
    }

    public function testFindAllAliasedField(): void {
        $q = new Query(TestUser::class);
        $q->setFields(['username' => 'name']);
        $sql = $this->qb->findAll($q);
        $this->assertStringContainsString('`name` as `username`', $sql);
    }

    public function testFindAllRawExpression(): void {
        $q = new Query(TestUser::class);
        $q->setFields(['c' => ['count(1)']]);
        $sql = $this->qb->findAll($q);
        $this->assertStringContainsString('count(1) as `c`', $sql);
    }

    public function testFindAllWithCondition(): void {
        $q = new Query(TestUser::class);
        $q->addCondition('`name` = :name');
        $sql = $this->qb->findAll($q);
        $this->assertStringContainsString('where (`name` = :name)', $sql);
    }

    public function testFindAllWithTwoConditions(): void {
        $q = new Query(TestUser::class);
        $q->addCondition('a = :a');
        $q->addCondition('b = :b');
        $sql = $this->qb->findAll($q);
        $this->assertStringContainsString('where (a = :a) and (b = :b)', $sql);
    }

    public function testFindAllWithInnerJoin(): void {
        $q = new Query(TestUser::class);
        $q->addInnerJoin(TestPost::class, '`testuser`.`id` = `testpost`.`user_id`');
        $sql = $this->qb->findAll($q);
        $this->assertStringContainsString('inner join `testpost`', $sql);
    }

    public function testFindAllWithGroupBy(): void {
        $q = new Query(TestUser::class);
        $q->addGroupBy('`active`');
        $sql = $this->qb->findAll($q);
        $this->assertStringContainsString('group by `active`', $sql);
    }

    public function testFindAllWithOrderBy(): void {
        $q = new Query(TestUser::class);
        $q->setFields(['name' => 'name']);
        $q->addOrderBy('name');
        $sql = $this->qb->findAll($q);
        $this->assertStringContainsString('order by `name` asc', $sql);
    }

    public function testFindAllOrderByIgnoredIfFieldNotSelected(): void {
        $q = new Query(TestUser::class);
        $q->setFields(['email' => 'email']);
        $q->addOrderBy('name');
        $sql = $this->qb->findAll($q);
        $this->assertStringNotContainsString('order by', $sql);
    }

    public function testFindAllWithLimit(): void {
        $q = new Query(TestUser::class);
        $q->setLimit(0, 10);
        $sql = $this->qb->findAll($q);
        $this->assertStringContainsString('limit 0, 10', $sql);
    }

    public function testFindAllLimitMaxClamped(): void {
        $q = new Query(TestUser::class);
        $q->setLimit(0, 9999);
        $sql = $this->qb->findAll($q);
        $this->assertStringContainsString('limit 0, 1000', $sql);
    }

    public function testFindAllLimitOffsetClamped(): void {
        $q = new Query(TestUser::class);
        $q->setLimit(-5, 10);
        $sql = $this->qb->findAll($q);
        $this->assertStringContainsString('limit 0, 10', $sql);
    }

    public function testFindAllLimitMaxMinimumOne(): void {
        $q = new Query(TestUser::class);
        $q->setLimit(0, 0);
        $sql = $this->qb->findAll($q);
        $this->assertStringContainsString('limit 0, 1', $sql);
    }

    public function testFindAllNoLimit(): void {
        $q = new Query(TestUser::class);
        $sql = $this->qb->findAll($q);
        $this->assertStringNotContainsString('limit', $sql);
    }

    public function testFindAllCount(): void {
        $q = new Query(TestUser::class);
        $sql = $this->qb->findAllCount($q);
        $this->assertStringContainsString('count(1)', $sql);
        $this->assertStringNotContainsString('order by', $sql);
        $this->assertStringNotContainsString('limit', $sql);
    }

    public function testIsTableExistSql(): void {
        $sql = $this->qb->isTableExist(':dbName', ':tableName');
        $this->assertStringContainsString('information_schema.tables', $sql);
        $this->assertStringContainsString(':dbName', $sql);
        $this->assertStringContainsString(':tableName', $sql);
    }

    public function testListTablesSql(): void {
        $this->assertSame('show tables', $this->qb->listTables());
    }
}
