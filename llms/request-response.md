# Koldy PHP Framework — Request & Response

## Request

`Koldy\Request` is a static facade wrapping `$_SERVER`, `$_GET`, `$_POST`, and `$_FILES`. It provides methods for IP detection, HTTP method inspection, parameter retrieval, and file uploads.

### IP & Network

```php
Request::ip();                 // real client IP (detects proxies)
Request::host();               // reverse DNS lookup
Request::hasProxy();           // detect proxy headers
Request::proxySignature();     // HTTP_VIA header
Request::ipWithProxy(',');     // "client_ip,proxy_ip"
Request::httpXForwardedFor();  // X-Forwarded-For header
Request::userAgent();          // User-Agent string
Request::httpReferer();        // Referer header
```

### HTTP Method Detection

```php
Request::method();    // "GET", "POST", etc.
Request::isGet();     // bool
Request::isPost();    // bool
Request::isPut();     // bool
Request::isDelete();  // bool
Request::isPatch();   // bool
Request::isHead();    // bool
Request::isOptions(); // bool
```

### URI

```php
Request::uri();              // full request URI
Request::uriSegment(0);     // first URI segment (for "/users/list" it will return "users")
Request::getCurrentURL();   // Url instance
```

### Parameter Retrieval

```php
// GET parameters
Request::getGetParameter('page');       // ?string
Request::hasGetParameter('page');       // bool
Request::getAllGetParameters();          // array

// POST parameters
Request::getPostParameter('email');     // ?string
Request::hasPostParameter('email');     // bool
Request::getAllPostParameters();         // array

// PUT parameters
Request::getPutParameter('name');       // ?string
Request::hasPutParameter('name');       // bool
Request::getAllPutParameters();          // array

// DELETE parameters
Request::getDeleteParameter('id');      // ?string
Request::hasDeleteParameter('id');      // bool

// Raw body
Request::getRawData();        // raw request body as string
Request::getDataFromJSON();   // parse JSON body to array

// Universal — handles JSON, multipart, form-data automatically
Request::getAllParameters();      // array
Request::getAllParametersObj();   // stdClass

// Require specific parameters (throws BadRequestException if missing)
$params = Request::requireParams('email', 'password');        // array
$params = Request::requireParamsObj('email', 'password');     // stdClass
```

### Parameter Validation

```php
Request::parametersCount();                      // int
Request::containsParams('email', 'password');    // has all listed params
Request::only('email', 'password');              // has exactly these params, no extras
Request::doesntContainParams('admin', 'role');   // missing all listed params
```

### File Uploads

```php
$files = Request::getAllFiles();  // UploadedFile[]
```

`UploadedFile` provides methods for accessing uploaded file info (name, size, tmp path, error).

---

## Response Types

All response classes extend `Koldy\Response\AbstractResponse`.

### Common Methods (AbstractResponse)

```php
$response->setHeader('X-Custom', 'value');
$response->hasHeader('X-Custom');           // bool
$response->removeHeader('X-Custom');
$response->removeHeaders();                 // clear all
$response->getHeader('X-Custom');           // string

$response->setStatusCode(201);
$response->getStatusCode();                 // int

// Pre/post flush callbacks
$response->workBeforeResponse(fn() => /* ... */, 'name');
$response->workAfterResponse(fn() => /* ... */, 'name');

$response->flush();         // send response to client
(string) $response;         // get response body as string
```

### Json

JSON response with automatic `Content-Type: application/json` header.

```php
use Koldy\Response\Json;

return Json::create(['status' => 'ok', 'user' => $user]);
```

Uses the `Data` trait, so you can set key-value pairs:

```php
$json = new Json();
$json->set('status', 'ok');
$json->set('users', $userList);
return $json;
```

### View

PHP started as template engine, and it's still the best one, so we didn't re-invent the wheel. Simply make PHP files with "phtml", store them in application/views/ and use them.

Renders a PHP view file with data passed as variables.

```php
use Koldy\Response\View;

return View::create('pages/home', ['title' => 'Welcome']); // /application/views/pages/home.phtml
```

### Plain

Plain text response.

```php
use Koldy\Response\Plain;

return Plain::create('Hello, World!');
```

### Redirect

HTTP redirect (301 or 302).

```php
use Koldy\Response\Redirect;

return Redirect::to('/dashboard');
return Redirect::permanent('/new-url');  // 301
```

### FileDownload

Download a file from the filesystem.

```php
use Koldy\Response\FileDownload;

return FileDownload::create('/path/to/file.pdf');
```

### ContentDownload

Download dynamically generated content.

```php
use Koldy\Response\ContentDownload;

return ContentDownload::create('report.csv', $csvContent);
```

### ResponseExceptionHandler

Default exception handler that renders appropriate error responses. Custom exception handlers can extend this class.

---

## HTTP Exception Responses

The framework provides exception classes under `Koldy\Response\Exception\` that automatically set the correct HTTP status code:

| Exception | Status Code |
|-----------|-------------|
| `BadRequestException` | 400 |
| `UnauthorizedException` | 401 |
| `ForbiddenException` | 403 |
| `NotFoundException` | 404 |
| `MethodNotAllowedException` | 405 |
| `ServerException` | 500 |

Throw these from controllers and the exception handler will render the appropriate response:

```php
use Koldy\Response\Exception\NotFoundException;

throw new NotFoundException('User not found');
```
