<?php
declare(strict_types=1);

namespace Sigi\TempTableBundle\Service\Database\TypeConverters;

class BigIntTypeConverter implements SingleTypeConverterInterface
{
    public function convert(mixed $value): mixed
    {
        if (null === $value || '' === $value) {
            return null;
        }

        return is_numeric($value) ? (string) $value : null;
    }
}