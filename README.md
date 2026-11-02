# Localzet Proxy

[Русская документация](README.ru.md)

HTTP forward proxy built on Localzet Server with Basic authentication and an IP/port allowlist.

## Status and compatibility

Current release: `1.0.2`. Tests cover Basic authentication, removal of proxy-only headers before HTTP forwarding, CONNECT tunneling, IPv4/IPv6 destination allowlists, malformed requests and upstream failures. GitHub Actions checks PHP 8.1–8.5.

This version targets Localzet Server 7.x. Set non-empty `username` and `password`, and configure `allowedHosts` and `allowedPorts` before starting. Basic credentials are sent in clear text by HTTP; expose the proxy only over a trusted encrypted network or behind a TLS terminator. Allowed destinations must be literal IPv4/IPv6 addresses: write IPv6 in brackets in request URIs (for example, `[2001:db8::1]`) and without brackets in `allowedHosts`. Domain names are rejected because this implementation does not pin asynchronous DNS results. Apply network egress rules as a second boundary. The proxy is not a general-purpose HTTP parser and supports one request per client connection.

## Dependencies

- `php`: `>=8.1`

- `localzet/server`: `^7.0`

## Installation

```sh
composer config repositories.localzet-proxy vcs https://github.com/localzet/Proxy
composer require localzet/proxy:^1.0
```

The package is not registered on Packagist yet; the VCS source resolves the stable `1.x` release tags.

## Development checks

```sh
composer validate --strict
composer install
composer dump-autoload --optimize --strict-psr
composer lint
composer test
composer test:integration
```

The integration suite starts a local Server and verifies HTTP forwarding, CONNECT and auth header isolation. It does not establish production readiness; use TLS for credentials and restrict network ingress and egress.

[Historical usage notes](docs/legacy-readme.md) need verification against the current API.

## Author and license

Ivan Zorin (`localzet`), <creator@localzet.com>, https://www.localzet.com.
Source: https://github.com/localzet/Proxy. AGPL-3.0-or-later; [LICENSE](LICENSE). Original copyright and third-party licenses remain applicable.

[Authors](.github/AUTHORS.md) · [Contributing](.github/CONTRIBUTING.md) · [Security](.github/SECURITY.md)
