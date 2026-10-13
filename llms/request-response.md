# Koldy PHP Framework — Request & Response

## Request

`Koldy\Request` is a static facade wrapping `$_SERVER`, `$_GET`, `$_POST`, and `$_FILES`. It provides methods for IP detection, HTTP method inspection, parameter retrieval, and file uploads.

### IP & Network

```php
Request::ip();                         // real client IP (see Trusted Proxies below)
Request::host();                       // reverse DNS lookup
Request::hasProxy();                   // detect proxy headers
Request::proxySignature();             // HTTP_VIA header
Request::ipWithProxy(',');             // "client_ip,proxy_ip"
Request::httpXForwardedFor();          // X-Forwarded-For header
Request::userAgent();                  // User-Agent string
Request::httpReferer();                // Referer header

// Trusted proxy allowlist (see full section below)
Request::setTrustedProxies(['10.0.0.1', '10.0.0.0/8']);
Request::getTrustedProxies();          // string[]|null
```

### Trusted Proxies

How `Request::ip()` resolves the client address depends on whether a trusted
proxy allowlist (`trusted_proxies`) is configured. Configure one whenever the
app runs behind a load balancer or reverse proxy.

#### With an allowlist (recommended)

1. If `REMOTE_ADDR` is **not** on the list, it is returned as-is and every
   forwarded header is ignored.
2. Otherwise only `X-Forwarded-For` is read. Each proxy *appends* the address
   it received the request from, so the left part of the header is whatever the
   client sent and only the right end can be trusted. `ip()` builds the hop
   chain `[X-Forwarded-For entries…, REMOTE_ADDR]`, walks it **from the right**,
   skips every hop on the allowlist and returns the first hop that is not on it:
   the **right-most untrusted hop**, i.e. the address the outermost trusted
   proxy actually saw.
3. If every hop is trusted, the leftmost entry is returned.
4. Private and reserved addresses are returned like any other, so a VPN or
   intranet client gets its own `192.168.x.x` / `10.x.x.x` address, not the
   proxy's. List only your proxies: a client inside a trusted range is skipped
   as if it were a proxy.
5. Every entry is trimmed and validated as an IP. A malformed entry (including
   an empty one, as in `a,,b`) stops the walk because nothing to its left can be
   trusted. The hop to its right is returned (`REMOTE_ADDR` when the malformed
   entry is the last one), never the malformed value.

```
trusted_proxies:  ['10.0.0.0/16']
REMOTE_ADDR:      10.0.1.7                   (load balancer node)
X-Forwarded-For:  6.6.6.6, 203.0.113.9       (client sent "6.6.6.6", the LB appended the real address)

hop chain:        6.6.6.6    203.0.113.9    10.0.1.7
walk ←                       untrusted      trusted → skip
ip()              = 203.0.113.9
```

Once an allowlist is set, these headers are **ignored**:

| Header | `$_SERVER` key | Why |
|---|---|---|
| `Client-IP` | `HTTP_CLIENT_IP` | not set by any standard proxy, client-controlled |
| `X-Forwarded` | `HTTP_X_FORWARDED` | not set by any standard proxy, client-controlled |
| `X-Cluster-Client-IP` | `HTTP_X_CLUSTER_CLIENT_IP` | not set by any standard proxy, client-controlled |
| `Forwarded-For` | `HTTP_FORWARDED_FOR` | not set by any standard proxy, client-controlled |
| `Forwarded` (RFC 7239) | `HTTP_FORWARDED` | not parsed, see below |

RFC 7239 `Forwarded` is not supported. If your proxy only emits `Forwarded`,
configure it to append `X-Forwarded-For` as well. Using `Forwarded` only as a
fallback when `X-Forwarded-For` is missing would let a client override such a
proxy by sending its own `X-Forwarded-For`.

#### Without an allowlist (legacy default)

With no `trusted_proxies` key (`getTrustedProxies()` returns `null`), `ip()`
keeps its original behaviour: it returns the first public IP found in
`Client-IP`, `X-Forwarded-For`, `X-Forwarded`, `X-Cluster-Client-IP`,
`Forwarded-For` or `Forwarded`, and falls back to `REMOTE_ADDR`.

> **Warning:** every one of those headers can be set by the client, so in this
> mode anyone can choose the IP your app logs, rate-limits and audits. It is
> only safe when the app is never reachable except through a proxy that
> **overwrites** (not appends to) all of them. Configure `trusted_proxies`
> instead.

An empty allowlist (`[]`) means "no proxy is trusted": `ip()` always returns
`REMOTE_ADDR`.

#### Option 1 — application config (recommended)

Add a `trusted_proxies` key to `configs/application.php`. The value is picked
up automatically on the first call to `Request::ip()` or
`Request::getTrustedProxies()` — no bootstrap code required:

```php
// configs/application.php
return [
    'key'             => '...',
    'timezone'        => 'UTC',
    // ...
    'trusted_proxies' => [
        '10.0.0.1',          // exact IP
        '10.0.0.0/8',        // IPv4 CIDR range
        '192.168.1.0/24',
        '2001:db8::/32',     // IPv6 CIDR range
    ],
];
```

#### Option 2 — explicit call at bootstrap

```php
// public/index.php, after Application::init() / Application::run()
use Koldy\Request;

Request::setTrustedProxies([
    '10.0.0.1',
    '192.168.1.0/24',
]);
```

An explicit `setTrustedProxies()` call always wins over the config key, and
calling it also clears the cached IP so the next `Request::ip()` call
re-evaluates.

#### Example — AWS ALB + nginx

An Application Load Balancer appends the client address to `X-Forwarded-For`
and connects from node IPs that change as it scales, so list the **subnets the
ALB lives in** (or the whole VPC CIDR) rather than individual IPs. With nginx
and PHP-FPM on the same instance, `include fastcgi_params;` already sets
`REMOTE_ADDR` to the ALB node and passes `X-Forwarded-For` through unchanged,
so the subnet is all you need:

```php
// configs/application.php
'trusted_proxies' => [
    '10.0.0.0/16',   // VPC / ALB subnets
],
```

If nginx runs as a separate reverse proxy (ALB → nginx → app server), make it
append to the header, and add its address too unless it is already inside a
listed range:

```nginx
location / {
    proxy_pass http://app_backend;
    proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
}
```

```php
'trusted_proxies' => [
    '10.0.0.0/16',   // ALB subnets
    '172.31.5.20',   // nginx reverse proxy
],
```

#### Matching rules

- Exact IPs and CIDR ranges are supported for both IPv4 and IPv6.
- Addresses are compared in binary form, so `::1` matches `0:0:0:0:0:0:0:1`.
- An IPv4 entry never matches an IPv6 address and vice versa. That includes
  IPv4-mapped addresses: `::ffff:10.0.0.1` does not match `10.0.0.0/8`.
- Malformed entries, and CIDR entries with an invalid prefix length
  (`10.0.0.0/33`, `10.0.0.0/abc`), are skipped.

#### Reading the current list

```php
$proxies = Request::getTrustedProxies(); // string[]|null
// null  → no allowlist configured, all proxy headers are trusted (legacy, spoofable)
// []    → empty allowlist, proxy headers are never trusted
// [...] → only connections from listed IPs/CIDRs are trusted; X-Forwarded-For is walked from the right
```

#### Behaviour summary

| REMOTE_ADDR | `trusted_proxies` | X-Forwarded-For | ip() returns |
|---|---|---|---|
| any | not set (null) | any | first public IP from any proxy header, else REMOTE_ADDR (**spoofable**) |
| any | `[]` | any | REMOTE_ADDR |
| **not** in allowlist | `[...]` | any | REMOTE_ADDR (all headers ignored) |
| in allowlist | `[...]` | absent | REMOTE_ADDR |
| in allowlist | `[...]` | present | right-most hop not in the allowlist (leftmost entry if every hop is trusted) |

#### Subclassing

`isTrustedProxy(string $ip): bool` is `protected`, so a subclass can replace
the matching logic entirely:

```php
class MyRequest extends \Koldy\Request
{
    protected static function isTrustedProxy(string $ip): bool
    {
        // custom logic — e.g. look up a database allowlist
        return MyAllowlist::contains($ip);
    }
}
```

`ip()` calls `isTrustedProxy()` for `REMOTE_ADDR` and for every
`X-Forwarded-For` hop. The right-to-left walk only runs when
`getTrustedProxies()` returns an array, though. With `null`, `ip()` keeps the
legacy behaviour. A subclass with its own matching should therefore also set
`trusted_proxies` (any array, even `[]`) to opt in.

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

`UploadedFile` provides methods for accessing uploaded file info:

```php
$file->getName();          // original filename as sent by the browser
$file->getSize();          // file size in bytes
$file->getExtension();     // extension derived from the original filename
$file->hasError();         // bool — true if the upload failed

// MIME type — three methods with different trust levels:
$file->getDetectedMimeType();  // string|null — server-detected via mime_content_type();
                                //   returns null when detection is unavailable or fails.
                                //   Use this for any security-sensitive file type check.

$file->getMimeType();           // string — server-detected when possible, falls back to the
                                //   browser-declared value if mime_content_type() is
                                //   unavailable or fails. Do NOT rely on this for security.

$file->getClientMimeType();     // string — the raw value from $_FILES['type'], fully
                                //   attacker-controlled. Never use for security decisions.
```

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

Renders a PHP view file with data attached via the fluent `Data` trait API.

```php
use Koldy\Response\View;

return View::create('pages/home')->set('title', 'Welcome'); // /application/views/pages/home.phtml
```

**More docs:** see [`view.md`](view.md) for the full templating guide — passing data, sub-views, iteration, custom view roots, and rendering without flushing.

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
