<?php

declare(strict_types=1);

namespace Sigi\TempTableBundle\Service\Types;

use Symfony\Component\DependencyInjection\Attribute\AsTaggedItem;

#[AsTaggedItem('sigi_type_guesser')]
interface TypeGuesserInterface
{
    public const BIGINT = 'BIGINT';
    public const INTEGER = 'INTEGER';
    public const DECIMAL = 'DECIMAL';
    public const NUMERIC = 'NUMERIC';
    public const VARCHAR = 'VARCHAR(255)';
    public const TEXT = 'TEXT';

    public function guessType(mixed $value): ?string;
}
