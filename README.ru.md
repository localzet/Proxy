# Localzet Proxy

[English documentation](README.md)

HTTP forward proxy на Localzet Server с Basic-аутентификацией и списком разрешённых IP-адресов и портов.

## Состояние и совместимость

Текущий выпуск — `1.0.1`. Проверки охватывают Basic-аутентификацию, удаление служебных proxy-заголовков перед HTTP forwarding, CONNECT-туннель, allowlist назначения, неверные запросы и ошибки upstream. GitHub Actions проверяет PHP 8.1–8.5.

Эта версия рассчитана на Localzet Server 7.x. Перед запуском задайте непустые `username` и `password`, настройте `allowedHosts` и `allowedPorts`. Basic credentials передаются открытым текстом внутри HTTP: используйте прокси только в доверенной зашифрованной сети или за TLS terminator. В `allowedHosts` указывайте буквальные IPv4/IPv6 адреса: доменные имена отклоняются, поскольку реализация не фиксирует результат асинхронного DNS. Дополнительно ограничьте исходящий трафик сетевыми ACL. Прокси обрабатывает один запрос на клиентское соединение и не является полноценным HTTP parser.

## Зависимости

- `php`: `>=8.1`

- `localzet/server`: `^7.0`

## Установка

```sh
composer config repositories.localzet-proxy vcs https://github.com/localzet/Proxy
composer require localzet/proxy:^1.0
```

Пакет пока не зарегистрирован в Packagist; VCS source разрешает установку стабильных тегов ветки `1.x`.

## Проверки разработки

```sh
composer validate --strict
composer install
composer dump-autoload --optimize --strict-psr
composer lint
composer test
composer test:integration
```

Интеграционная проверка запускает локальный Server и проверяет HTTP forwarding, CONNECT и изоляцию заголовков авторизации. Это не подтверждает готовность к эксплуатации: используйте TLS для credentials и ограничьте входящие и исходящие сетевые подключения.

[Исторические примеры](docs/legacy-readme.md) нужно сверять с текущим API.

## Автор и лицензия

Ivan Zorin (`localzet`), <creator@localzet.com>, https://www.localzet.com.
Source: https://github.com/localzet/Proxy. AGPL-3.0-or-later; [LICENSE](LICENSE). Сохраняются исходные уведомления авторов и лицензии сторонних компонентов.

[Authors](.github/AUTHORS.md) · [Contributing](.github/CONTRIBUTING.md) · [Security](.github/SECURITY.md)
