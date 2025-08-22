<?php
declare(strict_types=1);

namespace Sigi\TempTableBundle\Service\Types;

class VarcharTypeGuesser implements TypeGuesserInterface
{
    public function guessType(mixed $value): ?string
    {
        return self::VARCHAR;
    }
}
