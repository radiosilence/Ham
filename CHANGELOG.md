# Changelog

## Unreleased

Rewritten for PHP 8.5 as a drop-in replacement: the classes, methods, properties
and configuration file format of the previous version are unchanged. The
previous version could not run on PHP 8 at all.

Every method and property is natively typed. Applications that only call Ham
need no changes; subclasses that override methods or redeclare properties must
match the new signatures, for example
`render(string $view, ?array $data = null, string|false|null $layout = null): string`.
The file stays in coercive typing mode so that handlers declaring `int` or
`float` parameters still accept the string captures.

### Changed

- Requires PHP 8.5.
- `route()` accepts any request method by default. A list of methods, when
  given, is now enforced with a 405 response; previously it was stored and
  ignored. `HEAD` is allowed wherever `GET` is.
- A mounted app receives the rest of the path from its parent instead of
  re-matching `$_SERVER['REQUEST_URI']`, and a mount at `/beans` no longer
  matches `/beansprout`.
- `APP_URI` strips a leading prefix only, rather than every occurrence in the URI.
- Configuration files may return an array as well as declare variables.
- `config_from_env()` falls back to `getenv()` when `$_ENV` is not populated.
- `create_logger()` throws when the log file cannot be created or written.
  It previously called `abort()` statically, which is fatal on PHP 8.
- `run()` throws when a handler returns an array or a non-stringable object,
  rather than printing `Array`.
- The `APC` cache is backed by APCu, APC's successor.

### Removed

- The `XCache` cache. XCache does not exist for PHP 7 or later.
- Caching of compiled routes, resolved URIs, template paths and configuration.
  With OPcache, this work costs less than the cache lookup that replaced it.

### Fixed

- `onError()` referenced variables its closure never captured, so it failed
  whenever a log message was given. The handler now also receives the app.
- `<path>` matched a single character only, and now also matches `.`.
- `RedisCache` passed its TTL to Redis in milliseconds where seconds are expected.
- Template data with a `path` key overwrote the template's own path.
