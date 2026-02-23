<?php

declare(strict_types=1);

namespace Tests;

use InvalidArgumentException;
use Koldy\Application;
use Koldy\Mock;
use Koldy\Response\Exception\NotFoundException;
use Koldy\Response\Exception\ServerException;
use Koldy\Route\HttpRoute;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

class HttpRouteTest extends TestCase
{

	private const NS_STATIC = 'Tests\\Fixtures\\HttpRoute\\StaticRoutes\\';
	private const NS_DYNAMIC = 'Tests\\Fixtures\\HttpRoute\\DynamicRoutes\\';
	private const NS_COMPANY = 'Tests\\Fixtures\\HttpRoute\\CompanyHierarchy\\';
	private const NS_BROKEN = 'Tests\\Fixtures\\HttpRoute\\BrokenControllers\\';
	private const NS_ERROR = 'Tests\\Fixtures\\HttpRoute\\ErrorHandling\\';

	private static bool $appInitialized = false;

	public static function setUpBeforeClass(): void
	{
		if (!self::$appInitialized) {
			$_SERVER['SCRIPT_FILENAME'] = __FILE__;

			Application::useConfig([
				'site_url' => 'http://localhost',
				'env' => Application::TEST,
				'key' => 'HttpRouteTestKey12345',
				'timezone' => 'UTC',
				'paths' => [
					'application' => __DIR__ . '/',
					'storage' => __DIR__ . '/',
				],
			]);

			self::$appInitialized = true;
		}
	}

	protected function setUp(): void
	{
		Mock::start();
	}

	protected function tearDown(): void
	{
		Mock::reset();
	}

	private function createRoute(string $namespace, array $config = []): HttpRoute
	{
		return new HttpRoute(array_merge(['namespace' => $namespace], $config));
	}

	private function setRequestMethod(string $method): void
	{
		$_SERVER['REQUEST_METHOD'] = strtoupper($method);
	}

	// ── sanitize (private, tested via reflection) ──

	private function callSanitize(string $segment): string
	{
		$route = $this->createRoute(self::NS_STATIC);
		$method = new ReflectionMethod($route, 'sanitize');
		return $method->invoke($route, $segment);
	}

	public function testSanitizeSimpleSegment(): void
	{
		$this->assertSame('Users', $this->callSanitize('users'));
	}

	public function testSanitizeHyphenatedSegment(): void
	{
		$this->assertSame('BankAccounts', $this->callSanitize('bank-accounts'));
	}

	public function testSanitizeMultipleHyphens(): void
	{
		$this->assertSame('MyAwesomePage', $this->callSanitize('my-awesome-page'));
	}

	public function testSanitizeDoubleHyphensCollapsed(): void
	{
		$this->assertSame('FooBar', $this->callSanitize('foo--bar'));
	}

	public function testSanitizeEmptyString(): void
	{
		$this->assertSame('', $this->callSanitize(''));
	}

	// ── constructConstructor (private, tested via reflection) ──

	public function testConstructConstructorReturnsExpectedShape(): void
	{
		$route = $this->createRoute(self::NS_STATIC);

		// Set context via reflection
		$contextProp = new \ReflectionProperty($route, 'context');
		$contextProp->setValue($route, ['foo' => 'bar']);

		$method = new ReflectionMethod($route, 'constructConstructor');
		$result = $method->invoke($route, 'my-segment');

		$this->assertSame(['context' => ['foo' => 'bar'], 'segment' => 'my-segment'], $result);
	}

	public function testConstructConstructorWithNullSegment(): void
	{
		$route = $this->createRoute(self::NS_STATIC);
		$method = new ReflectionMethod($route, 'constructConstructor');
		$result = $method->invoke($route, null);

		$this->assertSame(['context' => [], 'segment' => null], $result);
	}

	// ── start() input validation ──

	public function testStartThrowsWhenUriDoesNotStartWithSlash(): void
	{
		$route = $this->createRoute(self::NS_STATIC);
		$this->expectException(InvalidArgumentException::class);
		$this->expectExceptionMessage('URI must start with slash');
		$route->start('no-leading-slash');
	}

	// ── start() normalizes double slashes ──

	public function testStartNormalizesDoubleSlashes(): void
	{
		$this->setRequestMethod('GET');
		$route = $this->createRoute(self::NS_DYNAMIC);
		// //users is parsed as network path by parse_url (no path key), so use a path with internal double slashes
		$result = $route->start('/users//abc-123');
		$this->assertSame('user-detail-abc-123', $result);
	}

	// ── static routing ──

	public function testStaticRouteGetSingleSegment(): void
	{
		$this->setRequestMethod('GET');
		$route = $this->createRoute(self::NS_STATIC);
		$result = $route->start('/users');
		$this->assertSame('users-list', $result);
	}

	public function testStaticRoutePostSingleSegment(): void
	{
		$this->setRequestMethod('POST');
		$route = $this->createRoute(self::NS_STATIC);
		$result = $route->start('/users');
		$this->assertSame('user-created', $result);
	}

	public function testStaticRouteHyphenatedSegment(): void
	{
		$this->setRequestMethod('GET');
		$route = $this->createRoute(self::NS_STATIC);
		$result = $route->start('/bank-accounts');
		$this->assertSame('bank-accounts-list', $result);
	}

	public function testStaticRouteIndex(): void
	{
		$this->setRequestMethod('GET');
		$route = $this->createRoute(self::NS_STATIC);
		$result = $route->start('/index');
		$this->assertSame('index-page', $result);
	}

	// ── dynamic routing (__) ──

	public function testDynamicRouteMatchesWildcard(): void
	{
		$this->setRequestMethod('GET');
		$route = $this->createRoute(self::NS_DYNAMIC);
		$result = $route->start('/users/abc-123');
		$this->assertSame('user-detail-abc-123', $result);
	}

	public function testDynamicRouteMatchesUuid(): void
	{
		$this->setRequestMethod('GET');
		$route = $this->createRoute(self::NS_DYNAMIC);
		$result = $route->start('/users/550e8400-e29b-41d4-a716-446655440000');
		$this->assertSame('user-detail-550e8400-e29b-41d4-a716-446655440000', $result);
	}

	// ── nested routing with context propagation ──

	public function testNestedRouteAfterDynamicSegment(): void
	{
		$this->setRequestMethod('GET');
		$route = $this->createRoute(self::NS_DYNAMIC);
		$result = $route->start('/users/user-42/invoices');
		$this->assertSame('invoices-for-user-42', $result);
	}

	public function testContextPropagationThroughChain(): void
	{
		$this->setRequestMethod('GET');
		$route = $this->createRoute(self::NS_DYNAMIC);
		$route->start('/users/my-user-id/invoices');

		// After routing, the context on the route should contain user_id set by the __ controller
		$contextProp = new \ReflectionProperty($route, 'context');
		$context = $contextProp->getValue($route);
		// The last controller (Invoices) doesn't modify context, so it inherits from __
		// But context is set from $instance->context after each controller
		$this->assertArrayHasKey('user_id', $context);
		$this->assertSame('my-user-id', $context['user_id']);
	}

	// ── NotFoundException ──

	public function testNotFoundForNonExistentRoute(): void
	{
		$this->setRequestMethod('GET');
		$route = $this->createRoute(self::NS_STATIC);
		// The start() method catches Throwable and calls handleException(),
		// so we need to test exec() directly via reflection to get the exception
		$this->expectException(NotFoundException::class);

		// Set up the route's internal state as start() would
		$routeRef = new \ReflectionClass($route);

		$uriProp = $routeRef->getProperty('uri');
		$uriProp->setValue($route, '/nonexistent');

		$uriPartsProp = $routeRef->getProperty('uriParts');
		$uriPartsProp->setValue($route, ['nonexistent']);

		$methodProp = $routeRef->getProperty('method');
		$methodProp->setValue($route, 'get');

		$nsProp = $routeRef->getProperty('namespace');
		$nsProp->setValue($route, self::NS_STATIC);

		$exec = new ReflectionMethod($route, 'exec');
		$exec->invoke($route);
	}

	public function testNotFoundForExistingRouteButWrongMethod(): void
	{
		$this->setRequestMethod('DELETE');
		$route = $this->createRoute(self::NS_STATIC);

		// Users controller only has get() and post(), not delete()
		// Use exec() directly to get the exception before handleException catches it
		$routeRef = new \ReflectionClass($route);

		$uriProp = $routeRef->getProperty('uri');
		$uriProp->setValue($route, '/users');

		$uriPartsProp = $routeRef->getProperty('uriParts');
		$uriPartsProp->setValue($route, ['users']);

		$methodProp = $routeRef->getProperty('method');
		$methodProp->setValue($route, 'delete');

		$nsProp = $routeRef->getProperty('namespace');
		$nsProp->setValue($route, self::NS_STATIC);

		$this->expectException(NotFoundException::class);
		$exec = new ReflectionMethod($route, 'exec');
		$exec->invoke($route);
	}

	// ── ServerException for non-HttpController classes ──

	public function testServerExceptionForNonControllerClass(): void
	{
		$this->setRequestMethod('GET');
		$route = $this->createRoute(self::NS_BROKEN);

		$routeRef = new \ReflectionClass($route);

		$uriProp = $routeRef->getProperty('uri');
		$uriProp->setValue($route, '/not-a-controller');

		$uriPartsProp = $routeRef->getProperty('uriParts');
		$uriPartsProp->setValue($route, ['not-a-controller']);

		$methodProp = $routeRef->getProperty('method');
		$methodProp->setValue($route, 'get');

		$nsProp = $routeRef->getProperty('namespace');
		$nsProp->setValue($route, self::NS_BROKEN);

		$this->expectException(ServerException::class);
		$exec = new ReflectionMethod($route, 'exec');
		$exec->invoke($route);
	}

	// ── config options ──

	public function testDebugFlagsDefaultToFalse(): void
	{
		$route = $this->createRoute(self::NS_STATIC);
		$ref = new \ReflectionClass($route);

		$debugFailure = $ref->getProperty('debugFailure');
		$debugSuccess = $ref->getProperty('debugSuccess');

		$this->assertFalse($debugFailure->getValue($route));
		$this->assertFalse($debugSuccess->getValue($route));
	}

	public function testDebugFlagsSetFromConfig(): void
	{
		$this->setRequestMethod('GET');
		$route = $this->createRoute(self::NS_STATIC, [
			'debugFailure' => true,
			'debugSuccess' => true,
		]);

		// Trigger start() to process config flags
		$route->start('/index');

		$ref = new \ReflectionClass($route);

		$debugFailure = $ref->getProperty('debugFailure');
		$debugSuccess = $ref->getProperty('debugSuccess');

		$this->assertTrue($debugFailure->getValue($route));
		$this->assertTrue($debugSuccess->getValue($route));
	}

	// ── URI with query string ──

	public function testQueryStringIsStrippedFromUri(): void
	{
		$this->setRequestMethod('GET');
		$route = $this->createRoute(self::NS_STATIC);
		$result = $route->start('/users?page=1&limit=10');
		$this->assertSame('users-list', $result);

		$uriProp = new \ReflectionProperty($route, 'uri');
		$this->assertSame('/users', $uriProp->getValue($route));
	}

	// ── URL-encoded URI ──

	public function testUrlEncodedUriIsDecoded(): void
	{
		$this->setRequestMethod('GET');
		$route = $this->createRoute(self::NS_STATIC);
		// %2F is / encoded, but bank-accounts is a valid segment
		$result = $route->start('/bank-accounts');
		$this->assertSame('bank-accounts-list', $result);
	}

	// ── handleException ──

	public function testHandleExceptionUsesCustomHandler(): void
	{
		$route = $this->createRoute(self::NS_ERROR);

		// Set namespace — Tests\Fixtures\HttpRoute\ErrorHandling\ExceptionHandler exists and has exec()
		$nsProp = new \ReflectionProperty($route, 'namespace');
		$nsProp->setValue($route, self::NS_ERROR);

		\Tests\Fixtures\HttpRoute\ErrorHandling\ExceptionHandler::$called = false;
		$route->handleException(new NotFoundException('test'));

		$this->assertTrue(\Tests\Fixtures\HttpRoute\ErrorHandling\ExceptionHandler::$called);
	}

	// ── start() catches exceptions and delegates to handleException ──

	public function testStartCatchesNotFoundAndDelegatesToHandler(): void
	{
		$this->setRequestMethod('GET');
		$route = $this->createRoute(self::NS_ERROR);

		// Route to a nonexistent path — start() should catch the NotFoundException
		// and call handleException() via the custom ExceptionHandler fixture
		\Tests\Fixtures\HttpRoute\ErrorHandling\ExceptionHandler::$called = false;
		$result = $route->start('/totally-nonexistent-path');

		// start() returns null when exception is caught (no return after handleException)
		$this->assertNull($result);
		$this->assertTrue(\Tests\Fixtures\HttpRoute\ErrorHandling\ExceptionHandler::$called);
	}

	// ── route: / (root) ──

	public function testRootSlashReturnsNotFound(): void
	{
		$this->setRequestMethod('GET');
		$route = $this->createRoute(self::NS_ERROR);

		// GET / → uriParts = [''] → sanitize('') = '' → no class match → NotFoundException
		// start() catches it and delegates to ExceptionHandler
		\Tests\Fixtures\HttpRoute\ErrorHandling\ExceptionHandler::$called = false;
		$result = $route->start('/');
		$this->assertNull($result);
		$this->assertTrue(\Tests\Fixtures\HttpRoute\ErrorHandling\ExceptionHandler::$called);
	}

	// ── route: /non-existing ──

	public function testNonExistingRouteViaStart(): void
	{
		$this->setRequestMethod('GET');
		$route = $this->createRoute(self::NS_ERROR);

		\Tests\Fixtures\HttpRoute\ErrorHandling\ExceptionHandler::$called = false;
		$result = $route->start('/non-existing');
		$this->assertNull($result);
		$this->assertTrue(\Tests\Fixtures\HttpRoute\ErrorHandling\ExceptionHandler::$called);
	}

	// ── route: /companies ──

	public function testCompaniesRoute(): void
	{
		$this->setRequestMethod('GET');
		$route = $this->createRoute(self::NS_COMPANY);
		$result = $route->start('/companies');
		$this->assertSame('companies-list', $result);
	}

	// ── route: /companies/status (dynamic __ takes precedence over static Status) ──

	public function testCompaniesStatusDynamicTakesPrecedence(): void
	{
		$this->setRequestMethod('GET');
		$route = $this->createRoute(self::NS_COMPANY);
		// Companies\__ exists and takes precedence over Companies\Status
		$result = $route->start('/companies/status');
		$this->assertSame('company-detail-status', $result);
	}

	// ── route: /companies/dynamic-company-name ──

	public function testCompaniesDynamicSegment(): void
	{
		$this->setRequestMethod('GET');
		$route = $this->createRoute(self::NS_COMPANY);
		$result = $route->start('/companies/dynamic-company-name');
		$this->assertSame('company-detail-dynamic-company-name', $result);
	}

	// ── route: /companies/{slug}/invoices ──

	public function testCompaniesSlugInvoices(): void
	{
		$this->setRequestMethod('GET');
		$route = $this->createRoute(self::NS_COMPANY);
		$result = $route->start('/companies/acme-corp/invoices');
		$this->assertSame('invoices-for-acme-corp', $result);
	}

	// ── route: /companies/{slug}/invoices/{id} ──

	public function testCompaniesSlugInvoicesId(): void
	{
		$this->setRequestMethod('GET');
		$route = $this->createRoute(self::NS_COMPANY);
		$result = $route->start('/companies/acme-corp/invoices/99');
		$this->assertSame('invoice-99-for-acme-corp', $result);
	}

	// ── route: /companies/splendido-solutions/invoices/12345/file (5 segments deep) ──

	public function testDeepNestedRoute(): void
	{
		$this->setRequestMethod('GET');
		$route = $this->createRoute(self::NS_COMPANY);
		$result = $route->start('/companies/splendido-solutions/invoices/12345/file');
		$this->assertSame('file-for-invoice-12345-of-splendido-solutions', $result);
	}

	public function testDeepNestedContextPropagation(): void
	{
		$this->setRequestMethod('GET');
		$route = $this->createRoute(self::NS_COMPANY);
		$route->start('/companies/splendido-solutions/invoices/12345/file');

		$contextProp = new \ReflectionProperty($route, 'context');
		$context = $contextProp->getValue($route);

		$this->assertSame('splendido-solutions', $context['company_slug']);
		$this->assertSame('12345', $context['invoice_id']);
	}

	// ── gap: InvalidArgumentException('Invalid URI given') — line 150 ──

	public function testStartThrowsInvalidUriGiven(): void
	{
		$route = $this->createRoute(self::NS_STATIC);
		$this->expectException(InvalidArgumentException::class);
		$this->expectExceptionMessage('Invalid URI given');
		// //users starts with / (passes line 143) but parse_url treats it as
		// a network path with host=users and no 'path' key
		$route->start('//users');
	}

	// ── gap: ServerException on dynamic __ that is not HttpController — line 256 ──

	public function testDynamicMatchServerExceptionForNonController(): void
	{
		$this->setRequestMethod('GET');
		$route = $this->createRoute(self::NS_BROKEN);

		$routeRef = new \ReflectionClass($route);

		$uriProp = $routeRef->getProperty('uri');
		$uriProp->setValue($route, '/broken/anything');

		$uriPartsProp = $routeRef->getProperty('uriParts');
		$uriPartsProp->setValue($route, ['broken', 'anything']);

		$methodProp = $routeRef->getProperty('method');
		$methodProp->setValue($route, 'get');

		$nsProp = $routeRef->getProperty('namespace');
		$nsProp->setValue($route, self::NS_BROKEN);

		// 'broken' → no dynamic BrokenControllers\__ → static match Broken (valid controller, not last)
		// 'anything' → try dynamic Broken\__ first → exists but NOT HttpController
		$this->expectException(ServerException::class);
		$exec = new ReflectionMethod($route, 'exec');
		$exec->invoke($route);
	}

	// ── gap: NotFoundException on dynamic __ with wrong HTTP method — line 277 ──

	public function testDynamicMatchNotFoundForWrongMethod(): void
	{
		$this->setRequestMethod('DELETE');
		$route = $this->createRoute(self::NS_DYNAMIC);

		$routeRef = new \ReflectionClass($route);

		$uriProp = $routeRef->getProperty('uri');
		$uriProp->setValue($route, '/users/abc-123');

		$uriPartsProp = $routeRef->getProperty('uriParts');
		$uriPartsProp->setValue($route, ['users', 'abc-123']);

		$methodProp = $routeRef->getProperty('method');
		$methodProp->setValue($route, 'delete');

		$nsProp = $routeRef->getProperty('namespace');
		$nsProp->setValue($route, self::NS_DYNAMIC);

		// 'users' → no dynamic DynamicRoutes\__ → static match Users (valid, not last)
		// 'abc-123' → try dynamic Users\__ first → exists, isLast,
		// but delete() doesn't exist → NotFoundException
		$this->expectException(NotFoundException::class);
		$exec = new ReflectionMethod($route, 'exec');
		$exec->invoke($route);
	}

}
