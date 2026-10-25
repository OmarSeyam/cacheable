# Cacheable

A Laravel trait that transparently caches Eloquent query results — including per-user caching, search filtering, and paginated results — with a built-in custom paginator and automatic cache invalidation.

## Installation

Install the package via Composer:

```bash
composer require omarseyam/cacheable
```

No service provider registration is needed — this package only ships a trait and a small helper action class, autoloaded via PSR-4.

## Usage

Add the trait to any Eloquent model:

```php
use Illuminate\Database\Eloquent\Model;
use OmarSeyam\Cacheable\Traits\Cacheable;

class Post extends Model
{
    use Cacheable;
}
```

### Basic usage

```php
// Cache all results for one hour (default)
$posts = Post::bring();
```

### Paginated usage

```php
$posts = Post::bring(
    perPage: 15,
    is_paginate: true,
);
```

### Full signature

```php
Post::bring(
    ?callable $callback = null,   // customize the base query, e.g. fn ($query) => $query->where('active', true)
    int $perPage = 10,
    ?string $cache_name = null,   // defaults to the model's table name
    ?int $ttl = null,             // defaults to 1 hour
    string $search_name = 'title',// request() key used for search matching
    ?callable $filter = null,     // extra query filtering callback
    bool $is_paginate = false,
    bool $is_public = false,      // false = cache is scoped per authenticated user
    bool $is_connected = true,    // whether to track this cache key for later invalidation
);
```

### Invalidating the cache

```php
Post::deletingCache();

// Or invalidate related models' caches too
Post::deletingCache(null, Comment::class);
```

An explicit `$user_id` takes precedence over the signed-in user, so you can clear another user's cache:

```php
Post::deletingCache($user->id, Comment::class);
```

To clear the caches of several users at once (for example when one user's action changes data other users have cached, or inside a queued job where no user is signed in):

```php
Post::deletingCacheFor([$author->id, $editor->id], Comment::class);
```

## How it works

- `bring()` builds (or reuses) a query, then serves results from Laravel's cache, keyed by table name (or a custom name you provide), optionally scoped per authenticated user (`Auth::id()`).
- When a search term (via `request($search_name)`) or a `$filter` callback is present, cached results are bypassed and a live, filtered query runs instead.
- When `is_paginate` is `true`, results are paginated with a custom `LengthAwarePaginator` built by `CustomPaginatorAction`, and the count itself is cached separately.
- Every cache key that gets written is tracked (per user) so `deletingCache()` can find and purge all of a model's related cache entries at once.

## Requirements

- PHP 8.1+
- Laravel 9, 10, 11, 12, or 13

## Changelog

### 1.1.0

- Fixed the PHP 8.1+ deprecation "Passing null to parameter #1 ($string) of type string" on reads without a search term.
- `deletingCache()`: an explicit `$user_id` now takes precedence over the signed-in user (it was ignored whenever someone was signed in).
- Added `deletingCacheFor(array $ownerIds, ...$relations)` to clear the tracked caches of several users at once.

## License

The MIT License (MIT). See [LICENSE](LICENSE) for more information.
