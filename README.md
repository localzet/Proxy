# Localzet Proxy

[Русская документация](README.ru.md)

Experimental HTTP forward proxy built on Localzet Server.

## Status and compatibility

Class loading and writable Server configuration are regression-tested. Authentication, destination policy, CONNECT establishment, failure responses and full HTTP framing need further work before an untrusted deployment.

This is a Server 4.x component; Server 7.x compatibility is not established.

## Dependencies

- `php`: `>=8.1`

- `localzet/server`: `^4.1`

## Installation

```sh
composer require localzet/proxy
```

## Development checks

```sh
composer validate --strict
composer install
composer dump-autoload --optimize --strict-psr
composer lint
composer test
```

Installation, lint and autoload checks do not establish end-to-end behavior or production readiness.

[Historical usage notes](docs/legacy-readme.md) need verification against the current API.

## Author and license

Ivan Zorin (`localzet`), <creator@localzet.com>, https://www.localzet.com.
Source: https://github.com/localzet/Proxy. AGPL-3.0-or-later; [LICENSE](LICENSE). Original copyright and third-party licenses remain applicable.

[Authors](.github/AUTHORS.md) · [Contributing](.github/CONTRIBUTING.md) · [Security](.github/SECURITY.md)
