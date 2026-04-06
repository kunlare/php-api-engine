<?php

declare(strict_types=1);

namespace Kunlare\PhpApiEngine\Tests\Unit\Crud;

use Kunlare\PhpApiEngine\Crud\Validator;
use Kunlare\PhpApiEngine\Exceptions\ValidationException;
use Kunlare\PhpApiEngine\Tests\TestCase;

class ValidatorTest extends TestCase
{
    private Validator $validator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->validator = new Validator();
    }

    public function testRequiredRulePassesWithValue(): void
    {
        $this->expectNotToPerformAssertions();

        $this->validator->validate(
            ['name' => 'John'],
            ['name' => 'required']
        );
    }

    public function testRequiredRuleFailsWithEmptyValue(): void
    {
        $this->expectException(ValidationException::class);

        $this->validator->validate(
            ['name' => ''],
            ['name' => 'required']
        );
    }

    public function testRequiredRuleFailsWithMissingField(): void
    {
        $this->expectException(ValidationException::class);

        $this->validator->validate(
            [],
            ['name' => 'required']
        );
    }

    public function testRequiredRuleFailsWithNull(): void
    {
        $this->expectException(ValidationException::class);

        $this->validator->validate(
            ['name' => null],
            ['name' => 'required']
        );
    }

    public function testEmailRulePassesWithValidEmail(): void
    {
        $this->expectNotToPerformAssertions();

        $this->validator->validate(
            ['email' => 'test@example.com'],
            ['email' => 'required|email']
        );
    }

    public function testEmailRuleFailsWithInvalidEmail(): void
    {
        $this->expectException(ValidationException::class);

        $this->validator->validate(
            ['email' => 'not-an-email'],
            ['email' => 'required|email']
        );
    }

    public function testMinRulePassesWithLongEnoughString(): void
    {
        $this->expectNotToPerformAssertions();

        $this->validator->validate(
            ['name' => 'John'],
            ['name' => 'required|min:3']
        );
    }

    public function testMinRuleFailsWithShortString(): void
    {
        $this->expectException(ValidationException::class);

        $this->validator->validate(
            ['name' => 'Jo'],
            ['name' => 'required|min:3']
        );
    }

    public function testMinRuleWorksWithNumericValues(): void
    {
        $this->expectException(ValidationException::class);

        $this->validator->validate(
            ['age' => 5],
            ['age' => 'required|numeric|min:18']
        );
    }

    public function testMaxRulePassesWithShortEnoughString(): void
    {
        $this->expectNotToPerformAssertions();

        $this->validator->validate(
            ['name' => 'John'],
            ['name' => 'required|max:10']
        );
    }

    public function testMaxRuleFailsWithLongString(): void
    {
        $this->expectException(ValidationException::class);

        $this->validator->validate(
            ['name' => 'A very long name that exceeds the limit'],
            ['name' => 'required|max:10']
        );
    }

    public function testNumericRulePassesWithNumber(): void
    {
        $this->expectNotToPerformAssertions();

        $this->validator->validate(
            ['price' => 99.99],
            ['price' => 'required|numeric']
        );
    }

    public function testNumericRulePassesWithNumericString(): void
    {
        $this->expectNotToPerformAssertions();

        $this->validator->validate(
            ['price' => '99.99'],
            ['price' => 'required|numeric']
        );
    }

    public function testNumericRuleFailsWithNonNumeric(): void
    {
        $this->expectException(ValidationException::class);

        $this->validator->validate(
            ['price' => 'abc'],
            ['price' => 'required|numeric']
        );
    }

    public function testIntegerRulePassesWithInteger(): void
    {
        $this->expectNotToPerformAssertions();

        $this->validator->validate(
            ['count' => 42],
            ['count' => 'required|integer']
        );
    }

    public function testIntegerRulePassesWithNumericString(): void
    {
        $this->expectNotToPerformAssertions();

        $this->validator->validate(
            ['count' => '42'],
            ['count' => 'required|integer']
        );
    }

    public function testIntegerRuleFailsWithFloat(): void
    {
        $this->expectException(ValidationException::class);

        $this->validator->validate(
            ['count' => 3.14],
            ['count' => 'required|integer']
        );
    }

    public function testStringRulePassesWithString(): void
    {
        $this->expectNotToPerformAssertions();

        $this->validator->validate(
            ['name' => 'John'],
            ['name' => 'required|string']
        );
    }

    public function testStringRuleFailsWithNonString(): void
    {
        $this->expectException(ValidationException::class);

        $this->validator->validate(
            ['name' => 123],
            ['name' => 'required|string']
        );
    }

    public function testInRulePassesWithAllowedValue(): void
    {
        $this->expectNotToPerformAssertions();

        $this->validator->validate(
            ['role' => 'admin'],
            ['role' => 'required|in:admin,user,developer']
        );
    }

    public function testInRuleFailsWithDisallowedValue(): void
    {
        $this->expectException(ValidationException::class);

        $this->validator->validate(
            ['role' => 'superadmin'],
            ['role' => 'required|in:admin,user,developer']
        );
    }

    public function testRegexRulePassesWithMatchingValue(): void
    {
        $this->expectNotToPerformAssertions();

        $this->validator->validate(
            ['code' => 'ABC123'],
            ['code' => 'required|regex:/^[A-Z]{3}\d{3}$/']
        );
    }

    public function testRegexRuleFailsWithNonMatchingValue(): void
    {
        $this->expectException(ValidationException::class);

        $this->validator->validate(
            ['code' => 'abc'],
            ['code' => 'required|regex:/^[A-Z]{3}\d{3}$/']
        );
    }

    public function testMultipleRulesAllApplied(): void
    {
        $this->expectNotToPerformAssertions();

        $this->validator->validate(
            [
                'name' => 'John',
                'email' => 'john@test.com',
                'role' => 'admin',
            ],
            [
                'name' => 'required|string|min:2|max:50',
                'email' => 'required|email',
                'role' => 'required|in:admin,user,developer',
            ]
        );
    }

    public function testValidationExceptionContainsErrors(): void
    {
        try {
            $this->validator->validate(
                ['name' => '', 'email' => 'invalid'],
                ['name' => 'required', 'email' => 'required|email']
            );
            $this->fail('Expected ValidationException was not thrown');
        } catch (ValidationException $e) {
            $errors = $e->getErrors();
            $this->assertArrayHasKey('name', $errors);
            $this->assertArrayHasKey('email', $errors);
            $this->assertEquals(422, $e->getStatusCode());
        }
    }

    public function testValidateEmailReturnsTrueForValidEmail(): void
    {
        $this->assertTrue($this->validator->validateEmail('test@example.com'));
        $this->assertTrue($this->validator->validateEmail('user+tag@domain.co.uk'));
    }

    public function testValidateEmailReturnsFalseForInvalidEmail(): void
    {
        $this->assertFalse($this->validator->validateEmail('not-an-email'));
        $this->assertFalse($this->validator->validateEmail('@missing-local.com'));
        $this->assertFalse($this->validator->validateEmail('missing@'));
    }

    public function testValidatePasswordReturnsTrueForValidPassword(): void
    {
        $this->assertTrue($this->validator->validatePassword('MyP@ss123', 8, true));
    }

    public function testValidatePasswordReturnsFalseForShortPassword(): void
    {
        $this->assertFalse($this->validator->validatePassword('Ab1!', 8, true));
    }

    public function testValidatePasswordReturnsFalseWithoutSpecialChars(): void
    {
        $this->assertFalse($this->validator->validatePassword('Password123', 8, true));
    }

    public function testValidatePasswordPassesWithoutSpecialCharsRequired(): void
    {
        $this->assertTrue($this->validator->validatePassword('Password123', 8, false));
    }

    public function testSanitizeString(): void
    {
        $result = $this->validator->sanitize('<script>alert("xss")</script>');
        $this->assertEquals('&lt;script&gt;alert(&quot;xss&quot;)&lt;/script&gt;', $result);
    }

    public function testSanitizeTrimsWhitespace(): void
    {
        $result = $this->validator->sanitize('  hello world  ');
        $this->assertEquals('hello world', $result);
    }

    public function testSanitizeArray(): void
    {
        $result = $this->validator->sanitize(['<b>bold</b>', 'normal']);
        $this->assertIsArray($result);
        $this->assertEquals('&lt;b&gt;bold&lt;/b&gt;', $result[0]);
        $this->assertEquals('normal', $result[1]);
    }

    public function testSanitizeNonStringPassesThrough(): void
    {
        $this->assertEquals(42, $this->validator->sanitize(42));
        $this->assertEquals(3.14, $this->validator->sanitize(3.14));
        $this->assertTrue($this->validator->sanitize(true));
        $this->assertNull($this->validator->sanitize(null));
    }

    public function testOptionalFieldsSkippedWhenEmpty(): void
    {
        $this->expectNotToPerformAssertions();

        $this->validator->validate(
            ['name' => 'John'],
            [
                'name' => 'required|string',
                'bio' => 'string|max:500', // not required, not in data
            ]
        );
    }

    public function testArrayOfRules(): void
    {
        $this->expectNotToPerformAssertions();

        $this->validator->validate(
            ['name' => 'John'],
            ['name' => ['required', 'string', 'min:2']]
        );
    }
}
