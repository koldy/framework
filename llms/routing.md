# Koldy PHP Framework — Routing

Koldy provides two routing systems. Both extend `Koldy\Route\AbstractRoute`.

## HttpRoute (Recommended)

`Koldy\Route\HttpRoute` is a filesystem-based HTTP router that maps URI segments to namespaced PHP classes using PSR-4 autoloading. Each URI segment maps to a class, and the final class handles HTTP methods (`get()`, `post()`, `patch()`, `delete()`, etc.). This router supports dynamic and static matching, and supports context propagation through the request chain.

### Configuration

```php
// In application config
'routing_class' => \Koldy\Route\HttpRoute::class,
'routing_options' => [
    'namespace' => 'App\\Http\\',   // required — root namespace for handler classes
    'debugFailure' => true,          // optional — log why route resolution fails
    'debugSuccess' => true           // optional — log successful route resolution steps
]
```

### How Routing Works

URI segments are processed left-to-right. Each segment is sanitized (slugified, then PascalCased) and resolved to a class in the namespace tree.

Example: `GET /companies/splendido-solutions/invoices`

1. `companies` — resolves to `App\Http\Companies` (static match)
2. `splendido-solutions` — resolves to `App\Http\Companies\__` (dynamic match) - opportunity to load company and put it in context so it can be used in next controller
3. `invoices` — resolves to `App\Http\Companies\__\Invoices` → `get()` is called

### Segment Sanitization

Segments are converted to class names: slugified, double dashes collapsed, dashes replaced with spaces, ucwords applied, spaces removed.

- `bank-accounts` → `BankAccounts`
- `my-awesome-page` → `MyAwesomePage`

### Dynamic Matching (`__`)

A class named `__` (double underscore) acts as a wildcard catch-all for any segment value. The raw segment is available via `$this->segment` in the `__` controller.

Dynamic matches **always take precedence** over static matches. If both `__` and a named class exist at the same namespace level, `__` wins. This approach allows you to have a catch-all controller for a given level of URI hierarchy but also makes it clear so you don't mix static and dynamic controllers at the same level.

```php
// App\Http\Companies\__.php
namespace App\Http\Companies;

use Koldy\Route\HttpRoute\HttpController;

class __ extends HttpController
{
    public function __construct(array $data)
    {
        parent::__construct($data);
        // $this->segment contains the raw URI segment (e.g. "splendido-solutions")
        // Load the company and pass it via context
        $this->context['company'] = Company::fetchOneOrFail(['slug' => $this->segment]);
    }
}
```

### Static Matching

If no `__` class exists, the router looks for a class whose PascalCased name matches the segment.

```php
// App\Http\Companies.php — handles /companies
namespace App\Http;

use Koldy\Route\HttpRoute\HttpController;
use Koldy\Response\Json;

class Companies extends HttpController
{
    public function get(): Json
    {
        return Json::create(['companies' => Company::fetch()]);
    }

    public function post(): Json
    {
        // Handle POST /companies
        return Json::create(['created' => true]);
    }
}
```

### Context Propagation

Each controller in the chain receives a `$context` array. Controllers enrich the context by adding data, and the next controller receives the updated version. This enables deep URI structures to accumulate state without global variables.

```php
// App\Http\Companies\__\Invoices.php — handles /companies/{slug}/invoices
namespace App\Http\Companies\__;

use Koldy\Route\HttpRoute\HttpController;
use Koldy\Response\Json;

class Invoices extends HttpController
{
    public function get(): Json
    {
        // $this->context['company'] was set by the __ controller above
        $company = $this->context['company'];
        return Json::create(['invoices' => $company->getInvoices()]);
    }
}
```

### HttpController Base Class

All HttpRoute controllers must extend `Koldy\Route\HttpRoute\HttpController`:

```php
abstract class HttpController
{
    public array $context;        // accumulated context from parent controllers
    public string|null $segment;  // raw URI segment that matched this controller

    public function __construct(array $data);

    // Execute a callback only once per request cycle
    protected function once(string $name, callable $callback): void;
}
```

The `once()` method is useful when abstract controllers in the inheritance chain might otherwise run shared logic multiple times (e.g. authentication checks).

For example, you don't want to do "authentication" on every route, so you make a class like "abstract AuthenticatedUsers extends HttpController" and in constructor, you should then call `$this->once('named-code', function() { ... })`, because if multiple controllers extends that abstract controller, then that code will be executed multiple times, but with `once()` method, it will be executed only once. Example:

```php
abstract class AuthenticatedUsers extends HttpController
{
    protected User $user;

    public function __construct(array $data)
    {
        parent::__construct($data);
        $this->once('authenticate-user', function() {
            // ... authentication logic
            // and then you probably want to remember it
            $this->user = $user;
        });
    }
}

class Companies extends AuthenticatedUsers
{
    public function get(): Json
    {
        // $this->user is authenticated user and it's available in all child classes
        return Json::create(['companies' => $this->user->getCompanies()]);
    }
}
```

### Exception Handling

If an `ExceptionHandler` class exists in the configured root namespace (e.g. `App\Http\ExceptionHandler`) with an `exec()` method, it handles exceptions. Otherwise, the framework's `ResponseExceptionHandler` is used.

### Trailing Slash Behavior

GET/HEAD requests with a trailing slash are 301-redirected to the canonical URL without the trailing slash.

---

## DefaultRoute (Legacy - not recommended)

`Koldy\Route\DefaultRoute` maps URLs to controllers using the pattern:

```
/[module]/[controller]/[action]/[param1]/[param2]/...
```

or without modules:

```
/[controller]/[action]/[param1]/[param2]/...
```

### Configuration

```php
'routing_class' => \Koldy\Route\DefaultRoute::class,
'routing_options' => [
    'always_restful' => false  // optional — force RESTful method naming
]
```

### Controller Resolution

- `/` → `IndexController::indexAction()`
- `/users` → `UsersController::indexAction()`
- `/users/login` → `UsersController::loginAction()`
- `/users/show-details/5` → `UsersController::showDetailsAction()` with `getVar(0)` returning `"5"`

### Action Method Naming

The action method name depends on request type:

| Request Type | Method Pattern | Example |
|-------------|---------------|---------|
| Regular GET | `{action}Action()` | `loginAction()` |
| AJAX POST | `{action}Ajax()` | `loginAjax()` |
| RESTful | `{httpMethod}{Action}()` | `getLogin()`, `postLogin()` |

RESTful mode is enabled per-controller via `public static $restful = true;` or globally via `'always_restful' => true` in config.

### Module Support

If the first URL segment matches a registered module directory, routing shifts:

- `/admin/users/edit/5` → Module `admin`, `UsersController::editAction()`, `getVar(0)` = `"5"`

### URL Parameters

Access positional URL parameters via `getVar()`:

```php
$route = Application::route();
$id = $route->getVar(0);    // first parameter after action
$slug = $route->getVar(1);  // second parameter
```

### Link Generation

```php
$route = Application::route();
$url = $route->href('users', 'edit', ['id' => 5]);
// → https://example.com/users/edit?id=5

$url = $route->siteHref('api', 'users', 'list');
// → https://api.example.com/users/list (using sites config)
```
