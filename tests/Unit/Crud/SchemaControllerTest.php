<?php

declare(strict_types=1);

namespace Kunlare\PhpApiEngine\Tests\Unit\Crud;

use Kunlare\PhpApiEngine\Api\Request;
use Kunlare\PhpApiEngine\Config\Config;
use Kunlare\PhpApiEngine\Crud\SchemaController;
use Kunlare\PhpApiEngine\Database\Connection;
use Kunlare\PhpApiEngine\Exceptions\AuthException;
use Kunlare\PhpApiEngine\Exceptions\NotFoundException;
use Kunlare\PhpApiEngine\Exceptions\ValidationException;
use Kunlare\PhpApiEngine\Tests\TestCase;

class SchemaControllerTest extends TestCase
{
    private Connection $connection;
    private Request $request;
    private Config $config;

    protected function setUp(): void
    {
        parent::setUp();
        $this->connection = $this->createMock(Connection::class);
        $this->request = $this->createMock(Request::class);
        $this->config = $this->createConfig();
    }

    private function createController(): SchemaController
    {
        return new SchemaController($this->connection, $this->request, $this->config);
    }

    public function testListTablesRequiresAuth(): void
    {
        $this->request->method('getUser')->willReturn(null);

        $this->expectException(AuthException::class);
        $this->expectExceptionMessage('Authentication required');

        $this->createController()->listTables();
    }

    public function testListTablesAllowsNonAdmin(): void
    {
        $this->request->method('getUser')->willReturn(['role' => 'user']);
        $this->connection->method('fetchAll')->willReturn([]);

        // Should not throw — non-admin users can list tables
        $this->expectOutputRegex('/"success":true/');
        $this->createController()->listTables();
    }

    public function testCreateTableRequiresAdmin(): void
    {
        $this->request->method('getUser')->willReturn(['role' => 'user']);

        $this->expectException(AuthException::class);

        $this->createController()->createTable();
    }

    public function testCreateTableRequiresTableField(): void
    {
        $this->request->method('getUser')->willReturn(['role' => 'admin']);
        $this->request->method('getJson')->willReturn([
            'columns' => ['id' => ['type' => 'INT']],
        ]);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('"table" field is required');

        $this->createController()->createTable();
    }

    public function testCreateTableRequiresColumnsField(): void
    {
        $this->request->method('getUser')->willReturn(['role' => 'admin']);
        $this->request->method('getJson')->willReturn([
            'table' => 'products',
        ]);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('"columns" field is required');

        $this->createController()->createTable();
    }

    public function testCreateTableRejectsColumnsAsNonArray(): void
    {
        $this->request->method('getUser')->willReturn(['role' => 'admin']);
        $this->request->method('getJson')->willReturn([
            'table' => 'products',
            'columns' => 'not-an-array',
        ]);

        $this->expectException(ValidationException::class);

        $this->createController()->createTable();
    }

    public function testGetTableRequiresAuth(): void
    {
        $this->request->method('getUser')->willReturn(null);

        $this->expectException(AuthException::class);
        $this->expectExceptionMessage('Authentication required');

        $this->createController()->getTable('products');
    }

    public function testGetTableThrowsNotFoundForMissingTable(): void
    {
        $this->request->method('getUser')->willReturn(['role' => 'admin']);

        // tableExists returns false
        $this->connection->method('fetchColumn')->willReturn(0);

        $this->expectException(NotFoundException::class);
        $this->expectExceptionMessage("'products' not found");

        $this->createController()->getTable('products');
    }

    public function testDropTableRequiresAdmin(): void
    {
        $this->request->method('getUser')->willReturn(['role' => 'developer']);

        $this->expectException(AuthException::class);

        $this->createController()->dropTable('products');
    }

    public function testDropTableProtectsSystemTables(): void
    {
        $this->request->method('getUser')->willReturn(['role' => 'admin']);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Cannot drop system table');

        $this->createController()->dropTable('users');
    }

    public function testDropTableProtectsApiKeysTable(): void
    {
        $this->request->method('getUser')->willReturn(['role' => 'admin']);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Cannot drop system table');

        $this->createController()->dropTable('api_keys');
    }

    public function testDropTableThrowsNotFoundForMissingTable(): void
    {
        $this->request->method('getUser')->willReturn(['role' => 'admin']);
        $this->connection->method('fetchColumn')->willReturn(0);

        $this->expectException(NotFoundException::class);

        $this->createController()->dropTable('nonexistent');
    }

    public function testAddColumnRequiresAdmin(): void
    {
        $this->request->method('getUser')->willReturn(null);

        $this->expectException(AuthException::class);

        $this->createController()->addColumn('products');
    }

    public function testAddColumnRequiresColumnField(): void
    {
        $this->request->method('getUser')->willReturn(['role' => 'admin']);
        $this->connection->method('fetchColumn')->willReturn(1); // table exists
        $this->request->method('getJson')->willReturn([
            'definition' => ['type' => 'TEXT'],
        ]);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('"column" field is required');

        $this->createController()->addColumn('products');
    }

    public function testAddColumnRequiresDefinitionField(): void
    {
        $this->request->method('getUser')->willReturn(['role' => 'admin']);
        $this->connection->method('fetchColumn')->willReturn(1);
        $this->request->method('getJson')->willReturn([
            'column' => 'description',
        ]);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('"definition" field is required');

        $this->createController()->addColumn('products');
    }

    public function testAddColumnThrowsNotFoundForMissingTable(): void
    {
        $this->request->method('getUser')->willReturn(['role' => 'admin']);
        $this->connection->method('fetchColumn')->willReturn(0);

        $this->expectException(NotFoundException::class);

        $this->createController()->addColumn('nonexistent');
    }

    public function testModifyColumnRequiresAdmin(): void
    {
        $this->request->method('getUser')->willReturn(['role' => 'user']);

        $this->expectException(AuthException::class);

        $this->createController()->modifyColumn('products', 'name');
    }

    public function testModifyColumnRequiresDefinition(): void
    {
        $this->request->method('getUser')->willReturn(['role' => 'admin']);
        $this->connection->method('fetchColumn')->willReturn(1);
        $this->request->method('getJson')->willReturn([]);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('"definition" field is required');

        $this->createController()->modifyColumn('products', 'name');
    }

    public function testModifyColumnThrowsNotFoundForMissingTable(): void
    {
        $this->request->method('getUser')->willReturn(['role' => 'admin']);
        $this->connection->method('fetchColumn')->willReturn(0);

        $this->expectException(NotFoundException::class);

        $this->createController()->modifyColumn('nonexistent', 'col');
    }

    public function testDropColumnRequiresAdmin(): void
    {
        $this->request->method('getUser')->willReturn(null);

        $this->expectException(AuthException::class);

        $this->createController()->dropColumn('products', 'description');
    }

    public function testDropColumnThrowsNotFoundForMissingTable(): void
    {
        $this->request->method('getUser')->willReturn(['role' => 'admin']);
        $this->connection->method('fetchColumn')->willReturn(0);

        $this->expectException(NotFoundException::class);

        $this->createController()->dropColumn('nonexistent', 'col');
    }
}
