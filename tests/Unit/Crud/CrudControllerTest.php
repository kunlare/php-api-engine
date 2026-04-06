<?php

declare(strict_types=1);

namespace Kunlare\PhpApiEngine\Tests\Unit\Crud;

use Kunlare\PhpApiEngine\Exceptions\ValidationException;
use Kunlare\PhpApiEngine\Tests\TestCase;

class CrudControllerTest extends TestCase
{
    public function testValidationExceptionHasCorrectStatusCode(): void
    {
        $exception = new ValidationException('Validation failed', ['name' => ['Required']], 422);
        $this->assertEquals(422, $exception->getStatusCode());
        $this->assertEquals('Validation failed', $exception->getMessage());
        $this->assertEquals(['name' => ['Required']], $exception->getErrors());
    }

    public function testValidationExceptionToArray(): void
    {
        $errors = ['email' => ['Invalid email'], 'name' => ['Required']];
        $exception = new ValidationException('Validation failed', $errors);

        $array = $exception->toArray();

        $this->assertEquals('Validation failed', $array['error']);
        $this->assertEquals(422, $array['status_code']);
        $this->assertEquals('ValidationException', $array['type']);
        $this->assertEquals($errors, $array['errors']);
    }

    public function testInvalidTableNameRejected(): void
    {
        // Test that table names with special characters are rejected
        $this->expectException(ValidationException::class);

        // We test via reflection or by trying to use the validator directly
        $validator = new \Kunlare\PhpApiEngine\Crud\Validator();
        // The table name validation is done in the controller
        // Test the regex pattern directly
        $this->assertFalse((bool) preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', 'table; DROP TABLE users'));
        $this->assertTrue((bool) preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', 'valid_table'));
        $this->assertTrue((bool) preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', 'products'));
        $this->assertFalse((bool) preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', '123invalid'));
        $this->assertFalse((bool) preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', 'table-name'));

        // Trigger the exception
        $connection = $this->createMock(\Kunlare\PhpApiEngine\Database\Connection::class);
        $request = $this->createMock(\Kunlare\PhpApiEngine\Api\Request::class);

        $controller = new \Kunlare\PhpApiEngine\Crud\CrudController($connection, $request);
        $controller->list('invalid-table-name');
    }
}
