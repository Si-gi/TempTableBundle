<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Doctrine\Common\Collections\ArrayCollection;
use Sigi\TempTableBundle\Service\Structures\Table;
use Sigi\TempTableBundle\Service\Structures\Column;

class TableTest extends TestCase
{
    public function testAddColumnSetsIndex()
    {
        $table = new Table('my_name', 'my_prefix_');
        $column = new Column('col1');
        $table->addColumn($column);

        $this->assertCount(1, $table->getColumns());
        $this->assertSame($column, $table->getColumns()->first());
        $this->assertEquals(0, $column->getIndex());
    }

    public function testGetColumnsByNameReturnsColumn()
    {
        $table = new Table('testname', 'prefx_');
        $columnA = new Column('aaa');
        $columnB = new Column('bbb');
        $table->addColumn($columnA);
        $table->addColumn($columnB);

        $found = $table->getColumnsByName('bbb');
        $this->assertSame($columnB, $found);
    }

    public function testGetFullNameReturnsConcatenatedValue()
    {
        $table = new Table('customers', 'crm_');
        $this->assertEquals('crm_customers', $table->getFullName());
    }

    public function testAddColumnDoesNotAddDuplicate()
    {
        $table = new Table('tab', 'pre_');
        $column = new Column('c');
        $table->addColumn($column);
        $table->addColumn($column);

        $this->assertCount(1, $table->getColumns());
    }

    public function testGetColumnsByNameReturnsNullIfNotFound()
    {
        $table = new Table('team', 'prefix_');
        $column = new Column('colX');
        $table->addColumn($column);

        $result = $table->getColumnsByName('does_not_exist');
        $this->assertNull($result);

        $table->removeColumn($column);
        $this->assertFalse($table->getColumns()->contains($column));

    }

    public function testGetColumnByIndexReturnsNullForInvalidIndex()
    {
        $table = new Table('table', 'pf_');
        $this->assertNull($table->getColumnByIndex(0));
        $table->addColumn(new Column('a'));
        $this->assertNull($table->getColumnByIndex(5));
        $this->assertNull($table->getColumnByIndex(-1));
    }

    public function testPRefixAndName(): void
    {
        $name = "table";
        $prefix = "pf_";
        $table = new Table($name, $prefix);
        $this->assertEquals($name, $table->getName());
        $this->assertEquals($prefix, $table->getprefix());

    }
}