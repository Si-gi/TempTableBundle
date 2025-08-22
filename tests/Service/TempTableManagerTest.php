<?php


declare(strict_types=1);

namespace Sigi\Tests\TempTableBundle\Service;

use Iterator;
use Doctrine\DBAL\Result;
use Psr\Log\LoggerInterface;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\Attributes\DataProvider;
use Sigi\TempTableBundle\Service\TempTableManager;
use Sigi\TempTableBundle\Exception\CsvStructureException;
use Symfony\Component\Filesystem\Exception\FileNotFoundException;
use Symfony\Component\HttpFoundation\File\Exception\AccessDeniedException;
use Symfony\Component\DependencyInjection\Loader\Configurator\Traits\PropertyTrait;
use org\bovigo\vfs\vfsStream;
use org\bovigo\vfs\vfsStreamDirectory;
use Sigi\TempTableBundle\Service\Structures\TableFactory;

class TempTableManagerTest extends TestCase{
    use PropertyTrait;
    private TempTableManager $tempTableManager;
    private MockObject|Connection $connectionMock;
    private MockObject|LoggerInterface $loggerMock;
    private MockObject|TableFactory $tableFactory;

    private vfsStreamDirectory $rootDir;
    protected function setUp(): void
    {
        $this->connectionMock = $this->createMock(Connection::class);
        $this->loggerMock = $this->createMock(LoggerInterface::class);
        $this->tableFactory = $this->createMock(TableFactory::class);
        $this->tempTableManager = new TempTableManager(
            $this->connectionMock, 
            $this->loggerMock,
            $this->tableFactory,
            tablePrefix: "temp_",
            retentionHours: 24
        );
    }
    protected function tearDown(): void{

    }
    // public function testAnalyzeCsvStructureFileNotFound(): void {
    //     $this->expectException(FileNotFoundException::class);
    //     $this->expectExceptionMessage('Csv File not found');
    //     $this->tempTableManager->analyzeStructure("/file/not/found.csv");
    // }

    // public function testAnalyzeCsvNotReadable(): void
    // {
    //     $root = vfsStream::setup('root', null, [
    //         'file.csv' => "id;name\n1;Alice",
    //     ]);

    //     // Récupère le fichier et enlève les droits
    //     $file = $root->getChild('file.csv');
    //     $file->chmod(0000);

    //     $this->expectException(AccessDeniedException::class);
    //     $this->expectExceptionMessage('Wrong Permission to read file');

    //     $this->tempTableManager->analyzeCsvStructure($file->url());
    // }

    // public function testAnalyzeFopenFailed(): void
    // {
    //     $root = vfsStream::setup('root');
    //     $dir = vfsStream::newDirectory('csvDir')->at($root);

    //     $this->expectException(CsvStructureException::class);
    //     $this->expectExceptionMessage(CsvStructureException::FOPEN_ERROR);

    //     // Ici file_exists = true et is_readable = true, mais fopen échouera
    //     $this->tempTableManager->analyzeCsvStructure($dir->url());
    // }

    // public function testAnalyzeFgetCSVFailed(): void
    // {
    //         $root = vfsStream::setup('root', null, [
    //         'file.csv' => "",
    //     ]);

    //     // Récupère le fichier et enlève les droits
    //     $file = $root->getChild('file.csv');

    //     $this->expectException(CsvStructureException::class);
    //     $this->expectExceptionMessage(CsvStructureException::FGETCSV_ERROR);

    //     $this->tempTableManager->analyzeCsvStructure($file->url());
    // }

    public function testInitializeRegistry(): void
    {
        $this->connectionMock->expects($this->once())->method('executeStatement');
        $this->tempTableManager->initializeRegistry();

    }
    
}