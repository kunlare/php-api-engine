<?php

declare(strict_types=1);

namespace Kunlare\PhpCrudApi\Exceptions;

use Exception;
use Throwable;

/**
 * Exception thrown for authentication/authorization errors.
 */
class AuthException extends Exception
{
    /** @var int HTTP status code */
    private int $statusCode;

    /**
     * @param string $message Error message
     * @param int $statusCode HTTP status code
     * @param int $code Exception code
     * @param Throwable|null $previous Previous exception
     */
    public function __construct(
        string $message = 'Authentication failed',
        int $statusCode = 401,
        int $code = 0,
        ?Throwable $previous = null
    ) {
        $this->statusCode = $statusCode;
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
     * Convert exception to array for JSON response.
     *
     * @return array{error: string, status_code: int, type: string}
     */
    public function toArray(): array
    {
        return [
            'error' => $this->getMessage(),
            'status_code' => $this->statusCode,
            'type' => 'AuthException',
        ];
    }
}
