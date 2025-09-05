<?php

namespace Sigi\Tests\TempTableBundle\Service;

use PHPUnit\Framework\TestCase;
use Sigi\TempTableBundle\Service\TypeGuesser;
use PHPUnit\Framework\Attributes\DataProvider;

class TypeGuesserTest extends TestCase
{
    #[DataProvider('typeProvider')]
    public function testGuessType(string $value, string $expected){
        $typeGuesser = new TypeGuesser();
        $guesserType = $typeGuesser->guessType($value);
        $this->assertEquals($expected, $guesserType);
    }

    #[DataProvider('valuesProviderForTypes')]
    public function testGuessTypes(array $values, string $expected): void
    {
        $typeGuesser = new TypeGuesser();
        $this->assertEquals($expected, $typeGuesser->guessTypes($values));
    }
    public function guessEmptyValues(): void
    {
        $typeGuesser = new TypeGuesser();
        $this->assertEquals(TypeGuesser::TEXT, $typeGuesser->guessTypes([]));
    }

    public static function typeProvider(): iterable
    {
        yield ["1234567", TypeGuesser::INTEGER];
        yield ["12345789.00", TypeGuesser::DECIMAL];
        yield ["10100146421", TypeGuesser::BIGINT];
        yield ["int", TypeGuesser::VARCHAR];
        yield [str_repeat('t', 256), TypeGuesser::TEXT];
        yield ['', TypeGuesser::TEXT];
    }


    public static function valuesProviderForTypes(): iterable
    {
        yield [
            [10100146421, 12345789.05 ], TypeGuesser::NUMERIC
        ];
        yield [
            [str_repeat('t', 255), "eeee"], TypeGuesser::VARCHAR
        ];
        yield [
            [15056, "text"], TypeGuesser::TEXT
        ];
        yield [
            [101001.1555, 12345789.0777775 ], TypeGuesser::DECIMAL
        ];
        yield [
            [], TypeGuesser::TEXT
        ];
        yield [
            [10100146421], TypeGuesser::BIGINT
        ];

    }
    
}