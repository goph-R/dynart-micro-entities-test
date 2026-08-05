<?php

namespace Dynart\Micro\Entities\Test\Integration;

use Dynart\Micro\EventService;
use Dynart\Micro\Entities\AuditService;
use Dynart\Micro\Entities\EntityManager;
use Dynart\Micro\Entities\Revision;
use Dynart\Micro\Entities\Test\Entities\TestAudited;
use Dynart\Micro\Entities\Test\Entities\TestUser;
use Dynart\Micro\Entities\Test\TestHelper;

class AuditServiceTest extends IntegrationTestCase {

    private AuditService $audit;
    private EventService $realEvents;

    protected function setUp(): void {
        parent::setUp();
        // the audit service needs real subscriptions, the stub swallows them
        $this->realEvents = new EventService();
        $this->em = new EntityManager($this->config, $this->db, $this->realEvents);
        TestHelper::registerEntity($this->em, TestUser::class);
        TestHelper::registerEntity($this->em, TestAudited::class);
        $this->qb = new \Dynart\Micro\Entities\QueryBuilder\MariaQueryBuilder($this->config, $this->db, $this->em);
        $this->qe = new \Dynart\Micro\Entities\QueryExecutor($this->db, $this->em, $this->qb);

        // postConstruct() registers the Revision entity, which dropTable() needs below
        $this->audit = new AuditService($this->em, $this->db, $this->realEvents);
        $this->audit->postConstruct();

        $this->dropAuditTable(TestAudited::class);
        $this->dropTable(TestAudited::class);
        $this->dropTable(TestUser::class);
        $this->dropTable(Revision::class);

        $this->qe->createTable(Revision::class);
        $this->qe->createTable(TestUser::class);
        $this->qe->createTableWithAudit(TestAudited::class);
        $this->audit->subscribeAll();
    }

    protected function tearDown(): void {
        $this->dropAuditTable(TestAudited::class);
        $this->dropTable(TestAudited::class);
        $this->dropTable(TestUser::class);
        $this->dropTable(Revision::class);
    }

    private function auditRows(): array {
        return $this->db->fetchAll('select * from '.$this->safeAuditTableName(TestAudited::class).' order by `rev_id` asc');
    }

    private function newAudited(string $slug = 'first', string $title = 'First'): TestAudited {
        $entity = new TestAudited();
        $entity->slug = $slug;
        $entity->title = $title;
        return $entity;
    }

    // --- Table creation ---

    public function testCreateTableWithAuditCreatesBothTables(): void {
        $this->assertTrue($this->qe->isTableExist(TestAudited::class));
        $this->assertTrue($this->qe->isAuditTableExist(TestAudited::class));
    }

    public function testNonAuditableEntityGetsNoMirrorTable(): void {
        $this->qe->createTableWithAudit(TestUser::class, true);
        $this->assertFalse($this->qe->isAuditTableExist(TestUser::class));
    }

    // --- Writing history ---

    public function testInsertWritesAnAddRow(): void {
        $this->em->save($this->newAudited('first', 'First'));
        $rows = $this->auditRows();
        $this->assertCount(1, $rows);
        $this->assertSame(AuditService::TYPE_ADD, $rows[0]['rev_type']);
        $this->assertSame('first', $rows[0]['slug']);
        $this->assertSame('First', $rows[0]['title']);
    }

    public function testUpdateWritesAModRow(): void {
        $entity = $this->newAudited('first', 'First');
        $this->em->save($entity);
        $this->audit->reset();
        $entity->title = 'Changed';
        $this->em->save($entity);
        $rows = $this->auditRows();
        $this->assertCount(2, $rows);
        $this->assertSame(AuditService::TYPE_MOD, $rows[1]['rev_type']);
        $this->assertSame('Changed', $rows[1]['title']);
    }

    public function testDeleteWritesADelRowWithThePreviousState(): void {
        $entity = $this->newAudited('first', 'First');
        $this->em->save($entity);
        $this->audit->reset();
        $this->em->deleteById(TestAudited::class, $entity->id);
        $rows = $this->auditRows();
        $this->assertCount(2, $rows);
        $this->assertSame(AuditService::TYPE_DEL, $rows[1]['rev_type']);
        $this->assertSame('First', $rows[1]['title']);
        $this->assertEquals($entity->id, $rows[1]['id']);
    }

    /**
     * An entity has at most one row per revision, holding its state at the end of it. Changing
     * the same entity twice inside one revision overwrites the earlier row.
     */
    public function testOneRowPerEntityPerRevision(): void {
        $entity = $this->newAudited('first', 'One');
        $this->em->save($entity);
        $entity->title = 'Two';
        $this->em->save($entity);
        $entity->title = 'Three';
        $this->em->save($entity);
        $rows = $this->auditRows();
        $this->assertCount(1, $rows);
        $this->assertSame('Three', $rows[0]['title']);
        $this->assertSame(AuditService::TYPE_MOD, $rows[0]['rev_type']);
    }

    public function testDeleteInTheSameRevisionOverwritesTheInsert(): void {
        $entity = $this->newAudited('first', 'First');
        $this->em->save($entity);
        $this->em->deleteById(TestAudited::class, $entity->id);
        $rows = $this->auditRows();
        $this->assertCount(1, $rows);
        $this->assertSame(AuditService::TYPE_DEL, $rows[0]['rev_type']);
    }

    public function testDifferentEntitiesDoNotOverwriteEachOther(): void {
        $this->em->save($this->newAudited('a', 'A'));
        $this->em->save($this->newAudited('b', 'B'));
        $this->assertCount(2, $this->auditRows());
    }

    /**
     * save() writes nothing when no field is dirty, so there is nothing to record either.
     */
    public function testSaveWithoutChangesWritesNothing(): void {
        $entity = $this->newAudited();
        $this->em->save($entity);
        $this->em->save($entity);
        $this->assertCount(1, $this->auditRows());
    }

    public function testHistorySurvivesTheDeletionOfTheSourceRow(): void {
        $entity = $this->newAudited('first', 'First');
        $this->em->save($entity);
        $this->audit->reset();
        $this->em->deleteById(TestAudited::class, $entity->id);
        $count = $this->db->fetchOne('select count(1) from '.$this->safeTableName(TestAudited::class));
        $this->assertEquals(0, $count);
        $this->assertCount(2, $this->auditRows());
    }

    /**
     * The same slug appears once per revision, which a unique constraint copied from the source
     * table would reject - so the mirror table must not have one.
     */
    public function testTheSameRowCanBeRecordedInManyRevisions(): void {
        $entity = $this->newAudited('same-slug', 'One');
        $this->em->save($entity);
        $this->audit->reset();
        $entity->title = 'Two';
        $this->em->save($entity);
        $this->audit->reset();
        $entity->title = 'Three';
        $this->em->save($entity);
        $rows = $this->auditRows();
        $this->assertCount(3, $rows);
        foreach ($rows as $row) {
            $this->assertSame('same-slug', $row['slug']);
        }
    }

    /**
     * A foreign key copied from the source table would delete the history along with the row it
     * points at, so the mirror table must not have one either.
     */
    public function testHistorySurvivesACascadingForeignKeyDelete(): void {
        $user = new TestUser();
        $user->name = 'Alice';
        $this->em->save($user);
        $entity = $this->newAudited('first', 'First');
        $entity->user_id = $user->id;
        $this->em->save($entity);
        $this->em->deleteById(TestUser::class, $user->id);
        $sourceCount = $this->db->fetchOne('select count(1) from '.$this->safeTableName(TestAudited::class));
        $this->assertEquals(0, $sourceCount); // cascaded away
        $this->assertCount(1, $this->auditRows()); // history kept
    }

    // --- Revisions ---

    public function testAllChangesOfOneRequestShareARevision(): void {
        $this->em->save($this->newAudited('a', 'A'));
        $this->em->save($this->newAudited('b', 'B'));
        $rows = $this->auditRows();
        $this->assertCount(2, $rows);
        $this->assertSame($rows[0]['rev_id'], $rows[1]['rev_id']);
        $this->assertEquals(1, $this->db->fetchOne('select count(1) from '.$this->safeTableName(Revision::class)));
    }

    public function testResetStartsANewRevision(): void {
        $this->em->save($this->newAudited('a', 'A'));
        $this->audit->reset();
        $this->em->save($this->newAudited('b', 'B'));
        $rows = $this->auditRows();
        $this->assertNotSame($rows[0]['rev_id'], $rows[1]['rev_id']);
        $this->assertEquals(2, $this->db->fetchOne('select count(1) from '.$this->safeTableName(Revision::class)));
    }

    public function testNoRevisionIsCreatedWithoutAnyChange(): void {
        $this->assertEquals(0, $this->db->fetchOne('select count(1) from '.$this->safeTableName(Revision::class)));
    }

    public function testRevisionRecordsTheUserId(): void {
        $this->audit->setUserId('42');
        $this->em->save($this->newAudited());
        $userId = $this->db->fetchOne('select `user_id` from '.$this->safeTableName(Revision::class));
        $this->assertEquals('42', $userId);
    }

    public function testRevisionHasATimestamp(): void {
        $this->em->save($this->newAudited());
        $createdAt = $this->db->fetchOne('select `created_at` from '.$this->safeTableName(Revision::class));
        $this->assertNotEmpty($createdAt);
    }

    // --- Enabling / disabling ---

    public function testDisabledAuditWritesNothing(): void {
        $this->audit->setEnabled(false);
        $this->em->save($this->newAudited());
        $this->assertCount(0, $this->auditRows());
    }

    public function testReenabledAuditWritesAgain(): void {
        $this->audit->setEnabled(false);
        $this->em->save($this->newAudited('a', 'A'));
        $this->audit->setEnabled(true);
        $this->em->save($this->newAudited('b', 'B'));
        $this->assertCount(1, $this->auditRows());
    }

    // --- Adding a column to a live table ---

    /**
     * The mirror has to gain the column too, or the next save of *any* row of that class fails
     *
     * Every audited write copies the whole row into the `_aud` table, so a source table with a
     * column its mirror does not have is not a cosmetic difference - it is an insert with the
     * wrong number of columns, for every entity of the class rather than the one being changed.
     */
    public function testAddColumnWithAuditKeepsTheMirrorInStep(): void {
        $table = $this->safeTableName(TestAudited::class);
        $auditTable = $this->safeAuditTableName(TestAudited::class);
        $this->db->query('alter table '.$table.' drop column `title`');
        $this->db->query('alter table '.$auditTable.' drop column `title`');

        $this->qe->addColumnWithAudit(TestAudited::class, 'title');

        // the save is the assertion: with the mirror a column short this throws
        $this->em->save($this->newAudited('after', 'A title'));
        $rows = $this->auditRows();
        $this->assertCount(1, $rows);
        $this->assertSame('A title', $rows[0]['title']);
    }

    public function testAddColumnWithAuditIsSkippedForSomethingNotAudited(): void {
        $table = $this->safeTableName(TestUser::class);
        $this->db->query('alter table '.$table.' drop column `active`');
        $this->qe->addColumnWithAudit(TestUser::class, 'active');   // no mirror to touch
        $this->db->insert($this->em->tableName(TestUser::class), ['name' => 'Alice', 'active' => true]);
        $this->assertSame(['1'], array_map('strval', $this->db->fetchColumn('select `active` from '.$table)));
    }

    // --- Subscriptions ---

    public function testSubscribeAllIsIdempotent(): void {
        $this->audit->subscribeAll();
        $this->audit->subscribeAll();
        $this->em->save($this->newAudited());
        $this->assertCount(1, $this->auditRows());
    }
}
