<?php

declare(strict_types=1);

namespace Kunlare\PhpApiEngine\Api;

/**
 * JSON response builder with standardized format.
 */
class Response
{
    /**
     * Send a JSON response.
     *
     * @param mixed $data Response data
     * @param int $statusCode HTTP status code
     * @param array<string, string> $headers Additional headers
     */
    public static function json(mixed $data, int $statusCode = 200, array $headers = []): void
    {
        http_response_code($statusCode);
        header('Content-Type: application/json; charset=utf-8');
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: DENY');

        foreach ($headers as $name => $value) {
            header("{$name}: {$value}");
        }

        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    }

    /**
     * Send a success response.
     *
     * @param mixed $data Response data
     * @param array<string, mixed> $meta Metadata (pagination, etc.)
     * @param int $statusCode HTTP status code
     */
    public static function success(mixed $data, array $meta = [], int $statusCode = 200): void
    {
        $response = [
            'success' => true,
            'data' => $data,
            'meta' => array_merge([
                'timestamp' => gmdate('Y-m-d\TH:i:s\Z'),
            ], $meta),
            'errors' => [],
        ];

        self::json($response, $statusCode);
    }

    /**
     * Send an error response.
     *
     * @param string $message Error message
     * @param int $statusCode HTTP status code
     * @param array<mixed> $errors Detailed errors
     */
    public static function error(string $message, int $statusCode = 400, array $errors = []): void
    {
        $response = [
            'success' => false,
            'data' => null,
            'meta' => [
                'timestamp' => gmdate('Y-m-d\TH:i:s\Z'),
            ],
            'errors' => array_merge([['message' => $message]], $errors),
        ];

        self::json($response, $statusCode);
    }

    /**
     * Send a 201 Created response.
     *
     * @param mixed $data Created resource data
     * @param string $location Location header URL
     */
    public static function created(mixed $data, string $location = ''): void
    {
        $headers = [];
        if ($location !== '') {
            $headers['Location'] = $location;
        }

        $response = [
            'success' => true,
            'data' => $data,
            'meta' => [
                'timestamp' => gmdate('Y-m-d\TH:i:s\Z'),
            ],
            'errors' => [],
        ];

        self::json($response, 201, $headers);
    }

    /**
     * Send a 204 No Content response.
     */
    public static function noContent(): void
    {
        http_response_code(204);
        header('Content-Type: application/json; charset=utf-8');
    }
}
