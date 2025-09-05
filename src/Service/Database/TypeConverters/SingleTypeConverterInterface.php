<?php
declare(strict_types=1);

namespace Sigi\TempTableBundle\Service\Database\TypeConverters;

interface SingleTypeConverterInterface
{
    public function convert(mixed $value): mixed;
}
