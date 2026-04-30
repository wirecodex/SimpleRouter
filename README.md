# SimpleRouter

 **Alpha — v0.1.0.** This module is in early testing. The API may change before a stable release. Feedback and bug reports are welcome.

A URL segment router for ProcessWire that replaces complex if/elseif chains with clean, expressive route definitions. Supports named parameters, type aliases, regex constraints, optional segments, wildcards, and HTTP method matching — all wired directly into ProcessWire's template system via page hooks.

The Router lives at `SimpleWire\Router\Router` and can be installed standalone or as part of the **SimpleWire** suite.

## Features

- **Explicit HTTP Methods:** `GET`, `POST`, `PUT`, `PATCH`, `DELETE` with clean `method:path` syntax
- **Named Parameters:** Extract URL segments into named variables passed to your handler
- **Type Aliases:** Built-in shortcuts for common patterns (`integer`, `slug`, `uuid`, `date`, etc.)
- **Custom Regex Constraints:** Inline regex for full control over segment matching
- **Mixed Content Segments:** Patterns like `v{major}` or `file.{ext}` extract embedded values
- **Optional Segments:** `[{version}]` matches with or without the segment
- **Wildcard Routes:** `{*}` captures all remaining segments into an array
- **Named and Simple Options:** `(planet:earth|mars|jupiter)` for fixed-value segments
- **Multi-method Registration:** Register a route for multiple HTTP methods in one call
- **Per-template Caching:** Routes compiled to PHP cache files for production performance
- **404 Control:** Choose between middleware mode (return null) or full takeover (throw 404)
- **Custom 404 Handler:** Define your own not-found response per template

## Installation

Install **SimpleRouter** through ProcessWire Admin → Modules → Site → SimpleRouter.

The `route()`, `router()`, and `simplerouter()` global functions are available automatically after installation.

**Prerequisite:** URL segments must be enabled for any template where you use the router. Check the template settings: Admin → Setup → Templates → your template → URLs tab → Allow URL segments.

---

## Quick Access

```php
// Via the shorthand function (recommended)
$router = simplerouter();

// Via the global router() helper
$router = router();

// Via the ProcessWire API variable
$router = wire()->simplerouter;
```

---

## Defining Routes

### Method:Path Format

The simplest way to register a route from inside a template is the `$page->route()` hook. Pass a `"method:path"` string and a handler:

```php
$page->route("get:users",           $handler); // GET  /users
$page->route("post:users",          $handler); // POST /users
$page->route("put:users/{id}",      $handler); // PUT  /users/{id}
$page->route("delete:users/{id}",   $handler); // DELETE /users/{id}
```

If no method prefix is given, `GET` is assumed:

```php
$page->route("hello", $handler); // Same as "get:hello"
```

### Global `route()` Function

The `route()` function is equivalent and can be used anywhere in a template:

```php
route("get:users",      $handler);
route("post:users",     $handler);
route("delete:users/{id}", $handler);
```

It returns the `Router` instance for chaining:

```php
route("get:users", $handlerA)->get("products", $handlerB);
```

### Router Methods Directly

You can also call methods on the `Router` instance directly:

```php
$router = simplerouter();

$router->get("users",          $handler);
$router->post("users",         $handler);
$router->put("users/{id}",     $handler);
$router->patch("users/{id}",   $handler);
$router->delete("users/{id}",  $handler);
$router->any("users/{id}",     $handler); // GET | POST | PUT | PATCH | DELETE
```

`add()` is the underlying method — it accepts pipe-separated methods for multi-method registration:

```php
$router->add('GET|POST', 'contact', $handler);
```

---

## Route Patterns

### Literal Segments

Exact match, no parameters:

```php
route("get:hello/world", function() {
    return "Hello World!";
});
```

### Named Parameters

Capture any URL segment by wrapping the name in `{ }`:

```php
route("get:users/{id}", function($id) {
    // GET /users/42  →  $id = '42'
});

route("get:products/{category}/{slug}", function($category, $slug) {
    // GET /products/electronics/laptop-pro
});
```

### Type Aliases

Constrain parameters to a specific pattern using `{name<alias>}`:

```php
route("get:users/{id<integer>}",     $handler); // digits only
route("get:products/{slug<slug>}",   $handler); // URL-safe string
route("get:resources/{uid<uuid>}",   $handler); // UUID format
route("get:archive/{date<date>}",    $handler); // YYYY-MM-DD
route("get:year/{y<year>}",          $handler); // four-digit year
```

| Alias | Matches | Example |
| --- | --- | --- |
| `integer` | Positive integers | `1`, `123`, `9999` |
| `float` | Decimals with required dot | `3.14`, `0.5`, `123.456` |
| `number` | Any numeric value | `5`, `3.14`, `0.1` |
| `alpha` | Letters only | `abc`, `Hello`, `xyz` |
| `alphanumeric` | Letters and digits | `user123`, `abc99` |
| `unicode` | International letters | `José`, `Björk`, `北京` |
| `slug` | URL-friendly strings | `my-blog-post`, `product_name` |
| `uuid` | Standard UUID | `550e8400-e29b-41d4-a716-446655440000` |
| `date` | ISO date YYYY-MM-DD | `2024-12-25` |
| `year` | Four-digit year | `2024`, `1999`, `2030` |
| `month` | Month 01–12 | `01`, `06`, `12` |
| `day` | Day 01–31 | `01`, `15`, `31` |

An unrecognized alias name will never match — a warning to catch typos.

### Custom Regex Constraints

Use inline regex with `{name:pattern}` for full control:

```php
route("get:users/{id:[0-9]+}",        $handler); // digits only
route("get:posts/{slug:[a-z0-9\-]+}", $handler); // lowercase alphanumeric + hyphens
route("get:version/{v:v[0-9]+}",      $handler); // v1, v2, v10
```

### Mixed Content Segments

Embed a parameter inside a literal segment using `prefix{name}suffix`:

```php
route("get:hello/great-{planet}", function($planet) {
    // GET /hello/great-mars  →  $planet = 'mars'
});

route("get:files/{name}.{ext}", function($name, $ext) {
    // GET /files/report.pdf  →  $name = 'report', $ext = 'pdf'
});

route("get:api/v{major}", function($major) {
    // GET /api/v2  →  $major = '2'
});
```

### Optional Segments

Wrap a segment in `[ ]` to make it optional:

```php
route("get:api/[{version}]", function($version = 'v1') {
    // Matches both /api  and  /api/v2
});
```

Multiple optional segments are allowed:

```php
route("get:archive/[{year}]/[{month}]", function($year = null, $month = null) {
    // Matches /archive, /archive/2024, /archive/2024/06
});
```

### Wildcard Routes

`{*}` captures all remaining URL segments into a `$tail` array:

```php
route("get:files/{*}", function($tail) {
    // GET /files/docs/2024/report.pdf
    // $tail = ['docs', '2024', 'report.pdf']
    $path = implode('/', $tail);
    return serveFile($path);
});
```

The wildcard must be the last segment in the pattern.

### Named Options

Match one of a fixed set of values and capture it by name:

```php
route("get:hello/(planet:earth|mars|jupiter)", function($planet) {
    // GET /hello/mars  →  $planet = 'mars'
    // GET /hello/venus → no match
});
```

### Simple Options

Match one of a fixed set without a named capture:

```php
route("get:hello/(earth|mars|jupiter)", function($match) {
    // $match is the matched value, passed positionally
});
```

---

## Dispatching Routes

Call `$page->dispatchRoutes()` after all route definitions. It runs the router and returns the handler's return value, or `null` if no route matched:

```php
$result = $page->dispatchRoutes();

if ($result !== null) {
    echo $result;
} else {
    // No route matched — fall through to normal template rendering
}
```

You can also call `dispatch()` directly on the Router instance:

```php
$result = simplerouter()->dispatch();
```

---

## 404 Handling

### handle404 = false (default — middleware mode)

The router returns `null` for unmatched requests. Your template decides what to do:

```php
$result = $page->dispatchRoutes();

if ($result !== null) {
    echo $result;
} else {
    // Render the page normally, or throw 404, or do anything else
}
```

This is the best mode when mixing routed and non-routed content on the same template.

### handle404 = true (full takeover mode)

The router automatically throws `Wire404Exception` for unmatched requests. Useful for pure API templates where every request must match a defined route:

```php
// All routes defined above
$result = $page->dispatchRoutes(); // throws Wire404 if nothing matched
echo $result;
```

### Custom Not-Found Handler

Define a custom response for unmatched routes. When `handle404 = true`, this fires instead of throwing an exception:

```php
simplerouter()->setNotFoundHandler(function() {
    http_response_code(404);
    header('Content-Type: application/json');
    return json_encode(['error' => 'Route not found']);
});
```

---

## Template Usage Patterns

### Minimal Template

```php
<?php
// /site/templates/products.php
namespace ProcessWire;

route("get:detail/{id<integer>}", function($id) use ($page) {
    $product = wire()->pages->get($id);
    if (!$product->id) throw new Wire404Exception();
    return $product->render();
});

$result = $page->dispatchRoutes();

if ($result !== null) {
    echo $result;
} else {
    // Default: render the page normally
    echo "<h1>{$page->title}</h1>";
}
```

### Pure API Template

```php
<?php
// /site/templates/api.php
namespace ProcessWire;

simplerouter()->setNotFoundHandler(function() {
    http_response_code(404);
    header('Content-Type: application/json');
    return json_encode(['error' => 'Endpoint not found']);
});

route("get:products", function() {
    $pages = wire()->pages->find("template=product, limit=50");
    $data  = [];
    foreach ($pages as $p) {
        $data[] = ['id' => $p->id, 'title' => $p->title, 'price' => $p->price];
    }
    header('Content-Type: application/json');
    return json_encode($data);
});

route("get:products/{id<integer>}", function($id) {
    $product = wire()->pages->get($id);
    if (!$product->id) {
        http_response_code(404);
        header('Content-Type: application/json');
        return json_encode(['error' => 'Product not found']);
    }
    header('Content-Type: application/json');
    return json_encode(['id' => $product->id, 'title' => $product->title]);
});

route("post:products", function() {
    $body = json_decode(file_get_contents('php://input'), true);
    // ... create product
    http_response_code(201);
    header('Content-Type: application/json');
    return json_encode(['success' => true, 'id' => $newPage->id]);
});

// handle404 = true in module config, or:
$result = $page->dispatchRoutes();
if ($result === null) throw new Wire404Exception();
echo $result;
```

---

## Module Configuration

Navigate to Admin → Modules → Site → SimpleRouter → Configure.

| Setting | Default | Description |
| --- | --- | --- |
| **Enable Router Cache** | `true` | Compile route patterns to PHP cache files |
| **Cache TTL (seconds)** | `3600` | How long cache files are considered valid |
| **Router handles 404** | `false` | When checked: unmatched routes throw Wire404Exception |

### Per-template Cache Files

Routes are cached per template under the ProcessWire cache directory:

```
/site/assets/cache/SimpleWire/Router/product.cache.php
/site/assets/cache/SimpleWire/Router/api.cache.php
/site/assets/cache/SimpleWire/Router/blog.cache.php
```

Cache files are verified with a SHA1 hash file alongside them. A mismatch or expired TTL causes the cache to be rebuilt automatically on the next request.

---

## Complete Examples

### E-commerce Product Template

```php
<?php
// /site/templates/products.php
namespace ProcessWire;

// Category listing
route("get:category/{name<slug>}", function($name) {
    $products = wire()->pages->find("template=product, category.name=$name, limit=24");
    if (!$products->count()) throw new Wire404Exception();
    return wire()->files->render(wire()->config->paths->templates . 'partials/product-list.php', [
        'products' => $products,
        'category' => $name,
    ]);
});

// Product detail by integer ID
route("get:detail/{id<integer>}", function($id) {
    $product = wire()->pages->get("id=$id, template=product");
    if (!$product->id) throw new Wire404Exception();
    return wire()->files->render(wire()->config->paths->templates . 'partials/product-detail.php', [
        'product' => $product,
    ]);
});

// Product search via POST
route("post:search", function() {
    $q       = wire()->sanitizer->text(wire()->input->post('q'));
    $results = wire()->pages->find("template=product, title~=$q, limit=20");
    header('Content-Type: application/json');
    $data = [];
    foreach ($results as $p) {
        $data[] = ['id' => $p->id, 'title' => $p->title, 'url' => $p->url];
    }
    return json_encode(['results' => $data, 'count' => count($data)]);
});

$result = $page->dispatchRoutes();

if ($result !== null) {
    echo $result;
} else {
    // Default product index
    echo "<h1>{$page->title}</h1>";
    echo "<div>{$page->body}</div>";
}
```

### RESTful API Template

```php
<?php
// /site/templates/api.php
namespace ProcessWire;

simplerouter()->setNotFoundHandler(function() {
    http_response_code(404);
    header('Content-Type: application/json');
    return json_encode(['error' => 'Endpoint not found']);
});

// GET /api/users
route("get:users", function() {
    $users = wire()->users->find("roles=member, limit=50");
    $data  = [];
    foreach ($users as $u) {
        $data[] = ['id' => $u->id, 'name' => $u->name, 'email' => $u->email];
    }
    header('Content-Type: application/json');
    return json_encode($data);
});

// GET /api/users/42
route("get:users/{id<integer>}", function($id) {
    $user = wire()->users->get((int)$id);
    if (!$user->id) {
        http_response_code(404);
        header('Content-Type: application/json');
        return json_encode(['error' => 'User not found']);
    }
    header('Content-Type: application/json');
    return json_encode(['id' => $user->id, 'name' => $user->name, 'email' => $user->email]);
});

// POST /api/users  (JSON body: {"name":"...", "email":"..."})
route("post:users", function() {
    $san  = wire()->sanitizer;
    $body = json_decode(file_get_contents('php://input'), true) ?? [];
    $name  = $san->pageName($body['name'] ?? '');
    $email = $san->email($body['email'] ?? '');

    if (!$name || !$email) {
        http_response_code(422);
        header('Content-Type: application/json');
        return json_encode(['error' => 'name and email are required']);
    }

    $user = new User();
    $user->name  = $name;
    $user->email = $email;
    $user->addRole('member');
    $user->save();

    http_response_code(201);
    header('Content-Type: application/json');
    return json_encode(['id' => $user->id, 'name' => $user->name]);
});

// DELETE /api/users/42
route("delete:users/{id<integer>}", function($id) {
    $user = wire()->users->get((int)$id);
    if (!$user->id) {
        http_response_code(404);
        header('Content-Type: application/json');
        return json_encode(['error' => 'User not found']);
    }
    wire()->users->delete($user);
    header('Content-Type: application/json');
    return json_encode(['success' => true]);
});

$result = $page->dispatchRoutes();
if ($result === null) throw new Wire404Exception();
echo $result;
```

### Blog Template with Optional Segments

```php
<?php
// /site/templates/blog.php
namespace ProcessWire;

// All posts, or filtered by year, or by year+month
route("get:archive/[{year<year>}]/[{month<month>}]", function($year = null, $month = null) {
    $selector = 'template=post, limit=20, sort=-date';
    if ($year)  $selector .= ", date>={$year}-01-01, date<=" . ($year + 1) . "-01-01";
    if ($month) $selector .= ", date>={$year}-{$month}-01";
    $posts = wire()->pages->find($selector);
    header('Content-Type: application/json');
    $data = [];
    foreach ($posts as $p) {
        $data[] = ['id' => $p->id, 'title' => $p->title, 'date' => $p->date];
    }
    return json_encode($data);
});

// Post by slug
route("get:{slug<slug>}", function($slug) {
    $post = wire()->pages->get("template=post, name=$slug");
    if (!$post->id) throw new Wire404Exception();
    return $post->render();
});

$result = $page->dispatchRoutes();
if ($result !== null) {
    echo $result;
} else {
    echo "<h1>{$page->title}</h1>";
}
```

### File Server with Wildcard

```php
<?php
// /site/templates/files.php
namespace ProcessWire;

route("get:download/{*}", function($tail) {
    // GET /download/docs/2024/annual-report.pdf
    // $tail = ['docs', '2024', 'annual-report.pdf']
    $relative = implode('/', array_map('rawurldecode', $tail));
    $basePath = wire()->config->paths->files;
    $fullPath = realpath($basePath . $relative);

    // Prevent directory traversal
    if (!$fullPath || !str_starts_with($fullPath, $basePath)) {
        http_response_code(403);
        return 'Forbidden';
    }

    if (!file_exists($fullPath)) {
        http_response_code(404);
        return 'File not found';
    }

    header('Content-Type: ' . mime_content_type($fullPath));
    header('Content-Disposition: attachment; filename="' . basename($fullPath) . '"');
    return file_get_contents($fullPath);
});

echo $page->dispatchRoutes();
```

---

## API Reference

### Router

| Method | Returns | Description |
| --- | --- | --- |
| `get(string $path, callable $handler)` | `self` | Register a GET route |
| `post(string $path, callable $handler)` | `self` | Register a POST route |
| `put(string $path, callable $handler)` | `self` | Register a PUT route |
| `patch(string $path, callable $handler)` | `self` | Register a PATCH route |
| `delete(string $path, callable $handler)` | `self` | Register a DELETE route |
| `any(string $path, callable $handler)` | `self` | Register for all HTTP methods |
| `add(string $methods, string $path, callable $handler)` | `self` | Register for pipe-separated methods (`'GET\|POST'`) |
| `setNotFoundHandler(callable $handler)` | `self` | Set custom 404 handler |
| `dispatch()` | `mixed\|null` | Run the router — returns handler result or `null` |

### Page Hooks

| Hook | Description |
| --- | --- |
| `$page->route(string $definition, callable $handler)` | Register a route. `$definition` is `"method:path"` or just `"path"` (defaults to GET) |
| `$page->dispatchRoutes()` | Run the router and return the result (or null) |

### Global Functions

| Function | Returns | Description |
| --- | --- | --- |
| `simplerouter()` | `Router` | Get the Router instance |
| `router()` | `Router` | Alias for `simplerouter()` |
| `route(string $definition, callable $handler)` | `Router` | Register a route and return the Router (chainable) |

---

## Best Practices

### Define All Routes Before Dispatching

```php
// Good
route("get:users",      $handlerA);
route("get:users/{id}", $handlerB);
$result = $page->dispatchRoutes();

// Bad — route defined after dispatch, never matched
$result = $page->dispatchRoutes();
route("get:users", $handlerA);
```

### Sanitize Route Parameters

Route parameters are raw URL segments. Always sanitize before using in queries or output:

```php
route("get:search/{q}", function($q) {
    $query   = wire()->sanitizer->text(urldecode($q));
    $results = wire()->pages->find("template=post, title~=$query");
    // ...
});
```

### Use Type Aliases for Safety

Type aliases prevent unintended matches and make intent clear:

```php
// Good — only matches integers
route("get:users/{id<integer>}", $handler);

// Risky — matches any string, including /users/admin
route("get:users/{id}", $handler);
```

### Access ProcessWire Inside Handlers

Inside a closure handler, use `wire()` to access ProcessWire:

```php
route("get:posts/{slug<slug>}", function($slug) {
    $page  = wire()->pages->get("template=post, name=$slug");
    $san   = wire()->sanitizer;
    $input = wire()->input;
    // ...
});
```

### Choose the Right 404 Mode

- **handle404 = false** (default): Mixed templates where some URL segments go to the router and others render the page normally.
- **handle404 = true**: Pure API or fully route-based templates where every request must match a route.

### Enable Cache in Production

Route pattern compilation happens once per template and is reused across requests. The performance gain is significant on templates with many routes.

---

## Troubleshooting

### Routes are not matching

- Confirm that `dispatchRoutes()` is called after all route definitions
- Verify **URL segments are enabled** for the template (Admin → Setup → Templates → your template → URLs tab)
- Test with a simple literal route first: `route("get:test", function() { return "ok"; })`
- Check the actual URL path: segments start after the page URL, not from the domain root

### Getting 404 for a valid route

- Check for typos in the pattern — `{id<integr>}` (missing a letter) would silently never match
- Ensure the HTTP method matches — a form that POSTs won't hit a `get:` route
- Try logging the active segments: `wire()->log->message(implode('/', wire()->input->urlSegments()))`

### Wildcard handler not receiving the array

The wildcard parameter is always named `$tail` — the variable in your handler must use that name:

```php
// Correct
route("get:files/{*}", function($tail) { ... });

// Won't receive the value in PHP 8+
route("get:files/{*}", function($segments) { ... });
```

### Type alias not matching

- Verify the syntax: `{id<integer>}` — angle brackets, not parentheses or colons
- An unknown alias name produces a pattern that never matches — check spelling against the alias table

### Debugging

```php
// Log current state for a request
wire()->log->save('router-debug', implode('/', wire()->input->urlSegments()));
wire()->log->save('router-debug', wire()->input->requestMethod());

// Inspect the router instance
$router = simplerouter();
```

---

## License

This module is released under the MIT License.
