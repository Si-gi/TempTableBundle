<?php
declare(strict_types=1);

namespace Sigi\TempTableBundle\Service\Structures;

class TempTableConfig
{
    public function __construct(
        public readonly string $tablePrefix,
        public readonly int $retentionHours,
        public readonly int $batchSize = 1000,
        public readonly int $connectionTimeout = 20
    ) {
    }
}
