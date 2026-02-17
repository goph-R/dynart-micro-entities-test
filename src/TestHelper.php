<?php

namespace Dynart\Micro\Entities\Test;

use Dynart\Micro\Entities\Attribute\Column;
use Dynart\Micro\Entities\EntityManager;
use ReflectionClass;

class TestHelper {

    public static function createEntityManager(string $tablePrefix = ''): EntityManager {
        $db = new TestDatabase();
        $db->tablePrefix = $tablePrefix;
        return new EntityManager(new StubConfig(), $db, new StubEvents());
    }

    public static function registerEntity(EntityManager $em, string $className): void {
        $ref = new ReflectionClass($className);
        foreach ($ref->getProperties() as $property) {
            foreach ($property->getAttributes(Column::class) as $attr) {
                $em->addColumn($className, $property->getName(), $attr->newInstance());
            }
        }
    }
}
