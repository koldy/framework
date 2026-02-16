<?php

declare(strict_types=1);

namespace Tests;

use Koldy\Validator;
use Koldy\Validator\Exception as InvalidDataException;
use Koldy\Validator\ConfigException as ValidatorConfigException;
use PHPUnit\Framework\TestCase;

class ValidatorTest extends TestCase
{

	// ── required ──

	public function testRequiredPassesWithValue(): void
	{
		$v = new Validator(['name' => 'required'], ['name' => 'John']);
		$this->assertTrue($v->isAllValid());
	}

	public function testRequiredFailsWithNull(): void
	{
		$v = new Validator(['name' => 'required'], ['name' => null]);
		$this->assertFalse($v->isAllValid());
		$this->assertArrayHasKey('name', $v->getMessages());
	}

	public function testRequiredFailsWithEmptyString(): void
	{
		$v = new Validator(['name' => 'required'], ['name' => '']);
		$this->assertFalse($v->isAllValid());
	}

	public function testRequiredPassesWithNumericZero(): void
	{
		$v = new Validator(['age' => 'required'], ['age' => 0]);
		$this->assertTrue($v->isAllValid());
	}

	public function testRequiredFailsWithEmptyArray(): void
	{
		$v = new Validator(['items' => 'required'], ['items' => []]);
		$this->assertFalse($v->isAllValid());
	}

	public function testRequiredPassesWithNonEmptyArray(): void
	{
		$v = new Validator(['items' => 'required'], ['items' => [1, 2]]);
		$this->assertTrue($v->isAllValid());
	}

	public function testRequiredFailsWithMissingKey(): void
	{
		$v = new Validator(['name' => 'required'], []);
		$this->assertFalse($v->isAllValid());
	}

	// ── integer ──

	public function testIntegerPassesWithValidInteger(): void
	{
		$v = new Validator(['age' => 'integer'], ['age' => '42']);
		$this->assertTrue($v->isAllValid());
	}

	public function testIntegerPassesWithNegative(): void
	{
		$v = new Validator(['temp' => 'integer'], ['temp' => '-5']);
		$this->assertTrue($v->isAllValid());
	}

	public function testIntegerFailsWithDecimal(): void
	{
		$v = new Validator(['val' => 'integer'], ['val' => '3.14']);
		$this->assertFalse($v->isAllValid());
	}

	public function testIntegerFailsWithNonNumeric(): void
	{
		$v = new Validator(['val' => 'integer'], ['val' => 'abc']);
		$this->assertFalse($v->isAllValid());
	}

	public function testIntegerPassesWithNull(): void
	{
		$v = new Validator(['val' => 'integer'], ['val' => null]);
		$this->assertTrue($v->isAllValid());
	}

	public function testIntegerPassesWithEmptyString(): void
	{
		$v = new Validator(['val' => 'integer'], ['val' => '']);
		$this->assertTrue($v->isAllValid());
	}

	// ── numeric ──

	public function testNumericPassesWithInteger(): void
	{
		$v = new Validator(['val' => 'numeric'], ['val' => '42']);
		$this->assertTrue($v->isAllValid());
	}

	public function testNumericPassesWithDecimal(): void
	{
		$v = new Validator(['val' => 'numeric'], ['val' => '3.14']);
		$this->assertTrue($v->isAllValid());
	}

	public function testNumericFailsWithNonNumeric(): void
	{
		$v = new Validator(['val' => 'numeric'], ['val' => 'abc']);
		$this->assertFalse($v->isAllValid());
	}

	public function testNumericPassesWithNull(): void
	{
		$v = new Validator(['val' => 'numeric'], ['val' => null]);
		$this->assertTrue($v->isAllValid());
	}

	// ── min / max ──

	public function testMinPassesWhenValueAboveMinimum(): void
	{
		$v = new Validator(['age' => 'min:18'], ['age' => '20']);
		$this->assertTrue($v->isAllValid());
	}

	public function testMinFailsWhenValueBelowMinimum(): void
	{
		$v = new Validator(['age' => 'min:18'], ['age' => '10']);
		$this->assertFalse($v->isAllValid());
	}

	public function testMinPassesWithNull(): void
	{
		$v = new Validator(['age' => 'min:18'], ['age' => null]);
		$this->assertTrue($v->isAllValid());
	}

	public function testMaxPassesWhenValueBelowMaximum(): void
	{
		$v = new Validator(['age' => 'max:100'], ['age' => '50']);
		$this->assertTrue($v->isAllValid());
	}

	public function testMaxFailsWhenValueAboveMaximum(): void
	{
		$v = new Validator(['age' => 'max:100'], ['age' => '150']);
		$this->assertFalse($v->isAllValid());
	}

	public function testMinMaxCombinedPasses(): void
	{
		$v = new Validator(['age' => 'min:1|max:100'], ['age' => '50']);
		$this->assertTrue($v->isAllValid());
	}

	// ── bool / boolean ──

	public function testBoolPassesWithTrue(): void
	{
		$v = new Validator(['active' => 'bool'], ['active' => true]);
		$this->assertTrue($v->isAllValid());
	}

	public function testBoolPassesWithFalse(): void
	{
		$v = new Validator(['active' => 'bool'], ['active' => false]);
		$this->assertTrue($v->isAllValid());
	}

	public function testBoolPassesWithStringTrue(): void
	{
		$v = new Validator(['active' => 'bool'], ['active' => 'true']);
		$this->assertTrue($v->isAllValid());
	}

	public function testBoolPassesWithStringFalse(): void
	{
		$v = new Validator(['active' => 'bool'], ['active' => 'false']);
		$this->assertTrue($v->isAllValid());
	}

	public function testBoolFailsWithInvalidValue(): void
	{
		$v = new Validator(['active' => 'bool'], ['active' => 'yes']);
		$this->assertFalse($v->isAllValid());
	}

	public function testBooleanAliasWorks(): void
	{
		$v = new Validator(['active' => 'boolean'], ['active' => true]);
		$this->assertTrue($v->isAllValid());
	}

	public function testBoolPassesWithNull(): void
	{
		$v = new Validator(['active' => 'bool'], ['active' => null]);
		$this->assertTrue($v->isAllValid());
	}

	// ── alpha ──

	public function testAlphaPassesWithLetters(): void
	{
		$v = new Validator(['name' => 'alpha'], ['name' => 'John']);
		$this->assertTrue($v->isAllValid());
	}

	public function testAlphaFailsWithNumbers(): void
	{
		$v = new Validator(['name' => 'alpha'], ['name' => 'John123']);
		$this->assertFalse($v->isAllValid());
	}

	public function testAlphaPassesWithNull(): void
	{
		$v = new Validator(['name' => 'alpha'], ['name' => null]);
		$this->assertTrue($v->isAllValid());
	}

	// ── alphaNum ──

	public function testAlphaNumPassesWithLettersAndNumbers(): void
	{
		$v = new Validator(['code' => 'alphaNum'], ['code' => 'abc123']);
		$this->assertTrue($v->isAllValid());
	}

	public function testAlphaNumFailsWithSpecialChars(): void
	{
		$v = new Validator(['code' => 'alphaNum'], ['code' => 'abc-123']);
		$this->assertFalse($v->isAllValid());
	}

	// ── hex ──

	public function testHexPassesWithValidHex(): void
	{
		$v = new Validator(['color' => 'hex'], ['color' => 'ff00aa']);
		$this->assertTrue($v->isAllValid());
	}

	public function testHexPassesWithNegativeHex(): void
	{
		$v = new Validator(['val' => 'hex'], ['val' => '-ff']);
		$this->assertTrue($v->isAllValid());
	}

	public function testHexFailsWithInvalidHex(): void
	{
		$v = new Validator(['color' => 'hex'], ['color' => 'xyz']);
		$this->assertFalse($v->isAllValid());
	}

	// ── email ──

	public function testEmailPassesWithValidEmail(): void
	{
		$v = new Validator(['email' => 'email'], ['email' => 'user@example.com']);
		$this->assertTrue($v->isAllValid());
	}

	public function testEmailFailsWithInvalidEmail(): void
	{
		$v = new Validator(['email' => 'email'], ['email' => 'not-an-email']);
		$this->assertFalse($v->isAllValid());
	}

	public function testEmailPassesWithNull(): void
	{
		$v = new Validator(['email' => 'email'], ['email' => null]);
		$this->assertTrue($v->isAllValid());
	}

	// ── slug ──

	public function testSlugPassesWithValidSlug(): void
	{
		$v = new Validator(['slug' => 'slug'], ['slug' => 'my-cool-post-123']);
		$this->assertTrue($v->isAllValid());
	}

	public function testSlugFailsWithUppercase(): void
	{
		$v = new Validator(['slug' => 'slug'], ['slug' => 'My-Cool-Post']);
		$this->assertFalse($v->isAllValid());
	}

	public function testSlugFailsWithSpaces(): void
	{
		$v = new Validator(['slug' => 'slug'], ['slug' => 'my cool post']);
		$this->assertFalse($v->isAllValid());
	}

	// ── uuid ──

	public function testUuidPassesWithValidUuid(): void
	{
		$v = new Validator(['id' => 'uuid'], ['id' => '550e8400-e29b-41d4-a716-446655440000']);
		$this->assertTrue($v->isAllValid());
	}

	public function testUuidFailsWithInvalidUuid(): void
	{
		$v = new Validator(['id' => 'uuid'], ['id' => 'not-a-uuid']);
		$this->assertFalse($v->isAllValid());
	}

	public function testUuidPassesWithNull(): void
	{
		$v = new Validator(['id' => 'uuid'], ['id' => null]);
		$this->assertTrue($v->isAllValid());
	}

	// ── is ──

	public function testIsPassesWithExactMatch(): void
	{
		$v = new Validator(['answer' => 'is:yes'], ['answer' => 'yes']);
		$this->assertTrue($v->isAllValid());
	}

	public function testIsFailsWithDifferentValue(): void
	{
		$v = new Validator(['answer' => 'is:yes'], ['answer' => 'no']);
		$this->assertFalse($v->isAllValid());
	}

	public function testIsPassesWithNull(): void
	{
		$v = new Validator(['answer' => 'is:yes'], ['answer' => null]);
		$this->assertTrue($v->isAllValid());
	}

	// ── decimal ──

	public function testDecimalPassesWithValidDecimals(): void
	{
		$v = new Validator(['price' => 'decimal:2'], ['price' => '19.99']);
		$this->assertTrue($v->isAllValid());
	}

	public function testDecimalFailsWithTooManyDecimals(): void
	{
		$v = new Validator(['price' => 'decimal:2'], ['price' => '19.999']);
		$this->assertFalse($v->isAllValid());
	}

	public function testDecimalPassesWithInteger(): void
	{
		$v = new Validator(['price' => 'decimal:2'], ['price' => '20']);
		$this->assertTrue($v->isAllValid());
	}

	public function testDecimalPassesWithNull(): void
	{
		$v = new Validator(['price' => 'decimal:2'], ['price' => null]);
		$this->assertTrue($v->isAllValid());
	}

	// ── same / different ──

	public function testSamePassesWhenFieldsMatch(): void
	{
		$v = new Validator(
			['password' => 'required', 'confirm' => 'same:password'],
			['password' => 'secret', 'confirm' => 'secret']
		);
		$this->assertTrue($v->isAllValid());
	}

	public function testSameFailsWhenFieldsDiffer(): void
	{
		$v = new Validator(
			['password' => 'required', 'confirm' => 'same:password'],
			['password' => 'secret', 'confirm' => 'other']
		);
		$this->assertFalse($v->isAllValid());
	}

	public function testDifferentPassesWhenFieldsDiffer(): void
	{
		$v = new Validator(
			['old' => 'required', 'new' => 'different:old'],
			['old' => 'oldpass', 'new' => 'newpass']
		);
		$this->assertTrue($v->isAllValid());
	}

	public function testDifferentFailsWhenFieldsMatch(): void
	{
		$v = new Validator(
			['old' => 'required', 'new' => 'different:old'],
			['old' => 'same', 'new' => 'same']
		);
		$this->assertFalse($v->isAllValid());
	}

	// ── date ──

	public function testDatePassesWithValidDate(): void
	{
		$v = new Validator(['date' => 'date'], ['date' => '2024-01-15']);
		$this->assertTrue($v->isAllValid());
	}

	public function testDateFailsWithInvalidDate(): void
	{
		$v = new Validator(['date' => 'date'], ['date' => 'not-a-date']);
		$this->assertFalse($v->isAllValid());
	}

	public function testDatePassesWithFormat(): void
	{
		$v = new Validator(['date' => 'date:Y-m-d'], ['date' => '2024-01-15']);
		$this->assertTrue($v->isAllValid());
	}

	public function testDateFailsWithWrongFormat(): void
	{
		$v = new Validator(['date' => 'date:Y-m-d'], ['date' => '15/01/2024']);
		$this->assertFalse($v->isAllValid());
	}

	public function testDatePassesWithNull(): void
	{
		$v = new Validator(['date' => 'date'], ['date' => null]);
		$this->assertTrue($v->isAllValid());
	}

	// ── anyOf ──

	public function testAnyOfPassesWithAllowedValue(): void
	{
		$v = new Validator(['color' => 'anyOf:red,green,blue'], ['color' => 'red']);
		$this->assertTrue($v->isAllValid());
	}

	public function testAnyOfFailsWithDisallowedValue(): void
	{
		$v = new Validator(['color' => 'anyOf:red,green,blue'], ['color' => 'yellow']);
		$this->assertFalse($v->isAllValid());
	}

	public function testAnyOfPassesWithNull(): void
	{
		$v = new Validator(['color' => 'anyOf:red,green,blue'], ['color' => null]);
		$this->assertTrue($v->isAllValid());
	}

	// ── present ──

	public function testPresentPassesWhenKeyExists(): void
	{
		$v = new Validator(['name' => 'present'], ['name' => null]);
		$this->assertTrue($v->isAllValid());
	}

	public function testPresentFailsWhenKeyMissing(): void
	{
		$v = new Validator(['name' => 'present'], []);
		$this->assertFalse($v->isAllValid());
	}

	// ── length ──

	public function testLengthPassesWithExactLength(): void
	{
		$v = new Validator(['code' => 'length:5'], ['code' => 'ABCDE']);
		$this->assertTrue($v->isAllValid());
	}

	public function testLengthFailsWithWrongLength(): void
	{
		$v = new Validator(['code' => 'length:5'], ['code' => 'ABC']);
		$this->assertFalse($v->isAllValid());
	}

	public function testLengthPassesWithNull(): void
	{
		$v = new Validator(['code' => 'length:5'], ['code' => null]);
		$this->assertTrue($v->isAllValid());
	}

	// ── startsWith / endsWith ──

	public function testStartsWithPasses(): void
	{
		$v = new Validator(['phone' => 'startsWith:+385'], ['phone' => '+385912345678']);
		$this->assertTrue($v->isAllValid());
	}

	public function testStartsWithFails(): void
	{
		$v = new Validator(['phone' => 'startsWith:+385'], ['phone' => '+1234567890']);
		$this->assertFalse($v->isAllValid());
	}

	public function testStartsWithPassesWithNull(): void
	{
		$v = new Validator(['phone' => 'startsWith:+385'], ['phone' => null]);
		$this->assertTrue($v->isAllValid());
	}

	public function testEndsWithPasses(): void
	{
		$v = new Validator(['file' => 'endsWith:.php'], ['file' => 'index.php']);
		$this->assertTrue($v->isAllValid());
	}

	public function testEndsWithFails(): void
	{
		$v = new Validator(['file' => 'endsWith:.php'], ['file' => 'index.html']);
		$this->assertFalse($v->isAllValid());
	}

	public function testEndsWithPassesWithNull(): void
	{
		$v = new Validator(['file' => 'endsWith:.php'], ['file' => null]);
		$this->assertTrue($v->isAllValid());
	}

	// ── array ──

	public function testArrayPassesWithArray(): void
	{
		$v = new Validator(['items' => 'array'], ['items' => [1, 2, 3]]);
		$this->assertTrue($v->isAllValid());
	}

	public function testArrayFailsWithNonArray(): void
	{
		$v = new Validator(['items' => 'array'], ['items' => 'not-array']);
		$this->assertFalse($v->isAllValid());
	}

	public function testArrayWithCountPasses(): void
	{
		$v = new Validator(['items' => 'array:3'], ['items' => [1, 2, 3]]);
		$this->assertTrue($v->isAllValid());
	}

	public function testArrayWithCountFails(): void
	{
		$v = new Validator(['items' => 'array:3'], ['items' => [1, 2]]);
		$this->assertFalse($v->isAllValid());
	}

	public function testArrayPassesWithNull(): void
	{
		$v = new Validator(['items' => 'array'], ['items' => null]);
		$this->assertTrue($v->isAllValid());
	}

	// ── getMessages / getRules ──

	public function testGetMessagesReturnsInvalidFields(): void
	{
		$v = new Validator(
			['name' => 'required', 'email' => 'required'],
			['name' => '', 'email' => '']
		);
		$v->validate();
		$messages = $v->getMessages();
		$this->assertArrayHasKey('name', $messages);
		$this->assertArrayHasKey('email', $messages);
	}

	public function testGetMessagesReturnsEmptyWhenValid(): void
	{
		$v = new Validator(['name' => 'required'], ['name' => 'John']);
		$v->validate();
		$this->assertEmpty($v->getMessages());
	}

	public function testGetRulesReturnsRules(): void
	{
		$rules = ['name' => 'required', 'age' => 'integer|min:0'];
		$v = new Validator($rules, ['name' => 'John', 'age' => '25']);
		$this->assertSame($rules, $v->getRules());
	}

	// ── create static factory ──

	public function testCreateReturnsValidatorOnValidData(): void
	{
		$v = Validator::create(
			['name' => 'required'],
			['name' => 'John'],
			true
		);
		$this->assertInstanceOf(Validator::class, $v);
	}

	public function testCreateThrowsOnInvalidData(): void
	{
		$this->expectException(InvalidDataException::class);
		Validator::create(
			['name' => 'required'],
			['name' => ''],
			true
		);
	}

	public function testCreateWithoutAutoValidation(): void
	{
		$v = Validator::create(
			['name' => 'required'],
			['name' => ''],
			false
		);
		$this->assertInstanceOf(Validator::class, $v);
		$this->assertFalse($v->isAllValid());
	}

	// ── combined rules ──

	public function testCombinedRequiredIntegerMinMax(): void
	{
		$v = new Validator(
			['age' => 'required|integer|min:1|max:150'],
			['age' => '25']
		);
		$this->assertTrue($v->isAllValid());
	}

	public function testCombinedRequiredIntegerMinMaxFails(): void
	{
		$v = new Validator(
			['age' => 'required|integer|min:1|max:150'],
			['age' => '200']
		);
		$this->assertFalse($v->isAllValid());
	}

	public function testCombinedRequiredEmail(): void
	{
		$v = new Validator(
			['email' => 'required|email'],
			['email' => 'user@example.com']
		);
		$this->assertTrue($v->isAllValid());
	}

	// ── null rule (skip validation) ──

	public function testNullRuleSkipsValidation(): void
	{
		$v = new Validator(
			['name' => 'required', 'optional' => null],
			['name' => 'John', 'optional' => 'anything']
		);
		$this->assertTrue($v->isAllValid());
	}

	// ── invalid rule throws config exception ──

	public function testInvalidRuleThrowsConfigException(): void
	{
		$this->expectException(ValidatorConfigException::class);
		$v = new Validator(['val' => 'nonExistentRule'], ['val' => 'test']);
		$v->validate();
	}

	// ── multiple fields validation ──

	public function testMultipleFieldsValidation(): void
	{
		$v = new Validator(
			[
				'name' => 'required|alpha',
				'age' => 'required|integer|min:0|max:150',
				'email' => 'required|email',
			],
			[
				'name' => 'John',
				'age' => '30',
				'email' => 'john@example.com',
			]
		);
		$this->assertTrue($v->isAllValid());
	}

	public function testMultipleFieldsPartialFailure(): void
	{
		$v = new Validator(
			[
				'name' => 'required',
				'email' => 'required|email',
			],
			[
				'name' => 'John',
				'email' => 'not-valid',
			]
		);
		$this->assertFalse($v->isAllValid());
		$messages = $v->getMessages();
		$this->assertArrayNotHasKey('name', $messages);
		$this->assertArrayHasKey('email', $messages);
	}

	// ── min/max with array rule ──

	public function testMinWithArrayRuleChecksArraySize(): void
	{
		$v = new Validator(['items' => 'array|min:2'], ['items' => [1, 2, 3]]);
		$this->assertTrue($v->isAllValid());
	}

	public function testMaxWithArrayRuleChecksArraySize(): void
	{
		$v = new Validator(['items' => 'array|max:3'], ['items' => [1, 2]]);
		$this->assertTrue($v->isAllValid());
	}

	public function testMaxWithArrayRuleFailsWhenTooMany(): void
	{
		$v = new Validator(['items' => 'array|max:2'], ['items' => [1, 2, 3]]);
		$this->assertFalse($v->isAllValid());
	}
}
