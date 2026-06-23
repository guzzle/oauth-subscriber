# CHANGELOG

## 0.9.3 - 2026-06-23

* Require `guzzlehttp/guzzle` ^7.12.3 and `guzzlehttp/psr7` ^2.12.3
* Fixed form body signing for parameterized and case-insensitive content types

## 0.9.2 - 2026-06-12

* Fixed non-finite float values emitting coercion warnings on PHP 8.5

## 0.9.1 - 2026-06-02

* Require `guzzlehttp/guzzle` ^7.11, `guzzlehttp/psr7` ^2.11

## 0.9.0 - 2026-05-19

* Added support for PHP 8.5
* Added support for per-request `token` and `token_secret` overrides
* Convert RSA signing failures to runtime exceptions
* Fixed OAuth parameter normalization for duplicate parameter values
* Sign bare query and form parameters as empty values

## 0.8.2 - 2026-05-16

* Fixed signature generation when request body or query parameters include `oauth_signature`
* Validate RSA private key configuration before signing

## 0.8.1 - 2025-01-06

* Fixed insufficient nonce entropy (CVE-2025-21617)

## 0.8.0 - 2025-01-06

* Adjusted some method modifiers and added return types
* Fixed signature generation with duplicate query parameters

## 0.7.0 - 2025-01-06

* Dropped support for HHVM and PHP <7.2.5
* Dropped support for Guzzle 6.x and PSR-7 1.x
* Added support for PHP 8.1, 8.2, 8.3, 8.4
* Add param types to various methods

## 0.6.0 - 2021-07-13

* Added support for `guzzlehttp/psr7:^2.0`

## 0.5.0 - 2021-02-17

* Add oauth_body_hash parameter to authorization header
* Do not require token_secret for 2-legged authentication
* Added HMAC-SHA256 support
* Added ext-openssl suggest
* Added PHP 8 Support

## 0.4.0 - 2020-06-30

* Allow guzzle 7

## 0.3.0 - 2015-08-15

* Updated to work with Guzzle 6 as a middleware
