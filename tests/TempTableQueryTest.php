<?php

declare(strict_types=1);

namespace Sigi\Tests\TempTableBundle;


use Doctrine\DBAL\Result;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Doctrine\DBAL\Query\QueryBuilder;
use PHPUnit\Framework\MockObject\MockObject;
use Prophecy\PhpUnit\ProphecyTrait;
use Sigi\TempTableBundle\Service\TempTableQuery;

class TempTableQueryTest extends TestCase
{

    use ProphecyTrait;
    private MockObject|Connection $connectionMock;
    private MockObject|Result $resultMock;
    private MockObject|QueryBuilder $queryBuilderMock;
    private TempTableQuery $tempTableQuery;
 
    protected function setUp(): void
    {
        $this->connectionMock = $this->createMock(Connection::class);
        $this->resultMock = $this->createMock(Result::class);
        $this->queryBuilderMock = $this->createMock(QueryBuilder::class);
        $this->tempTableQuery = new TempTableQuery($this->connectionMock);

        $this->queryBuilderMock
            ->method('from')
            ->willReturnSelf();
    }

    public function testQueryWithoutConditions(): void
    {
        $this->connectionMock->method('createQueryBuilder')->willReturn($this->queryBuilderMock);
        $this->connectionMock->method('quoteIdentifier')->willReturn('"test_table"');

        $this->queryBuilderMock
            ->expects($this->once())
            ->method('select')
            ->with('*')
            ->willReturnSelf();

        $this->queryBuilderMock
            ->expects($this->once())
            ->method('from')
            ->with('"test_table"')
            ->willReturnSelf();

        $this->queryBuilderMock->expects($this->once())->method('setMaxResults')->with(1)->willReturnSelf();
        $this->queryBuilderMock->expects($this->once())->method('setFirstResult')->with(1)->willReturnSelf();
        $this->tempTableQuery->query("test_table", [], 1 ,1);

    }

    public function testQueryWithCOlumnsSpecified(): void
    {
        $this->connectionMock->method('createQueryBuilder')->willReturn($this->queryBuilderMock);
        $this->connectionMock->method('quoteIdentifier')->willReturn('"test_table"');

        $this->queryBuilderMock
            ->expects($this->once())
            ->method('select')
            ->willReturnSelf();

        $this->queryBuilderMock
            ->expects($this->once())
            ->method('from')
            ->with('"test_table"')
            ->willReturnSelf();

        $this->queryBuilderMock->expects($this->once())->method('setMaxResults')->with(1)->willReturnSelf();
        $this->queryBuilderMock->expects($this->once())->method('setFirstResult')->with(1)->willReturnSelf();
        $this->tempTableQuery->query("test_table", ["column_1", "column_2"], 1 ,1);
    }

    public function testCreateQueryBuilderReturnsDoctrineQueryBuilder(): void
    {
        $this->connectionMock
            ->expects($this->once())
            ->method('createQueryBuilder')
            ->willReturn($this->queryBuilderMock);

        $this->connectionMock
            ->expects($this->once())
            ->method('quoteIdentifier')
            ->with('test_table')
            ->willReturn('"test_table"');

        $this->queryBuilderMock
            ->expects($this->once())
            ->method('from')
            ->with('"test_table"')
            ->willReturnSelf();

        $result = $this->tempTableQuery->createQueryBuilder('test_table');
        
        $this->assertSame($this->queryBuilderMock, $result);
    }

    public function testAddConditions(): void
    {
        $this->connectionMock
            ->method('quoteIdentifier')
            ->willReturnCallback(fn($column) => '"' . $column . '"');
        $this->connectionMock
            ->method('createQueryBuilder')
            ->willReturn($this->queryBuilderMock);
        $this->queryBuilderMock
            ->method('from')
            ->willReturnSelf();
        $conditions = ["name" => 'john', 'lastname' => 'doe'];

        $this->queryBuilderMock->expects($this->exactly(2))->method('andWhere')->willReturnSelf();
        $this->queryBuilderMock->expects($this->exactly(2))->method('setParameter')->willReturnSelf();
        $this->tempTableQuery->query('test_table');
        $this->tempTableQuery->addConditions($conditions);
    }

    public function testGetQb(): void
    {
         $this->connectionMock
            ->method('quoteIdentifier')
            ->willReturnCallback(fn($column) => '"' . $column . '"');
        $this->connectionMock
            ->method('createQueryBuilder')
            ->willReturn($this->queryBuilderMock);

        $this->tempTableQuery->query('test_table');

        $this->assertSame($this->queryBuilderMock, $this->tempTableQuery->getQb());
    }
}