<?php

declare(strict_types=1);

namespace Kunlare\PhpCrudApi\Auth;

use Kunlare\PhpCrudApi\Api\Request;

/**
 * Interface for authentication strategies.
 */
interface AuthInterface
{
    /**
     * Authenticate a request and return user data.
     *
     * @param Request $request The HTTP request
     * @return array<string, mixed>|null User data or null if authentication fails
     */
    public function authenticate(Request $request): ?array;

    /**
     * Validate credentials.
     *
     * @param string $credentials The credentials to validate
     * @return bool True if valid
     */
    public function validate(string $credentials): bool;
}
