# Localzet Proxy

[Русская документация](README.ru.md)

HTTP forward proxy built on Localzet Server with Basic authentication and an IP/port allowlist.

## Status and compatibility

Local tests cover Basic authentication, HTTP forwarding, CONNECT tunneling, destination allowlists, malformed requests and upstream failures.

This version targets Localzet Server 7.x. Set non-empty `username` and `password`, and configure `allowedHosts` and `allowedPorts` before starting. Basic credentials are sent in clear text by HTTP; expose the proxy only over a trusted encrypted network or behind a TLS terminator. Allowed destinations must be literal IPv4/IPv6 addresses: domain names are rejected because this implementation does not pin asynchronous DNS results. Apply network egress rules as a second boundary. The proxy is not a general-purpose HTTP parser and supports one request per client connection.

## Dependencies

- `php`: `>=8.1`

- `localzet/server`: `^7.0`

## Installation

```sh
composer config repositories.localzet-proxy vcs https://github.com/localzet/Proxy
composer require localzet/proxy:dev-main
```

The package is not published on Packagist yet; this VCS source installs the current Server 7 branch.

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
