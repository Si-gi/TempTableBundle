<?php

declare(strict_types=1);

namespace Sigi\Tests\TempTableBundle\Service;

use org\bovigo\vfs\vfsStream;

use PHPUnit\Framework\TestCase;
use org\bovigo\vfs\vfsStreamDirectory;
use Sigi\TempTableBundle\Service\TypeGuesser;
use PHPUnit\Framework\Attributes\DataProvider;
use Sigi\TempTableBundle\Service\Structures\Table;
use Sigi\TempTableBundle\Service\Structures\Column;
use Sigi\TempTableBundle\Service\Structures\TableFactory;
use Sigi\TempTableBundle\Service\Structures\DefineTableStructure;

class TableFactoryTest extends TestCase
{
    #[DataProvider("fileContentProvider")]
    public function testAnalyzeStructure(string $fileContent, string $expectedType): void
    {
        $table = new Table("test", "tst");
        $column = new Column("name", TypeGuesser::VARCHAR);

        $root = vfsStream::setup('root', null, [
            'file.csv' => "id;name\n1;Alice",
        ]);
        $file = $root->getChild('file.csv');
        $defineStructure = new TableFactory(100, new TypeGuesser());
        $analyse = $defineStructure->analyzeStructure($file->url());

        $this->assertEquals($column->getType(), $analyse->getColumnByIndex(0)->getType());


    }

    public static function fileContentProvider(): iterable
    {
        yield [
            "Identifiant PP\n10003369898",  TypeGuesser::BIGINT
        ];
        yield [
            "Prénom d'exercice\nSTEPHANE",  TypeGuesser::VARCHAR
        ];
        yield [
            "TELEPHONE\n04 91 98 31 51", TypeGuesser::VARCHAR
        ];
    }
}