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
Guzzle OAuth Subscriber 0.9 supported PHP `^7.2.5 || ^8.0`, Guzzle `^7.10`, and
Guzzle PSR-7 `^2.8`.

If your application still supports PHP 7.2 or 7.3, or still uses Guzzle 7 or
Guzzle PSR-7 2, continue using Guzzle OAuth Subscriber 0.9 until your minimum
requirements are raised.

#### Native Signatures

`GuzzleHttp\Subscriber\Oauth\Oauth1::__invoke()` now declares a `\Closure`
return type. Subclasses overriding this method must update their method
signature to remain compatible.

OAuth middleware handlers are expected to follow Guzzle 8's handler contract and
return `GuzzleHttp\Promise\PromiseInterface` values.
