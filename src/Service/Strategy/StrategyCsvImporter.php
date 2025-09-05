<?php
declare(strict_types=1);

namespace Sigi\TempTableBundle\Service\Strategy;

use Psr\Log\LoggerInterface;
use Sigi\TempTableBundle\Service\Database\DatabaseConnectionInterface;
use Sigi\TempTableBundle\Service\Structures\Table;

class StrategyCsvImporter implements CsvImporterInterface
{
    /** @var CsvImportStrategyInterface[] */
    private array $strategies;

    public function __construct(
        private DatabaseConnectionInterface $connection,
        private LoggerInterface $logger,
        CsvImportStrategyInterface ...$strategies
    ) {
        $this->strategies = $strategies;
        // filter by priority
        usort($this->strategies, static fn ($a, $b) => $a->getPriority() <=> $b->getPriority());
    }

    public function import(string $csvFilePath, Table $table, string $delimiter): void
    {
        $this->connection->beginTransaction();

        try {
            $lastException = null;

            foreach ($this->strategies as $strategy) {
                if (!$strategy->canHandle($this->connection)) {
                    continue;
                }

                try {
                    $strategy->import($csvFilePath, $table, $delimiter);
                    $this->connection->commit();

                    return;
                } catch (\Exception $e) {
                    $this->logger->warning(\sprintf(
                        'Strategy %s failed: %s',
                        $strategy::class,
                        $e->getMessage()
                    ));
                    $lastException = $e;
                }
            }

            throw new \RuntimeException('All import strategies failed', 0, $lastException);
        } catch (\Exception $e) {
            $this->connection->rollBack();

            throw $e;
        }
    }
}
