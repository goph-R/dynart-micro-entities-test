<?php

namespace Dynart\Micro\Entities\Test\Unit;

use Dynart\Micro\Entities\Entity;
use PHPUnit\Framework\TestCase;

class EntityTestEntity extends Entity {
    public string $name = '';
    public int $age = 0;
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

    public function testBeforeSaveEvent(): void {
        $entity = new EntityTestEntity();
        $this->assertSame(EntityTestEntity::class . '.before_save', $entity->beforeSaveEvent());
    }

    public function testAfterSaveEvent(): void {
        $entity = new EntityTestEntity();
        $this->assertSame(EntityTestEntity::class . '.after_save', $entity->afterSaveEvent());
    }
}
