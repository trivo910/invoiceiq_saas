<?php

namespace App\Services\Import;

class ImportProcessingException extends \RuntimeException
{
    public function __construct(string $message, public readonly int $status = 409)
    {
        parent::__construct($message);
    }
}
