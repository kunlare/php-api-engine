<?php

declare(strict_types=1);

namespace Kunlare\PhpApiEngine\Tests\Unit\Database;

use Kunlare\PhpApiEngine\Database\Connection;
use Kunlare\PhpApiEngine\Database\QueryBuilder;
use Kunlare\PhpApiEngine\Exceptions\DatabaseException;
use Kunlare\PhpApiEngine\Tests\TestCase;
use PDO;
use PDOStatement;
use ReflectionClass;

class QueryBuilderTest extends TestCase
{
    private QueryBuilder $queryBuilder;
    private Connection $connection;

    protected function setUp(): void
    {
        parent::setUp();

        // Create a mock Connection
        $this->connection = $this->createMock(Connection::class);
        $this->queryBuilder = new QueryBuilder($this->connection);
    }

    public function testSelectReturnsNewInstance(): void
    {
        $result = $this->queryBuilder->select('users');
        $this->assertInstanceOf(QueryBuilder::class, $result);
        $this->assertNotSame($this->queryBuilder, $result);
    }

    public function testWhereReturnsNewInstance(): void
    {
        $query = $this->queryBuilder->select('users');
        $result = $query->where('id', '=', 1);
        $this->assertInstanceOf(QueryBuilder::class, $result);
        $this->assertNotSame($query, $result);
    }

    public function testWhereInReturnsNewInstance(): void
    {
        $query = $this->queryBuilder->select('users');
        $result = $query->whereIn('id', [1, 2, 3]);
        $this->assertInstanceOf(QueryBuilder::class, $result);
    }

    public function testWhereInEmptyArrayAddsImpossibleCondition(): void
    {
        $this->connection->expects($this->once())
            ->method('fetchAll')
            ->with(
                $this->stringContains('1 = 0'),
                $this->anything()
            )
            ->willReturn([]);

        $this->queryBuilder->select('users')
            ->whereIn('id', [])
            ->get();
    }

    public function testWhereLikeReturnsNewInstance(): void
    {
        $query = $this->queryBuilder->select('users');
        $result = $query->whereLike('name', '%test%');
        $this->assertInstanceOf(QueryBuilder::class, $result);
    }

    public function testWhereNullReturnsNewInstance(): void
    {
        $query = $this->queryBuilder->select('users');
        $result = $query->whereNull('deleted_at');
        $this->assertInstanceOf(QueryBuilder::class, $result);
    }

    public function testWhereNotNullReturnsNewInstance(): void
    {
        $query = $this->queryBuilder->select('users');
        $result = $query->whereNotNull('email');
        $this->assertInstanceOf(QueryBuilder::class, $result);
    }

    public function testOrderByReturnsNewInstance(): void
    {
        $query = $this->queryBuilder->select('users');
        $result = $query->orderBy('name', 'ASC');
        $this->assertInstanceOf(QueryBuilder::class, $result);
    }

    public function testOrderByInvalidDirectionDefaultsToAsc(): void
    {
        $this->connection->expects($this->once())
            ->method('fetchAll')
            ->with(
                $this->stringContains('ORDER BY `name` ASC'),
                $this->anything()
            )
            ->willReturn([]);

        $this->queryBuilder->select('users')
            ->orderBy('name', 'INVALID')
            ->get();
    }

    public function testLimitReturnsNewInstance(): void
    {
        $query = $this->queryBuilder->select('users');
        $result = $query->limit(10);
        $this->assertInstanceOf(QueryBuilder::class, $result);
    }

    public function testOffsetReturnsNewInstance(): void
    {
        $query = $this->queryBuilder->select('users');
        $result = $query->offset(20);
        $this->assertInstanceOf(QueryBuilder::class, $result);
    }

    public function testGetCallsFetchAllOnConnection(): void
    {
        $expected = [['id' => 1, 'name' => 'Test']];

        $this->connection->expects($this->once())
            ->method('fetchAll')
            ->with(
                $this->stringContains('SELECT * FROM `users`'),
                $this->anything()
            )
            ->willReturn($expected);

        $result = $this->queryBuilder->select('users')->get();
        $this->assertEquals($expected, $result);
    }

    public function testFirstCallsFetchOnConnection(): void
    {
        $expected = ['id' => 1, 'name' => 'Test'];

        $this->connection->expects($this->once())
            ->method('fetch')
            ->with(
                $this->stringContains('LIMIT 1'),
                $this->anything()
            )
            ->willReturn($expected);

        $result = $this->queryBuilder->select('users')->first();
        $this->assertEquals($expected, $result);
    }

    public function testFirstReturnsNullWhenNoResults(): void
    {
        $this->connection->expects($this->once())
            ->method('fetch')
            ->willReturn(null);

        $result = $this->queryBuilder->select('users')->first();
        $this->assertNull($result);
    }

    public function testCountReturnsTotalCount(): void
    {
        $this->connection->expects($this->once())
            ->method('fetch')
            ->with(
                $this->stringContains('COUNT(*)'),
                $this->anything()
            )
            ->willReturn(['count' => 42]);

        $result = $this->queryBuilder->select('users')->count();
        $this->assertEquals(42, $result);
    }

    public function testInsertExecutesInsertQuery(): void
    {
        $this->connection->expects($this->once())
            ->method('execute')
            ->with(
                $this->stringContains('INSERT INTO `users`'),
                ['John', 'john@test.com']
            );

        $this->connection->expects($this->once())
            ->method('lastInsertId')
            ->willReturn('5');

        $result = $this->queryBuilder->insert('users', ['name' => 'John', 'email' => 'john@test.com']);
        $this->assertEquals(5, $result);
    }

    public function testInsertEmptyDataThrowsException(): void
    {
        $this->expectException(DatabaseException::class);
        $this->expectExceptionMessage('Cannot insert empty data');

        $this->queryBuilder->insert('users', []);
    }

    public function testUpdateExecutesUpdateQuery(): void
    {
        $this->connection->expects($this->once())
            ->method('execute')
            ->with(
                $this->stringContains('UPDATE `users` SET'),
                $this->anything()
            );

        $result = $this->queryBuilder
            ->where('id', '=', 1)
            ->update('users', ['name' => 'Updated']);

        $this->assertTrue($result);
    }

    public function testUpdateEmptyDataThrowsException(): void
    {
        $this->expectException(DatabaseException::class);
        $this->expectExceptionMessage('Cannot update with empty data');

        $this->queryBuilder->update('users', []);
    }

    public function testDeleteExecutesDeleteQuery(): void
    {
        $this->connection->expects($this->once())
            ->method('execute')
            ->with(
                $this->stringContains('DELETE FROM `users`'),
                $this->anything()
            );

        $result = $this->queryBuilder
            ->where('id', '=', 1)
            ->delete('users');

        $this->assertTrue($result);
    }

    public function testInvalidOperatorThrowsException(): void
    {
        $this->expectException(DatabaseException::class);
        $this->expectExceptionMessage('Invalid operator');

        $this->queryBuilder->select('users')->where('id', 'INVALID', 1)->get();
    }

    public function testApplyFiltersWithSimpleEquality(): void
    {
        $this->connection->expects($this->once())
            ->method('fetchAll')
            ->with(
                $this->stringContains('`status` = ?'),
                ['active']
            )
            ->willReturn([]);

        $this->queryBuilder
            ->applyFilters('users', ['status' => 'active'])
            ->get();
    }

    public function testApplyFiltersWithOperator(): void
    {
        $this->connection->expects($this->once())
            ->method('fetchAll')
            ->with(
                $this->stringContains('`age` >= ?'),
                [18]
            )
            ->willReturn([]);

        $this->queryBuilder
            ->applyFilters('users', [
                'age' => ['operator' => '>=', 'value' => 18],
            ])
            ->get();
    }

    public function testApplyFiltersWithLike(): void
    {
        $this->connection->expects($this->once())
            ->method('fetchAll')
            ->with(
                $this->stringContains('LIKE ?'),
                ['%John%']
            )
            ->willReturn([]);

        $this->queryBuilder
            ->applyFilters('users', [
                'name' => ['operator' => 'LIKE', 'value' => '%John%'],
            ])
            ->get();
    }

    public function testChainedWhereConditions(): void
    {
        $this->connection->expects($this->once())
            ->method('fetchAll')
            ->with(
                $this->callback(function (string $sql): bool {
                    return str_contains($sql, '`age` >= ?')
                        && str_contains($sql, '`status` = ?')
                        && str_contains($sql, 'AND');
                }),
                [18, 'active']
            )
            ->willReturn([]);

        $this->queryBuilder->select('users')
            ->where('age', '>=', 18)
            ->where('status', '=', 'active')
            ->get();
    }

    public function testSelectWithLimitAndOffset(): void
    {
        $this->connection->expects($this->once())
            ->method('fetchAll')
            ->with(
                $this->callback(function (string $sql): bool {
                    return str_contains($sql, 'LIMIT 10')
                        && str_contains($sql, 'OFFSET 20');
                }),
                $this->anything()
            )
            ->willReturn([]);

        $this->queryBuilder->select('users')
            ->limit(10)
            ->offset(20)
            ->get();
    }
}
