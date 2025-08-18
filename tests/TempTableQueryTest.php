<?php

declare(strict_types=1);

namespace Sigi\Tests\TempTableBundle;

use Doctrine\DBAL\Connection;
use Iterator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Sigi\TempTableBundle\Service\TempTableQuery;

class TempTableQueryTest extends TestCase
{
    use ProphecyTrait;

    public function testQuery(): void
    {
        // $connection = $this->prophesize(Connection::class);
        // $connection->fetchAllAssociative()->shouldBeCalled();
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())
        ->method("fetchAllAssociative")
        ->willReturn([]);
        $tempTableQuery = new TempTableQuery($connection);
        $tempTableQuery->query("table");

    }

    #[DataProvider("buildQueryProvider")]
    public function testBuildSelectQuery(string $table, array $conditions, string $expectedSql): void
    {
        $connection = $this->createMock(Connection::class);
        $tempTableQuery = new TempTableQuery($connection);
        $got = $tempTableQuery->buildSelectQuery("table", $conditions);
        $this->assertEquals($expectedSql, $got[0]);
    }

    public static function buildQueryProvider(): Iterator
    {
        yield [
            'table', 
            [],
            'SELECT * FROM "table"'
        ];
        yield [
            "table", 
            ["name" => "john", "lastname" => "does"], 
            'SELECT * FROM "table" WHERE "name" = :name AND "lastname" = :lastname'
        ];
    }
}