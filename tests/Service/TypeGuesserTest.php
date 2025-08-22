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

    public static function typeProvider(): iterable
    {
        yield ["1234567", TypeGuesser::INTEGER];
        yield ["12345789.00", TypeGuesser::DECIMAL];
        yield ["10100146421", TypeGuesser::BIGINT];
        yield ["int", TypeGuesser::VARCHAR];
        yield [str_repeat('t', 256), TypeGuesser::TEXT];

    }
    
}