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
     * Registers an entity with the manager's own reflection based registration
     */
    public static function registerEntity(EntityManager $em, string $className): void {
        $em->registerEntity($className);
    }
}
