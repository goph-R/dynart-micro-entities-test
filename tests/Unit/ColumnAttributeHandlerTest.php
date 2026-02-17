<?php

namespace Dynart\Micro\Entities\Test\Unit;

use Dynart\Micro\AttributeHandlerInterface;
use Dynart\Micro\Entities\Attribute\Column;
use Dynart\Micro\Entities\AttributeHandler\ColumnAttributeHandler;
use Dynart\Micro\Entities\Test\Entities\TestUser;
use Dynart\Micro\Entities\Test\TestHelper;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

class ColumnAttributeHandlerTest extends TestCase {

    public function testAttributeClass(): void {
        $em = TestHelper::createEntityManager();
        $handler = new ColumnAttributeHandler($em);
        $this->assertSame(Column::class, $handler->attributeClass());
    }

    public function testTargets(): void {
        $em = TestHelper::createEntityManager();
        $handler = new ColumnAttributeHandler($em);
        $this->assertSame([AttributeHandlerInterface::TARGET_PROPERTY], $handler->targets());
    }

    public function testHandleRegistersColumnObject(): void {
        $em = TestHelper::createEntityManager();
        $handler = new ColumnAttributeHandler($em);

        $ref = new ReflectionClass(TestUser::class);
        $property = $ref->getProperty('name');
        $attribute = $property->getAttributes(Column::class)[0]->newInstance();

        $handler->handle(TestUser::class, $property, $attribute);

        $columns = $em->tableColumns(TestUser::class);
        $this->assertArrayHasKey('name', $columns);
        $this->assertSame($attribute, $columns['name']);
    }

    public function testHandlePassesColumnInstanceDirectly(): void {
        $em = TestHelper::createEntityManager();
        $handler = new ColumnAttributeHandler($em);

        $ref = new ReflectionClass(TestUser::class);
        $property = $ref->getProperty('id');
        $attribute = $property->getAttributes(Column::class)[0]->newInstance();

        $handler->handle(TestUser::class, $property, $attribute);

        $columns = $em->tableColumns(TestUser::class);
        $this->assertInstanceOf(Column::class, $columns['id']);
        $this->assertTrue($columns['id']->primaryKey);
        $this->assertTrue($columns['id']->autoIncrement);
    }
}
