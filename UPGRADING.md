Guzzle OAuth Subscriber Upgrade Guide
=====================================

0.9 to 1.0
----------

Guzzle OAuth Subscriber 1.0 is a major release that raises the minimum PHP
version and updates the supported Guzzle dependency stack for Guzzle 8 and
Guzzle PSR-7 3.x.

#### PHP Version and Dependencies

Guzzle OAuth Subscriber 1.0 requires PHP `^7.4 || ^8.0`,
[Guzzle 8.x](https://github.com/guzzle/guzzle/blob/8.0/UPGRADING.md), and
[Guzzle PSR-7 3.x](https://github.com/guzzle/psr7/blob/3.0/UPGRADING.md).
Guzzle OAuth Subscriber 0.9 supported PHP `^7.2.5 || ^8.0`, Guzzle `^7.11`, and
Guzzle PSR-7 `^2.11`.

If your application still supports PHP 7.2 or 7.3, or still uses Guzzle 7 or
Guzzle PSR-7 2, continue using Guzzle OAuth Subscriber 0.9 until your minimum
requirements are raised.

#### Native Signatures

`GuzzleHttp\Subscriber\Oauth\Oauth1::__invoke()` now declares a `\Closure`
return type. Subclasses overriding this method must update their method
signature to remain compatible.

OAuth middleware handlers are expected to follow Guzzle 8's handler contract and
return `GuzzleHttp\Promise\PromiseInterface` values.

#### Native PHP Serialization

`Oauth1` no longer supports native PHP `serialize()` or `unserialize()`.
Persist OAuth configuration values and create a new middleware instance instead
of persisting runtime middleware objects.

#### Non-Finite Float OAuth Parameters

OAuth parameter values containing `NAN`, `INF`, or `-INF` now throw an
`\InvalidArgumentException` during signature generation. Convert these
values to finite strings or omit them before they enter OAuth parameters.

#### Generic Promise and Structured PHPDoc Types

`Oauth1::__invoke()` now documents the standard Guzzle middleware handler
contract with generic `PromiseInterface<ResponseInterface, mixed>` PHPDoc types.
This is a static-analysis-only change and does not alter runtime behavior, but
projects with stricter static analysis may see new or different diagnostics.

`Oauth1::__construct()` config PHPDoc now uses a structured array shape for the
supported OAuth options. If your project documents reusable OAuth config arrays
or custom middleware handlers, you may need to update those PHPDoc annotations
to match the supported option and handler shapes.

#### Sensitive Stack Trace Arguments

OAuth credentials, signing inputs, and credential-bearing request arguments
are marked with `#[\SensitiveParameter]`. PHP 8.2 and later replace those
arguments in stack traces with `SensitiveParameterValue`. PHP 7.4 through 8.1
do not redact trace arguments.

This does not redact logs, exception messages, object properties, wire traffic,
captured variables, return values, user callbacks, or the separate executing
object in an explicit backtrace.
