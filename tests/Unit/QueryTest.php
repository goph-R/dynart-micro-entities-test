<?php

namespace Dynart\Micro\Entities\Test\Unit;

use Dynart\Micro\Entities\EntityManagerException;
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

    /**
     * Writing `(a = :id) or (b = :id)` as two conditions is normal, so rebinding the same name
     * to the same value has to keep working.
     */
    public function testAddVariablesAllowsTheSameNameWithTheSameValue(): void {
        $q = new Query('MyClass');
        $q->addCondition('a = :id', [':id' => 1]);
        $q->addCondition('b = :id', [':id' => 1]);
        $this->assertSame([':id' => 1], $q->variables());
        $this->assertSame(['a = :id', 'b = :id'], $q->conditions());
    }

    /**
     * The SQL keeps both occurrences of the placeholder, so a silent overwrite would make the
     * first condition filter on the second one's value.
     */
    public function testAddVariablesRefusesTheSameNameWithADifferentValue(): void {
        $q = new Query('MyClass');
        $q->addCondition('a = :id', [':id' => 1]);
        $this->expectException(EntityManagerException::class);
        $q->addCondition('b = :id', [':id' => 2]);
    }

    public function testAddVariablesDistinguishesTypes(): void {
        $q = new Query('MyClass');
        $q->addVariables([':id' => 1]);
        $this->expectException(EntityManagerException::class);
        $q->addVariables([':id' => '1']);
    }

    public function testNextParamNameStartsAtZero(): void {
        $q = new Query('MyClass');
        $this->assertSame(':author_0', $q->nextParamName('author'));
    }

    public function testNextParamNameSkipsUsedNames(): void {
        $q = new Query('MyClass');
        $q->addVariables([':author_0' => 1, ':author_1' => 2]);
        $this->assertSame(':author_2', $q->nextParamName('author'));
    }

    public function testNextParamNameAcceptsALeadingColon(): void {
        $q = new Query('MyClass');
        $this->assertSame(':author_0', $q->nextParamName(':author'));
    }

    public function testNextParamNameIsNotConfusedByASimilarName(): void {
        $q = new Query('MyClass');
        $q->addVariables([':author' => 1]);
        $this->assertSame(':author_0', $q->nextParamName('author'));
    }

    public function testTwoContributorsCanBothBindWithoutColliding(): void {
        $q = new Query('MyClass');
        $first = $q->nextParamName('id');
        $q->addCondition("a = $first", [$first => 1]);
        $second = $q->nextParamName('id');
        $q->addCondition("b = $second", [$second => 2]);
        $this->assertSame([':id_0' => 1, ':id_1' => 2], $q->variables());
        $this->assertSame(['a = :id_0', 'b = :id_1'], $q->conditions());
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
