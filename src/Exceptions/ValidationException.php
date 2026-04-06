<?php

declare(strict_types=1);

namespace Kunlare\PhpCrudApi\Exceptions;

use Exception;
use Throwable;

/**
 * Exception thrown for input validation errors.
 */
class ValidationException extends Exception
{
    /** @var int HTTP status code */
    private int $statusCode;

    /** @var array<string, string[]> Validation errors by field */
    private array $errors;

    /**
     * @param string $message Error message
     * @param array<string, string[]> $errors Validation errors by field
     * @param int $statusCode HTTP status code
     * @param int $code Exception code
     * @param Throwable|null $previous Previous exception
     */
    public function __construct(
        string $message = 'Validation failed',
        array $errors = [],
        int $statusCode = 422,
        int $code = 0,
        ?Throwable $previous = null
    ) {
        $this->statusCode = $statusCode;
        $this->errors = $errors;
        parent::__construct($message, $code, $previous);
    }

    /**
     * Get the HTTP status code.
     */
    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    /**
     * Get validation errors.
     *
     * @return array<string, string[]>
     */
    public function getErrors(): array
    {
        return $this->errors;
    }

    /**
     * Convert exception to array for JSON response.
     *
     * @return array{error: string, status_code: int, type: string, errors: array<string, string[]>}
     */
    public function toArray(): array
    {
        return [
            'error' => $this->getMessage(),
            'status_code' => $this->statusCode,
            'type' => 'ValidationException',
            'errors' => $this->errors,
        ];
    }
}
