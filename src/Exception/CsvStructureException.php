<?php
declare(strict_types=1);

namespace Sigi\TempTableBundle\Exception;

class CsvStructureException extends \RuntimeException
{
    private mixed $data;

    public const FOPEN_ERROR = 'Error while openin the csv File';
    public const FGETCSV_ERROR = 'Could not get CSV data';

    public function __construct(string $message = '', int $code = 0, mixed $data = null, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
        $this->data = $data;
    }

    public function getData(): mixed
    {
        return $this->data;
    }
}
