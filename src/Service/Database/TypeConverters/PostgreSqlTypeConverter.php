<?php
declare(strict_types=1);

namespace Sigi\TempTableBundle\Service\Database\TypeConverters;

use Sigi\TempTableBundle\Service\TypeGuesser;

class PostgreSqlTypeConverter implements TypeConverterInterface
{
    private array $converters;

    public function __construct()
    {
        $this->converters = [
            TypeGuesser::INTEGER => new IntegerTypeConverter(),
            TypeGuesser::BIGINT => new BigIntTypeConverter(),
            TypeGuesser::DECIMAL => new DecimalTypeConverter(),
            TypeGuesser::NUMERIC => new DecimalTypeConverter(),
        ];
    }

    public function convert(mixed $value, string $columnType): mixed
    {
        $converter = $this->converters[strtoupper($columnType)] ?? new StringTypeConverter();
        return $converter->convert($value);
    }

    public function getPdoType(string $columnType): int
    {
        return match (strtoupper($columnType)) {
            TypeGuesser::INTEGER => \PDO::PARAM_INT,
            TypeGuesser::BIGINT, TypeGuesser::DECIMAL, TypeGuesser::NUMERIC => \PDO::PARAM_STR,
            default => \PDO::PARAM_STR,
        };
    }
}