<?php

namespace Dynart\Micro\Entities\Test;

use Dynart\Micro\Entities\Attribute\Column;
use Dynart\Micro\Entities\Attribute\Table;
use Dynart\Micro\Entities\EntityManager;
use ReflectionClass;

class TestHelper {

    public static function createEntityManager(string $tablePrefix = ''): EntityManager {
        $db = new TestDatabase();
        $db->tablePrefix = $tablePrefix;
        return new EntityManager(new StubConfig(), $db, new StubEvents());
    }

    /**
     * Registers an entity the same way the attribute processor does: the class level attributes
     * first, then the property level ones.
     */
    public static function registerEntity(EntityManager $em, string $className): void {
        $ref = new ReflectionClass($className);
        foreach ($ref->getAttributes(Table::class) as $attr) {
            $em->addTable($className, $attr->newInstance());
        }
        foreach ($ref->getProperties() as $property) {
            foreach ($property->getAttributes(Column::class) as $attr) {
                $em->addColumn($className, $property->getName(), $attr->newInstance());
            }
        }
    }
}
