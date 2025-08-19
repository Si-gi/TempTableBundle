<?php

declare(strict_types=1);

namespace Sigi\Tests\TempTableBundle;


use Doctrine\DBAL\Result;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Doctrine\DBAL\Query\QueryBuilder;
use PHPUnit\Framework\MockObject\MockObject;
use Sigi\TempTableBundle\Service\TempTableQuery;

class TempTableQueryTest extends TestCase
{
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
    }

    public function testQueryWithoutConditions(): void
    {
        $expectedData = [['id' => 1, 'name' => 'test']];
        
        // Configuration du mock QueryBuilder
        $this->queryBuilderMock
            ->expects($this->once())
            ->method('select')
            ->with('*')
            ->willReturnSelf();

        $this->queryBuilderMock
            ->expects($this->once())
            ->method('executeQuery')
            ->willReturn($this->resultMock);

        $this->resultMock
            ->expects($this->once())
            ->method('fetchAllAssociative')
            ->willReturn($expectedData);

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

        $result = $this->tempTableQuery->query('test_table', [], 1, 1);
        $this->assertEquals($expectedData, $result);
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

}