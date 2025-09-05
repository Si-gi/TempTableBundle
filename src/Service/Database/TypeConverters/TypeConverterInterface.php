<?php
declare(strict_types=1);

namespace Sigi\TempTableBundle\Service\Database\TypeConverters;

interface TypeConverterInterface
{
    public function convert(mixed $value, string $columnType): mixed;
    public function getPdoType(string $columnType): int;
}