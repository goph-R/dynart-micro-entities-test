<?php

namespace Dynart\Micro\Entities\Test\Unit;

use Dynart\Micro\Entities\Query;
use PHPUnit\Framework\TestCase;

class QueryTest extends TestCase {

    public function testFromString(): void {
        $q = new Query('MyClass');
        $this->assertSame('MyClass', $q->from());
    }

    public function testFromSubquery(): void {
        $sub = new Query('MyClass');
        $q = new Query($sub);
        $this->assertSame($sub, $q->from());
    }

    public function testFieldsDefaultEmpty(): void {
        $q = new Query('MyClass');
        $this->assertSame([], $q->fields());
    }

    public function testAddFieldsMerges(): void {
        $q = new Query('MyClass');
        $q->addFields(['id', 'name']);
        $q->addFields(['email']);
        $this->assertSame(['id', 'name', 'email'], $q->fields());
    }

    public function testSetFieldsReplaces(): void {
        $q = new Query('MyClass');
        $q->addFields(['id', 'name']);
        $q->setFields(['email']);
        $this->assertSame(['email'], $q->fields());
    }

    public function testVariablesDefaultEmpty(): void {
        $q = new Query('MyClass');
        $this->assertSame([], $q->variables());
    }

    public function testAddVariablesMerges(): void {
        $q = new Query('MyClass');
        $q->addVariables([':a' => 1]);
        $q->addVariables([':b' => 2]);
        $this->assertSame([':a' => 1, ':b' => 2], $q->variables());
    }

    public function testAddConditionAppendsAndMergesVariables(): void {
        $q = new Query('MyClass');
        $q->addCondition('id = :id', [':id' => 1]);
        $this->assertSame(['id = :id'], $q->conditions());
        $this->assertSame([':id' => 1], $q->variables());
    }

    public function testAddConditionMultiple(): void {
        $q = new Query('MyClass');
        $q->addCondition('a = :a');
        $q->addCondition('b = :b');
        $this->assertSame(['a = :a', 'b = :b'], $q->conditions());
    }

    public function testAddInnerJoin(): void {
        $q = new Query('MyClass');
        $q->addInnerJoin('OtherClass', 'a.id = b.id');
        $this->assertSame([[Query::INNER_JOIN, 'OtherClass', 'a.id = b.id']], $q->joins());
    }

    public function testAddJoin(): void {
        $q = new Query('MyClass');
        $q->addJoin(Query::LEFT_JOIN, 'OtherClass', 'a.id = b.id');
        $this->assertSame([[Query::LEFT_JOIN, 'OtherClass', 'a.id = b.id']], $q->joins());
    }

    public function testAddGroupBy(): void {
        $q = new Query('MyClass');
        $q->addGroupBy('category');
        $q->addGroupBy('status');
        $this->assertSame(['category', 'status'], $q->groupBy());
    }

    public function testAddOrderByDefaultDir(): void {
        $q = new Query('MyClass');
        $q->addOrderBy('name');
        $this->assertSame([['name', 'asc']], $q->orderBy());
    }

    public function testAddOrderByDesc(): void {
        $q = new Query('MyClass');
        $q->addOrderBy('name', 'desc');
        $this->assertSame([['name', 'desc']], $q->orderBy());
    }

    public function testDefaultOffsetAndMax(): void {
        $q = new Query('MyClass');
        $this->assertSame(-1, $q->offset());
        $this->assertSame(-1, $q->max());
    }

    public function testSetLimit(): void {
        $q = new Query('MyClass');
        $q->setLimit(10, 25);
        $this->assertSame(10, $q->offset());
        $this->assertSame(25, $q->max());
    }

    public function testJoinTypeConstants(): void {
        $this->assertSame('inner',      Query::INNER_JOIN);
        $this->assertSame('left',       Query::LEFT_JOIN);
        $this->assertSame('right',      Query::RIGHT_JOIN);
        $this->assertSame('full outer', Query::OUTER_JOIN);
    }
}
