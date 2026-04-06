<?php

declare(strict_types=1);

namespace Kunlare\PhpCrudApi\Crud;

use Kunlare\PhpCrudApi\Database\Connection;
use Kunlare\PhpCrudApi\Exceptions\ValidationException;

/**
 * Input validation for API requests.
 */
class Validator
{
    /** @var Connection|null Database connection for unique checks */
    private ?Connection $connection;

    /** @var array<string, string[]> Accumulated errors */
    private array $errors = [];

    /**
     * @param Connection|null $connection Database connection for unique checks
     */
    public function __construct(?Connection $connection = null)
    {
        $this->connection = $connection;
    }

    /**
     * Validate data against a set of rules.
     *
     * @param array<string, mixed> $data Data to validate
     * @param array<string, string|string[]> $rules Validation rules
     * @return array<string, string[]> Validation errors (empty if valid)
     * @throws ValidationException If validation fails
     *
     * Supported rules: required, email, min:n, max:n, numeric, integer, string,
     *                  in:val1,val2, unique:table,column, regex:pattern
     */
    public function validate(array $data, array $rules): array
    {
        $this->errors = [];

        foreach ($rules as $field => $fieldRules) {
            $ruleList = is_array($fieldRules) ? $fieldRules : explode('|', $fieldRules);
            $value = $data[$field] ?? null;

            foreach ($ruleList as $rule) {
                $this->applyRule($field, $value, $rule, $data);
            }
        }

        if (!empty($this->errors)) {
            throw new ValidationException('Validation failed', $this->errors);
        }

        return $this->errors;
    }

    /**
     * Validate an email address.
     *
     * @param string $email Email to validate
     * @return bool True if valid
     */
    public function validateEmail(string $email): bool
    {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }

    /**
     * Validate a password meets requirements.
     *
     * @param string $password Password to validate
     * @param int $minLength Minimum length
     * @param bool $requireSpecial Require special characters
     * @return bool True if valid
     */
    public function validatePassword(string $password, int $minLength = 8, bool $requireSpecial = true): bool
    {
        if (strlen($password) < $minLength) {
            return false;
        }

        if ($requireSpecial && !preg_match('/[^a-zA-Z0-9]/', $password)) {
            return false;
        }

        return true;
    }

    /**
     * Sanitize input data.
     *
     * @param mixed $input Input to sanitize
     * @return mixed Sanitized input
     */
    public function sanitize(mixed $input): mixed
    {
        if (is_string($input)) {
            return htmlspecialchars(trim($input), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }

        if (is_array($input)) {
            return array_map([$this, 'sanitize'], $input);
        }

        return $input;
    }

    /**
     * Apply a single validation rule.
     *
     * @param string $field Field name
     * @param mixed $value Field value
     * @param string $rule Rule string
     * @param array<string, mixed> $data Full data array
     */
    private function applyRule(string $field, mixed $value, string $rule, array $data): void
    {
        $parts = explode(':', $rule, 2);
        $ruleName = $parts[0];
        $ruleParam = $parts[1] ?? '';

        match ($ruleName) {
            'required' => $this->ruleRequired($field, $value),
            'email' => $value !== null && $value !== '' ? $this->ruleEmail($field, $value) : null,
            'min' => $value !== null && $value !== '' ? $this->ruleMin($field, $value, (int) $ruleParam) : null,
            'max' => $value !== null && $value !== '' ? $this->ruleMax($field, $value, (int) $ruleParam) : null,
            'numeric' => $value !== null && $value !== '' ? $this->ruleNumeric($field, $value) : null,
            'integer' => $value !== null && $value !== '' ? $this->ruleInteger($field, $value) : null,
            'string' => $value !== null && $value !== '' ? $this->ruleString($field, $value) : null,
            'in' => $value !== null && $value !== '' ? $this->ruleIn($field, $value, $ruleParam) : null,
            'unique' => $value !== null && $value !== '' ? $this->ruleUnique($field, $value, $ruleParam) : null,
            'regex' => $value !== null && $value !== '' ? $this->ruleRegex($field, $value, $ruleParam) : null,
            default => null,
        };
    }

    /**
     * Add a validation error.
     *
     * @param string $field Field name
     * @param string $message Error message
     */
    private function addError(string $field, string $message): void
    {
        $this->errors[$field][] = $message;
    }

    private function ruleRequired(string $field, mixed $value): void
    {
        if ($value === null || $value === '' || (is_array($value) && empty($value))) {
            $this->addError($field, "The {$field} field is required.");
        }
    }

    private function ruleEmail(string $field, mixed $value): void
    {
        if (!$this->validateEmail((string) $value)) {
            $this->addError($field, "The {$field} field must be a valid email address.");
        }
    }

    private function ruleMin(string $field, mixed $value, int $min): void
    {
        if (is_string($value) && strlen($value) < $min) {
            $this->addError($field, "The {$field} field must be at least {$min} characters.");
        } elseif (is_numeric($value) && (float) $value < $min) {
            $this->addError($field, "The {$field} field must be at least {$min}.");
        }
    }

    private function ruleMax(string $field, mixed $value, int $max): void
    {
        if (is_string($value) && strlen($value) > $max) {
            $this->addError($field, "The {$field} field must not exceed {$max} characters.");
        } elseif (is_numeric($value) && (float) $value > $max) {
            $this->addError($field, "The {$field} field must not exceed {$max}.");
        }
    }

    private function ruleNumeric(string $field, mixed $value): void
    {
        if (!is_numeric($value)) {
            $this->addError($field, "The {$field} field must be numeric.");
        }
    }

    private function ruleInteger(string $field, mixed $value): void
    {
        if (!is_int($value) && !ctype_digit((string) $value)) {
            $this->addError($field, "The {$field} field must be an integer.");
        }
    }

    private function ruleString(string $field, mixed $value): void
    {
        if (!is_string($value)) {
            $this->addError($field, "The {$field} field must be a string.");
        }
    }

    private function ruleIn(string $field, mixed $value, string $param): void
    {
        $allowed = explode(',', $param);
        if (!in_array((string) $value, $allowed, true)) {
            $this->addError($field, "The {$field} field must be one of: {$param}.");
        }
    }

    private function ruleUnique(string $field, mixed $value, string $param): void
    {
        if ($this->connection === null) {
            return;
        }

        $parts = explode(',', $param);
        $table = $parts[0];
        $column = $parts[1] ?? $field;

        $count = $this->connection->fetchColumn(
            "SELECT COUNT(*) FROM `{$table}` WHERE `{$column}` = ?",
            [$value]
        );

        if ((int) $count > 0) {
            $this->addError($field, "The {$field} has already been taken.");
        }
    }

    private function ruleRegex(string $field, mixed $value, string $param): void
    {
        if (!preg_match($param, (string) $value)) {
            $this->addError($field, "The {$field} field format is invalid.");
        }
    }
}
