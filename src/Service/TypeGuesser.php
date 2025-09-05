<?php
declare(strict_types=1);

namespace Sigi\TempTableBundle\Service;

class TypeGuesser
{
    public const BIGINT = 'BIGINT';
    public const INTEGER = 'INTEGER';
    public const DECIMAL = 'DECIMAL';
    public const NUMERIC = 'NUMERIC';
    public const VARCHAR = 'VARCHAR(255)';
    public const TEXT = 'TEXT';
    // const TIMESTAMP = 'TIMESTAMP';

    private const INT_MAX = 2147483647;
    private const INT_MIN = -2147483648;
    private const BIGINT_MAX = '9223372036854775807';
    private const BIGINT_MIN = '-9223372036854775808';

    public function guessTypes(array $values): string
    {
        $cleanValues = array_filter($values, static fn ($v) => null !== $v && '' !== trim((string) $v));

        if (empty($cleanValues)) {
            return self::TEXT;
        }

        // Collecter tous les types individuels
        $individualTypes = [];
        $maxLength = 0;

        foreach ($cleanValues as $value) {
            $value = trim((string) $value);
            $maxLength = max($maxLength, \strlen($value));
            $individualTypes[] = $this->guessType($value);
        }

        // Déterminer le type le plus englobant
        return $this->determineOverallType($individualTypes, $maxLength);
    }

    public function guessType(mixed $value): string
    {
        if (empty($value)) {
            return self::TEXT;
        }

        $value = trim((string) $value);
        $maxLength = \strlen($value);

        $numericType = $this->getNumericType($value);
        if (null !== $numericType) {
            return $numericType;
        }

        return $this->getStringType($maxLength);
    }

    private function getNumericType(string $value): ?string
    {
        if (!is_numeric($value)) {
            return null;
        }

        // Vérifier si c'est un nombre décimal
        if (str_contains($value, '.')) {
            return self::DECIMAL;
        }

        // C'est un entier, déterminer INTEGER ou BIGINT
        return $this->getIntegerType($value);
    }

    /**
     * Détermine le type d'entier approprié (INTEGER ou BIGINT)
     * Utilise bccomp pour éviter les problèmes d'overflow de PHP
     */
    private function getIntegerType(string $value): string
    {
        // Utiliser bccomp pour comparer de gros nombres sous forme de chaînes
        if (\function_exists('bccomp')) {
            // Vérifier si la valeur dépasse les limites d'un INTEGER
            if (bccomp($value, (string) self::INT_MAX) > 0 || bccomp($value, (string) self::INT_MIN) < 0) {
                // Vérifier si c'est dans les limites d'un BIGINT
                if (bccomp($value, self::BIGINT_MAX) <= 0 && bccomp($value, self::BIGINT_MIN) >= 0) {
                    return self::BIGINT;
                }

                // Trop grand même pour BIGINT
                return self::NUMERIC;
            }

            return self::INTEGER;
        }

        // If bcmath not available
        return $this->getIntegerTypeFallback($value);
    }

    /**
     * Fallback method to determin numerical type
     */
    private function getIntegerTypeFallback(string $value): string
    {
        // - is used for negativs values
        $absValue = ltrim($value, '-');

        // bigint have 10 digits
        if (\strlen($absValue) > 10) {
            return self::BIGINT;
        }

        if (10 === \strlen($absValue)) {
            $intVal = (int) $value;
            if ($intVal > self::INT_MAX || $intVal < self::INT_MIN) {
                return self::BIGINT;
            }
        }

        if (false !== filter_var($value, \FILTER_VALIDATE_INT)) {
            return self::INTEGER;
        }

        return self::NUMERIC; // Return NUMERIC if uncertain
    }

    private function getStringType(int $maxLength): string
    {
        return $maxLength <= 255 ? self::VARCHAR : self::TEXT;
    }

    /**
     * Determine the global type based on individual types
     * Follows a hierarchy : TEXT > VARCHAR > NUMERIC > DECIMAL > BIGINT > INTEGER
     */
    private function determineOverallType(array $individualTypes, int $maxLength): string
    {
        $uniqueTypes = array_unique($individualTypes);
        if (1 === \count($uniqueTypes)) {
            return $uniqueTypes[0];
        }

        if (\in_array(self::TEXT, $uniqueTypes, true)
         || (\in_array(self::VARCHAR, $uniqueTypes, true) && \in_array(self::BIGINT, $uniqueTypes, true))
        || (\in_array(self::VARCHAR, $uniqueTypes, true) && \in_array(self::INTEGER, $uniqueTypes, true))
        || (\in_array(self::VARCHAR, $uniqueTypes, true) && \in_array(self::NUMERIC, $uniqueTypes, true))
        || (\in_array(self::VARCHAR, $uniqueTypes, true) && \in_array(self::DECIMAL, $uniqueTypes, true))
        ) {
            return self::TEXT;
        }

        if (\in_array(self::VARCHAR, $uniqueTypes, true)) {
            return $maxLength <= 255 ? self::VARCHAR : self::TEXT;
        }

        if (\in_array(self::NUMERIC, $uniqueTypes, true)
            || (\in_array(self::DECIMAL, $uniqueTypes, true) && \in_array(self::BIGINT, $uniqueTypes, true))
             || (\in_array(self::DECIMAL, $uniqueTypes, true) && \in_array(self::INTEGER, $uniqueTypes, true))
        ) {
            return self::NUMERIC;
        }

        if (\in_array(self::DECIMAL, $uniqueTypes, true)) {
            return self::DECIMAL;
        }

        if (\in_array(self::BIGINT, $uniqueTypes, true)) {
            return self::BIGINT;
        }

        return self::INTEGER;
    }

    public function requiresBigInt(string $value): bool
    {
        if (!is_numeric($value) || str_contains($value, '.')) {
            return false;
        }

        return self::BIGINT === $this->getIntegerType($value);
    }
}
