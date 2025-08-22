<?php

declare(strict_types=1);

namespace Sigi\TempTableBundle\Service\Types;

class NumericTypeGuesser implements TypeGuesserInterface
{
    public function guessType(mixed $value): ?string
    {
        if (!is_numeric($value)) {
            return null;
        }

        if (str_contains($value, '.')) {
            return self::DECIMAL;
        }
        if ((int) $value <= 9223372036854775807 && (int) $value > 2147483647) {
            return self::BIGINT;
        }
        if (filter_var($value, \FILTER_VALIDATE_INT)) {
            return self::INTEGER;
        }

        return self::NUMERIC;
    }
}
