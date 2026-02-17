<?php

namespace Dynart\Micro\Entities\Test\Unit;

use Dynart\Micro\Entities\Attribute\Column;
use PHPUnit\Framework\TestCase;

class ColumnTest extends TestCase {

    public function testTypeConstants(): void {
        $this->assertSame('int',      Column::TYPE_INT);
        $this->assertSame('long',     Column::TYPE_LONG);
        $this->assertSame('float',    Column::TYPE_FLOAT);
        $this->assertSame('double',   Column::TYPE_DOUBLE);
        $this->assertSame('numeric',  Column::TYPE_NUMERIC);
        $this->assertSame('string',   Column::TYPE_STRING);
        $this->assertSame('bool',     Column::TYPE_BOOL);
        $this->assertSame('date',     Column::TYPE_DATE);
        $this->assertSame('time',     Column::TYPE_TIME);
        $this->assertSame('datetime', Column::TYPE_DATETIME);
        $this->assertSame('blob',     Column::TYPE_BLOB);
    }

    public function testActionConstants(): void {
        $this->assertSame('cascade',  Column::ACTION_CASCADE);
        $this->assertSame('set_null', Column::ACTION_SET_NULL);
    }

    public function testNowConstant(): void {
        $this->assertSame('now', Column::NOW);
    }

    public function testConstructorDefaults(): void {
        $col = new Column(type: Column::TYPE_STRING);
        $this->assertSame(Column::TYPE_STRING, $col->type);
        $this->assertSame(0, $col->size);
        $this->assertFalse($col->fixSize);
        $this->assertFalse($col->notNull);
        $this->assertFalse($col->autoIncrement);
        $this->assertFalse($col->primaryKey);
        $this->assertNull($col->default);
        $this->assertNull($col->foreignKey);
        $this->assertNull($col->onDelete);
        $this->assertNull($col->onUpdate);
    }

    public function testConstructorStoresAllArgs(): void {
        $col = new Column(
            type: Column::TYPE_INT,
            size: 11,
            fixSize: true,
            notNull: true,
            autoIncrement: true,
            primaryKey: true,
            default: 0,
            foreignKey: ['SomeClass', 'id'],
            onDelete: Column::ACTION_CASCADE,
            onUpdate: Column::ACTION_SET_NULL,
        );
        $this->assertSame(Column::TYPE_INT, $col->type);
        $this->assertSame(11, $col->size);
        $this->assertTrue($col->fixSize);
        $this->assertTrue($col->notNull);
        $this->assertTrue($col->autoIncrement);
        $this->assertTrue($col->primaryKey);
        $this->assertSame(0, $col->default);
        $this->assertSame(['SomeClass', 'id'], $col->foreignKey);
        $this->assertSame(Column::ACTION_CASCADE, $col->onDelete);
        $this->assertSame(Column::ACTION_SET_NULL, $col->onUpdate);
    }
}
