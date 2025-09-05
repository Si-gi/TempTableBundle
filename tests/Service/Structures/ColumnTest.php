<?php

use PHPUnit\Framework\TestCase;
use Sigi\TempTableBundle\Service\Structures\Column;


class ColumnTest extends TestCase
{
    public function testColumnNameSanitization()
    {
        $column = new Column('Some-Column Name!');
        $this->assertEquals('some_column_name_', $column->getName());
    }

    public function testAddAndRetrieveValues()
    {
        $column = new Column('foo');
        $column->addValue(123);
        $column->addValue('bar');
        $column->addValue(true);
        $this->assertSame([123, 'bar', true], $column->getValues());
    }

    public function testSettersAndGetters()
    {
        $column = new Column('testType', null);
        $column->setType('integer');
        $this->assertEquals('integer', $column->getType());

        $column->setIndex(7);
        $this->assertEquals(7, $column->getIndex());
    }

    public function testColumnNameStartingWithDigit()
    {
        $column = new Column('3name');
        $this->assertEquals('col_3name', $column->getName());
    }

    public function testAddArrayValueSerializesToJson()
    {
        $column = new Column('jsonCol');
        $arrayValue = ['a' => 1, 'b' => 2];
        $column->addValue($arrayValue);

        $this->assertCount(1, $column->getValues());
        $this->assertJson($column->getValues()[0]);
        $this->assertEquals(json_encode($arrayValue), $column->getValues()[0]);
    }

    public function testGetValuesWhenEmpty()
    {
        $column = new Column('emptyCol');
        $this->assertSame([], $column->getValues());
    }
}