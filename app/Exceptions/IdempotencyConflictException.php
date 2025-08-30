<?php

namespace App\Exceptions;

use Exception;

class IdempotencyConflictException extends Exception
{
    protected $scope;
    protected $key;
    protected $existingResponse;

    public function __construct(
        string $message = '',
        string $scope = '',
        string $key = '',
        $existingResponse = null,
        int $code = 0,
        ?Throwable $previous = null
    ) {
        $defaultMessage = $message ?: "Idempotency key '{$scope}:{$key}' already exists with a different request.";
        parent::__construct($defaultMessage, $code, $previous);
        $this->scope = $scope;
        $this->key = $key;
        $this->existingResponse = $existingResponse;
    }

    public function getScope(): string
    {
        return $this->scope;
    }

    public function getKey(): string
    {
        return $this->key;
    }

    public function getExistingResponse()
    {
        return $this->existingResponse;
    }
}