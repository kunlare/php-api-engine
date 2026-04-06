<?php

declare(strict_types=1);

namespace Kunlare\PhpApiEngine\Config;

use Dotenv\Dotenv;
use RuntimeException;

/**
 * Configuration manager that loads settings from .env files.
 */
class Config
{
    /** @var array<string, string> Loaded configuration values */
    private array $values = [];

    /**
     * Create a new Config instance.
     *
     * @param string|null $envPath Directory containing .env file
     * @param string $envFile Name of the .env file
     */
    public function __construct(?string $envPath = null, string $envFile = '.env')
    {
        if ($envPath !== null) {
            $this->load($envPath, $envFile);
        }
    }

    /**
     * Load configuration from a .env file.
     *
     * @param string $path Directory containing .env file
     * @param string $file Name of the .env file
     */
    public function load(string $path, string $file = '.env'): void
    {
        if (!is_dir($path)) {
            throw new RuntimeException("Configuration directory not found: {$path}");
        }

        $dotenv = Dotenv::createImmutable($path, $file);
        $dotenv->safeLoad();

        $this->values = $_ENV;
    }

    /**
     * Get a configuration value.
     *
     * @param string $key Configuration key
     * @param mixed $default Default value if key not found
     * @return mixed Configuration value
     */
    public function get(string $key, mixed $default = null): mixed
    {
        $value = $this->values[$key] ?? $_ENV[$key] ?? getenv($key);

        if ($value === false) {
            return $default;
        }

        return $this->castValue((string) $value);
    }

    /**
     * Check if a configuration key exists.
     *
     * @param string $key Configuration key
     */
    public function has(string $key): bool
    {
        return isset($this->values[$key]) || isset($_ENV[$key]) || getenv($key) !== false;
    }

    /**
     * Get all configuration values.
     *
     * @return array<string, mixed>
     */
    public function all(): array
    {
        return $this->values;
    }

    /**
     * Get a configuration value as string.
     *
     * @param string $key Configuration key
     * @param string $default Default value
     */
    public function getString(string $key, string $default = ''): string
    {
        return (string) $this->get($key, $default);
    }

    /**
     * Get a configuration value as integer.
     *
     * @param string $key Configuration key
     * @param int $default Default value
     */
    public function getInt(string $key, int $default = 0): int
    {
        return (int) $this->get($key, $default);
    }

    /**
     * Get a configuration value as boolean.
     *
     * @param string $key Configuration key
     * @param bool $default Default value
     */
    public function getBool(string $key, bool $default = false): bool
    {
        $value = $this->get($key, $default);

        if (is_bool($value)) {
            return $value;
        }

        if (is_string($value)) {
            return in_array(strtolower($value), ['true', '1', 'yes', 'on'], true);
        }

        return (bool) $value;
    }

    /**
     * Get a configuration value as array (comma-separated).
     *
     * @param string $key Configuration key
     * @param array<string> $default Default value
     * @return array<string>
     */
    public function getArray(string $key, array $default = []): array
    {
        $value = $this->get($key);

        if ($value === null) {
            return $default;
        }

        if (is_array($value)) {
            return $value;
        }

        return array_map('trim', explode(',', (string) $value));
    }

    /**
     * Validate that required configuration keys are present.
     *
     * @param array<string> $required List of required keys
     * @throws RuntimeException If a required key is missing
     */
    public function validate(array $required): void
    {
        foreach ($required as $key) {
            if (!$this->has($key)) {
                throw new RuntimeException("Missing required configuration key: {$key}");
            }
        }
    }

    /**
     * Cast string value to appropriate PHP type.
     *
     * @param string $value Raw string value
     * @return mixed Cast value
     */
    private function castValue(string $value): mixed
    {
        $lower = strtolower($value);

        if ($lower === 'true') {
            return true;
        }
        if ($lower === 'false') {
            return false;
        }
        if ($lower === 'null') {
            return null;
        }
        if (is_numeric($value) && !str_contains($value, '.')) {
            return (int) $value;
        }
        if (is_numeric($value) && str_contains($value, '.')) {
            return (float) $value;
        }

        return $value;
    }
}
