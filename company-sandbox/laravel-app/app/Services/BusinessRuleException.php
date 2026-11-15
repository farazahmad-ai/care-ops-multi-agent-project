<?php

namespace App\Services;

use Exception;

class BusinessRuleException extends Exception
{
    protected string $errorCode;
    protected int $statusCode;
    protected array $details;

    public function __construct(string $errorCode, string $message, int $statusCode = 422, array $details = [])
    {
        parent::__construct($message);
        $this->errorCode = $errorCode;
        $this->statusCode = $statusCode;
        $this->details = $details;
    }

    public function getErrorCode(): string
    {
        return $this->errorCode;
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    public function getDetails(): array
    {
        return $this->details;
    }

    public function toResponseArray(): array
    {
        return [
            'error' => [
                'code' => $this->errorCode,
                'message' => $this->getMessage(),
                'details' => $this->details,
            ]
        ];
    }
}

