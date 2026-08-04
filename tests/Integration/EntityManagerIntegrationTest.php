<?php

namespace Dynart\Micro\Entities\Test\Integration;

use Dynart\Micro\Entities\Entity;
use Dynart\Micro\Entities\Test\Entities\TestPost;
use Dynart\Micro\Entities\Test\Entities\TestUser;

class EntityManagerIntegrationTest extends IntegrationTestCase {

    protected function setUp(): void {
        parent::setUp();
        $this->dropTable(TestPost::class);
        $this->dropTable(TestUser::class);
        $this->qe->createTable(TestUser::class);
        $this->qe->createTable(TestPost::class);
    }

    protected function tearDown(): void {
        $this->dropTable(TestPost::class);
        $this->dropTable(TestUser::class);
    }

    private function newUser(string $name = 'Alice', string $email = '', bool $active = false): TestUser {
        $user = new TestUser();
        $user->name = $name;
        $user->email = $email;
        $user->active = $active;
        return $user;
    }

    // --- save() new entity ---

    public function testSaveNewEntityInsertsRow(): void {
        $this->em->save($this->newUser('Alice'));
        $count = $this->db->fetchOne('select count(1) from ' . $this->safeTableName(TestUser::class));
        $this->assertEquals(1, $count);
    }

    public function testSaveNewEntityBackfillsAutoIncrementPk(): void {
        $user = $this->newUser('Alice');
        $this->em->save($user);
        $this->assertGreaterThan(0, (int)$user->id);
    }

    public function testSaveNewEntitySetsNotNew(): void {
        $user = $this->newUser('Alice');
        $this->em->save($user);
        $this->assertFalse($user->isNew());
    }

    public function testSaveNewEntityTakesSnapshot(): void {
        $user = $this->newUser('Alice');
        $this->em->save($user);
        $data = $this->em->fetchDataArray($user);
        $this->assertFalse($user->isDirty($data));
    }

    // --- save() existing entity ---

    public function testSaveDirtyEntityUpdatesOnlyChangedColumns(): void {
        $user = $this->newUser('Alice', 'alice@test.com');
        $this->em->save($user);
        $user->name = 'Bob';
        $this->em->save($user);
        $saved = $this->em->findById(TestUser::class, $user->id);
        $this->assertEquals('Bob', $saved->name);
        $this->assertEquals('alice@test.com', $saved->email);
    }

    public function testSaveCleanEntityIssuesNoUpdate(): void {
        $user = $this->newUser('Alice');
        $this->em->save($user);
        $id = $user->id;
        $this->em->save($user); // no changes — should be a no-op
        $saved = $this->em->findById(TestUser::class, $id);
        $this->assertEquals('Alice', $saved->name);
    }

    // --- findById() ---

    public function testFindByIdReturnsEntity(): void {
        $user = $this->newUser('Alice', 'alice@test.com', true);
        $this->em->save($user);
        $found = $this->em->findById(TestUser::class, $user->id);
        $this->assertInstanceOf(TestUser::class, $found);
        $this->assertEquals('Alice', $found->name);
        $this->assertEquals('alice@test.com', $found->email);
        $this->assertTrue((bool)$found->active);
    }

    public function testFindByIdSetsNotNew(): void {
        $user = $this->newUser('Alice');
        $this->em->save($user);
        $found = $this->em->findById(TestUser::class, $user->id);
        $this->assertFalse($found->isNew());
    }

    public function testFindByIdTakesSnapshot(): void {
        $user = $this->newUser('Alice');
        $this->em->save($user);
        $found = $this->em->findById(TestUser::class, $user->id);
        $data = $this->em->fetchDataArray($found);
        $this->assertFalse($found->isDirty($data));
    }

    // --- Events ---

    public function testSaveEmitsBeforeSaveEvent(): void {
        $this->em->save($this->newUser('Alice'));
        $this->assertContains(TestUser::event(Entity::EVENT_BEFORE_SAVE), $this->events->emitted);
    }

    public function testSaveEmitsAfterSaveEvent(): void {
        $this->em->save($this->newUser('Alice'));
        $this->assertContains(TestUser::event(Entity::EVENT_AFTER_SAVE), $this->events->emitted);
    }

    // --- findById() ---

    public function testFindByIdReturnsNullWhenMissing(): void {
        $this->assertNull($this->em->findById(TestUser::class, 12345));
    }

    // --- findByIds() ---

    public function testFindByIdsReturnsPersistedEntities(): void {
        $user1 = $this->newUser('Alice');
        $user2 = $this->newUser('Bob');
        $this->em->save($user1);
        $this->em->save($user2);
        $found = $this->em->findByIds(TestUser::class, [$user1->id, $user2->id]);
        $this->assertCount(2, $found);
        foreach ($found as $entity) {
            $this->assertFalse($entity->isNew());
        }
        $names = array_map(fn($e) => $e->name, $found);
        sort($names);
        $this->assertSame(['Alice', 'Bob'], $names);
    }

    public function testFindByIdsWithEmptyArray(): void {
        $this->assertSame([], $this->em->findByIds(TestUser::class, []));
    }

    // --- deleteById() ---

    public function testDeleteByIdRemovesRow(): void {
        $user = $this->newUser('Alice');
        $this->em->save($user);
        $this->em->deleteById(TestUser::class, $user->id);
        $count = $this->db->fetchOne('select count(1) from ' . $this->safeTableName(TestUser::class));
        $this->assertEquals(0, $count);
    }

    public function testDeleteByIdEmitsDeleteEvents(): void {
        $user = $this->newUser('Alice');
        $this->em->save($user);
        $this->events->emitted = [];
        $this->em->deleteById(TestUser::class, $user->id);
        $this->assertContains(TestUser::event(Entity::EVENT_BEFORE_DELETE), $this->events->emitted);
        $this->assertContains(TestUser::event(Entity::EVENT_AFTER_DELETE), $this->events->emitted);
    }

    /**
     * An auditing listener needs the previous state of the row, so the entity has to be loaded
     * before it is deleted and handed to the event.
     */
    public function testDeleteByIdEventCarriesThePreviousState(): void {
        $user = $this->newUser('Alice', 'alice@test.com', true);
        $this->em->save($user);
        $captured = null;
        $this->events->args = [];
        $this->em->deleteById(TestUser::class, $user->id);
        $captured = $this->events->args[TestUser::event(Entity::EVENT_BEFORE_DELETE)][0] ?? null;
        $this->assertInstanceOf(TestUser::class, $captured);
        $this->assertSame('Alice', $captured->name);
        $this->assertSame('alice@test.com', $captured->email);
    }

    public function testDeleteByIdDoesNothingWhenRowIsMissing(): void {
        $this->events->emitted = [];
        $this->em->deleteById(TestUser::class, 12345);
        $this->assertSame([], $this->events->emitted);
    }

    public function testDeleteByIdMarksTheEntityAsNew(): void {
        $user = $this->newUser('Alice');
        $this->em->save($user);
        $this->events->args = [];
        $this->em->deleteById(TestUser::class, $user->id);
        $deleted = $this->events->args[TestUser::event(Entity::EVENT_AFTER_DELETE)][0];
        $this->assertTrue($deleted->isNew());
    }

    // --- deleteByIds() ---

    public function testDeleteByIdsRemovesSpecifiedRows(): void {
        $user1 = $this->newUser('Alice');
        $user2 = $this->newUser('Bob');
        $user3 = $this->newUser('Charlie');
        $this->em->save($user1);
        $this->em->save($user2);
        $this->em->save($user3);
        $this->em->deleteByIds(TestUser::class, [$user1->id, $user2->id]);
        $count = $this->db->fetchOne('select count(1) from ' . $this->safeTableName(TestUser::class));
        $this->assertEquals(1, $count);
        $remaining = $this->db->fetchOne('select `name` from ' . $this->safeTableName(TestUser::class));
        $this->assertEquals('Charlie', $remaining);
    }

    public function testDeleteByIdsEmitsEventsPerEntity(): void {
        $user1 = $this->newUser('Alice');
        $user2 = $this->newUser('Bob');
        $this->em->save($user1);
        $this->em->save($user2);
        $this->events->emitted = [];
        $this->em->deleteByIds(TestUser::class, [$user1->id, $user2->id]);
        $before = array_filter($this->events->emitted, fn($e) => $e === TestUser::event(Entity::EVENT_BEFORE_DELETE));
        $after = array_filter($this->events->emitted, fn($e) => $e === TestUser::event(Entity::EVENT_AFTER_DELETE));
        $this->assertCount(2, $before);
        $this->assertCount(2, $after);
    }

    public function testDeleteByIdsWithEmptyArrayEmitsNothing(): void {
        $this->events->emitted = [];
        $this->em->deleteByIds(TestUser::class, []);
        $this->assertSame([], $this->events->emitted);
    }

    public function testDeleteByIdsWithNoMatchingRowsEmitsNothing(): void {
        $this->events->emitted = [];
        $this->em->deleteByIds(TestUser::class, [12345, 12346]);
        $this->assertSame([], $this->events->emitted);
    }

    // --- insert() / update() ---

    public function testInsertReturnsLastInsertId(): void {
        $id = $this->em->insert(TestUser::class, ['name' => 'Alice']);
        $this->assertNotFalse($id);
        $this->assertIsNumeric($id);
        $this->assertGreaterThan(0, (int)$id);
    }

    public function testUpdateWithCondition(): void {
        $this->em->insert(TestUser::class, ['name' => 'Alice']);
        $this->em->update(TestUser::class, ['name' => 'Bob'], '`name` = :oldName', [':oldName' => 'Alice']);
        $name = $this->db->fetchOne(
            'select `name` from ' . $this->safeTableName(TestUser::class) . " where `name` = 'Bob'"
        );
        $this->assertEquals('Bob', $name);
    }
}
