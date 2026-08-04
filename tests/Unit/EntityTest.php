<?php

namespace Dynart\Micro\Entities\Test\Unit;

use Dynart\Micro\Entities\Entity;
use PHPUnit\Framework\TestCase;

class EntityTestEntity extends Entity {
    public string $name = '';
    public int $age = 0;
}

class AliasedEventEntity extends Entity {
    protected static string $eventName = 'aliased';
}

class EntityTest extends TestCase {

    public function testNewEntityIsNew(): void {
        $entity = new EntityTestEntity();
        $this->assertTrue($entity->isNew());
    }

    public function testSetNew(): void {
        $entity = new EntityTestEntity();
        $entity->setNew(false);
        $this->assertFalse($entity->isNew());
    }

    public function testGetDirtyFieldsWithoutSnapshot(): void {
        $entity = new EntityTestEntity();
        $data = ['name' => 'Alice', 'age' => 30];
        $this->assertSame($data, $entity->getDirtyFields($data));
    }

    public function testGetDirtyFieldsNoChanges(): void {
        $entity = new EntityTestEntity();
        $data = ['name' => 'Alice', 'age' => 30];
        $entity->takeSnapshot($data);
        $this->assertSame([], $entity->getDirtyFields($data));
    }

    public function testGetDirtyFieldsWithChange(): void {
        $entity = new EntityTestEntity();
        $entity->takeSnapshot(['name' => 'Alice', 'age' => 30]);
        $this->assertSame(
            ['name' => 'Bob'],
            $entity->getDirtyFields(['name' => 'Bob', 'age' => 30])
        );
    }

    public function testGetDirtyFieldsWithExtraKey(): void {
        $entity = new EntityTestEntity();
        $entity->takeSnapshot(['name' => 'Alice']);
        $this->assertSame(
            ['age' => 30],
            $entity->getDirtyFields(['name' => 'Alice', 'age' => 30])
        );
    }

    public function testIsDirtyClean(): void {
        $entity = new EntityTestEntity();
        $data = ['name' => 'Alice', 'age' => 30];
        $entity->takeSnapshot($data);
        $this->assertFalse($entity->isDirty($data));
    }

    public function testIsDirtyDirty(): void {
        $entity = new EntityTestEntity();
        $entity->takeSnapshot(['name' => 'Alice', 'age' => 30]);
        $this->assertTrue($entity->isDirty(['name' => 'Bob', 'age' => 30]));
    }

    public function testClearSnapshot(): void {
        $entity = new EntityTestEntity();
        $data = ['name' => 'Alice', 'age' => 30];
        $entity->takeSnapshot($data);
        $entity->clearSnapshot();
        $this->assertSame($data, $entity->getDirtyFields($data));
    }

    // --- Events ---

    public function testEventNamespaceFallsBackToTheClassName(): void {
        $this->assertSame(
            'entity.dynart.micro.entities.test.unit.entitytestentity',
            EntityTestEntity::eventNamespace()
        );
    }

    public function testEventNamespaceUsesTheDeclaredAlias(): void {
        $this->assertSame('entity.aliased', AliasedEventEntity::eventNamespace());
    }

    public function testEventBuildsAFullName(): void {
        $this->assertSame('entity.aliased:something_happened', AliasedEventEntity::event('something_happened'));
    }

    public function testBeforeSaveEvent(): void {
        $entity = new EntityTestEntity();
        $this->assertSame(
            'entity.dynart.micro.entities.test.unit.entitytestentity:before_save',
            $entity->beforeSaveEvent()
        );
    }

    public function testAfterSaveEvent(): void {
        $entity = new EntityTestEntity();
        $this->assertSame(
            'entity.dynart.micro.entities.test.unit.entitytestentity:after_save',
            $entity->afterSaveEvent()
        );
    }

    public function testBeforeDeleteEvent(): void {
        $entity = new AliasedEventEntity();
        $this->assertSame('entity.aliased:before_delete', $entity->beforeDeleteEvent());
    }

    public function testAfterDeleteEvent(): void {
        $entity = new AliasedEventEntity();
        $this->assertSame('entity.aliased:after_delete', $entity->afterDeleteEvent());
    }

    /**
     * The entity. prefix is what keeps ORM level events from colliding with the service level
     * events of an application, so it has to be there for aliased entities too.
     */
    public function testEveryEventNamespaceStartsWithThePrefix(): void {
        $this->assertStringStartsWith(Entity::EVENT_NAMESPACE_PREFIX.'.', AliasedEventEntity::eventNamespace());
        $this->assertStringStartsWith(Entity::EVENT_NAMESPACE_PREFIX.'.', EntityTestEntity::eventNamespace());
    }

    public function testSnapshotReturnsTheStoredData(): void {
        $entity = new EntityTestEntity();
        $data = ['name' => 'Alice', 'age' => 30];
        $entity->takeSnapshot($data);
        $this->assertSame($data, $entity->snapshot());
    }
}
