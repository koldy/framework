# Koldy PHP Framework — View & Templating

`Koldy\Response\View` is the framework's templating layer. There is no custom template DSL: a view file is a plain PHP file with a `.phtml` extension that is rendered through `ob_start` / `require` / `ob_get_clean`. You get includes, conditionals, loops, and data access with PHP itself — no parser, no compile cache, no new syntax to learn.

## View File Location & Config

All view files are resolved relative to the path defined by the `view` key inside the `paths` array passed to `Application::useConfig()`. If the key is omitted, it defaults to `<application_path>/views/`. Every file must end with `.phtml`.

```php
// public/index.php
Application::useConfig([
    'application_path' => __DIR__ . '/application',
    'storage_path'     => __DIR__ . '/storage',
    'public_path'      => __DIR__ . '/public',
    'paths' => [
        'view' => __DIR__ . '/application/views/',
    ],
    // ...
]);
```

You can read the resolved path at runtime:

```php
Application::getViewPath();                 // /.../application/views/
Application::getViewPath('pages/home.phtml'); // /.../application/views/pages/home.phtml
```

## Returning a View from a Controller

`View::create()` is the static factory. Data is attached with the fluent `Data` trait API (`set`, `setData`, `addData`).

```php
use Koldy\Response\View;

public function indexAction(): View
{
    return View::create('pages/home')
        ->set('title', 'Welcome')
        ->set('user', $user);
}
```

Passing an entire associative array at once:

```php
return View::create('pages/home')->setData([
    'title' => 'Welcome',
    'user'  => $user,
    'items' => $items,
]);
```

- `set(string $key, mixed $value): static` — set one value.
- `setData(array $data): static` — replace all data.
- `addData(array $data): static` — merge with existing data.
- `get(string $key): mixed` / `has(string $key): bool` / `delete(string $key): static`.

## Accessing Controller Data Inside a View

Inside a `.phtml` file, `$this` is the `View` instance, so every value attached in the controller is reachable as a property, thanks to the `Data` trait's `__get` magic.

```php
<!-- application/views/pages/home.phtml -->
<!doctype html>
<html>
<head><title><?= htmlspecialchars($this->title) ?></title></head>
<body>
    <h1>Hello, <?= htmlspecialchars($this->user->name) ?></h1>

    <?php if ($this->has('flashMessage')): ?>
        <div class="flash"><?= htmlspecialchars($this->flashMessage) ?></div>
    <?php endif; ?>

    <?php $this->printIf('footerNote'); // prints $this->footerNote if set ?>
</body>
</html>
```

Useful helpers:

- `$this->has('key')` — true if the key exists.
- `$this->get('key')` — value or `null`.
- `$this->printIf('key')` — echoes `$this->$key` when set, does nothing otherwise.

Always escape user data with `htmlspecialchars()` (or your own helper) before echoing into HTML.

## Embedding One View Inside Another

Use `$this->render()` from inside a `.phtml` file to include a partial. The return value is the rendered string — echo it.

```php
<!-- application/views/pages/home.phtml -->
<?= $this->render('partials/header') ?>

<main>
    <h1><?= htmlspecialchars($this->title) ?></h1>
</main>

<?= $this->render('partials/footer') ?>
```

Inside the partial, `$this` is still the outer `View` instance, so anything set on the outer view is available:

```php
<!-- application/views/partials/header.phtml -->
<header>
    <a href="/"><?= htmlspecialchars($this->siteName) ?></a>
</header>
```

When the partial is optional and you do not want to throw if the file is missing, use `renderViewIf()`:

```php
<?= $this->renderViewIf('partials/optional-banner') ?>
```

`renderViewIf()` returns an empty string when the file does not exist. `render()` throws `Koldy\Response\Exception` if the file is missing.

## Passing Local Variables to a Sub-View

The second argument of `render()` / `renderViewIf()` is an associative array. Each key is extracted as a **local variable** inside the sub-view (not a property of `$this`). Keys must be strings.

```php
<?= $this->render('partials/user-card', [
    'user'      => $user,
    'highlight' => true,
]) ?>
```

```php
<!-- application/views/partials/user-card.phtml -->
<div class="user-card<?= $highlight ? ' user-card--highlight' : '' ?>">
    <strong><?= htmlspecialchars($user->name) ?></strong>
    <span><?= htmlspecialchars($user->email) ?></span>
</div>
```

Inside `user-card.phtml`, `$user` and `$highlight` are plain local variables. Data attached to the outer `View` is still reachable as `$this->...` in the partial.

## Iterating Over an Array

Plain PHP. No special loop syntax.

```php
<!-- application/views/pages/users.phtml -->
<ul>
    <?php foreach ($this->users as $user): ?>
        <li><?= htmlspecialchars($user->name) ?></li>
    <?php endforeach; ?>
</ul>
```

## Iterating and Delegating Each Element to Its Own Sub-View

Combine the loop with `render()` to keep row markup in its own file:

```php
<!-- application/views/pages/users.phtml -->
<h1>Users</h1>

<ul class="user-list">
    <?php foreach ($this->users as $user): ?>
        <?= $this->render('partials/user-card', [
            'user'      => $user,
            'highlight' => $user->isAdmin,
        ]) ?>
    <?php endforeach; ?>
</ul>
```

```php
<!-- application/views/partials/user-card.phtml -->
<li class="user-card<?= $highlight ? ' is-admin' : '' ?>">
    <img src="<?= htmlspecialchars($user->avatar) ?>" alt="">
    <div>
        <strong><?= htmlspecialchars($user->name) ?></strong>
        <small><?= htmlspecialchars($user->email) ?></small>
    </div>
</li>
```

Each iteration renders `partials/user-card.phtml` with its own `$user` and `$highlight` locals. The partial has no knowledge of the outer loop — it only knows its inputs, which keeps partials reusable.

For a view name that is itself dynamic (e.g. stored on the controller), use `renderViewInKeyIf()`:

```php
// controller
return View::create('pages/dashboard')->set('widgetView', 'widgets/chart');

// pages/dashboard.phtml
<?= $this->renderViewInKeyIf('widgetView', ['data' => $this->chartData]) ?>
```

## Checking If a View Exists

```php
if (View::exists('partials/user-card')) {
    // ...
}
```

`exists()` is static and returns `true` when the resolved `.phtml` file is present.

## Custom View Roots

Override the base directory on a single `View` instance with `setViewPath()`. Useful for e-mail templates or anything that lives outside `application/views/`:

```php
$mail = View::create('welcome')
    ->setViewPath(Application::getStoragePath('mail-views'))
    ->set('user', $user);
```

## Rendering Without Flushing

Sometimes you want the rendered HTML as a string — for e-mail bodies, PDF generation, or embedding inside another response. Use `getOutput()` or cast the view to a string:

```php
$html = View::create('emails/welcome')
    ->set('user', $user)
    ->getOutput();

// equivalent
$html = (string) View::create('emails/welcome')->set('user', $user);
```

`getOutput()` does not send headers or flush to the client; it only renders.

## Response Lifecycle

`View` extends `AbstractResponse`, so the standard response API is available:

```php
return View::create('pages/home')
    ->set('title', 'Welcome')
    ->setHeader('X-Frame-Options', 'DENY')
    ->statusCode(200)
    ->before(function () {
        // runs immediately before output is flushed
    })
    ->after(function () {
        // runs after the response is sent to the client
    });
```

When `flush()` is called (by the framework, usually), the buffered HTML is measured and a `Content-Length` header is added automatically (except for `1xx` and `204` responses).

## Why No Template Engine

PHP is already a compiled, opcode-cached templating language. Any alternative syntax — Blade, Twig, Smarty — adds a parse/compile step, a learning curve, and hides nothing PHP cannot express directly. The `View` class gives you every feature a templating layer offers: includes, loops, conditionals, data access, output capture, and existence checks — with zero translation cost and no dependencies. Your editor already knows PHP; your profiler already understands PHP; there is no second language in the pipeline.
