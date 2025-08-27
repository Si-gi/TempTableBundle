<?php
declare(strict_types=1);

namespace Sigi\TempTableBundle\Service\Database\TypeConverters;

class StringTypeConverter implements SingleTypeConverterInterface
{
    public function convert(mixed $value): mixed
    {
        return $value === null ? null : (string) $value;
    }
}
