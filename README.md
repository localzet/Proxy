# Localzet Proxy

[Русская документация](README.ru.md)

Experimental HTTP forward proxy built on Localzet Server.

## Status and compatibility

Class loading and writable Server configuration are regression-tested. The configured destination policy is regression-tested. Client authentication, complete HTTP framing and end-to-end CONNECT tests still need work before untrusted deployment.

This release targets Localzet Server 7.x. The forwarder has no built-in client authentication. Configure `allowedHosts` and `allowedPorts` before use. The policy matches exact host names and ports; it does not resolve DNS or prevent a permitted name from resolving to a private address. Bind it to a trusted network and enforce egress ACLs at the network boundary; do not expose it as a public proxy.

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
