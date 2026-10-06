# Changelog

## Unreleased

Rewritten for PHP 8.5. Every public name has changed, so existing applications
need updating.

### Changed

- Requires PHP 8.5 and loads through Composer's PSR-4 autoloader as `Ham\App`.
  Method names are camelCase (`configFromFile`, `templatePaths`).
- Route captures are cast to `int` or `float` according to their placeholder,
  so handlers can declare typed parameters under strict types.
- Request methods passed to `route()` are now enforced; a mismatch returns 405.
  They were previously stored but ignored.
- Mounted apps dispatch the remainder of the path directly and no longer read
  `$_SERVER['REQUEST_URI']` themselves. A mount at `/beans` no longer matches
  `/beansprout`.
- `handle($uri, $method)` dispatches a request without touching superglobals.
  It replaces invoking the app as a closure.
- Configuration files return an array instead of declaring variables.
- `json()` returns the encoded body instead of echoing it and calling `exit`.
- `abort()` escapes its message and no longer includes the app name.
- A missing template throws instead of rendering a 500 page into the output.
- The logger is constructed by the caller (`new FileLogger($path)`) and throws
  when the file is not writable.
- `APP_URI` is replaced by the `basePath` property, which strips only a leading
  prefix rather than every occurrence in the URI.

### Removed

- XCache, APC and Redis caches. XCache and APC do not exist for PHP 7 or later;
  APCu replaces them. Other backends can implement `Ham\Cache`.
- Caching of compiled routes, route lookups and template paths. With OPcache,
  matching a few compiled expressions is cheaper than a cache round trip.

### Fixed

- `onError()`, which referenced variables its closure never captured, is
  replaced by `notFound()`.
- `<path>` matched a single character only.
