# Localzet Proxy

[English documentation](README.md)

Экспериментальный HTTP forward proxy на Localzet Server.

## Состояние и совместимость

Проверены загрузка класса и доступность настроек Server. Аутентификация, политика адресов назначения, установление CONNECT, ответы при ошибках и полноценный HTTP framing требуют доработки перед работой с недоверенными клиентами.

Это компонент для Server 4.x; совместимость с Server 7.x не установлена.

## Зависимости

- `php`: `>=8.1`

- `localzet/server`: `^4.1`

## Установка

```sh
composer require localzet/proxy
```

## Проверки разработки

```sh
composer validate --strict
composer install
composer dump-autoload --optimize --strict-psr
composer lint
composer test
```

Установка, lint и автозагрузка не подтверждают сквозное поведение или готовность к эксплуатации.

[Исторические примеры](docs/legacy-readme.md) нужно сверять с текущим API.

## Автор и лицензия

Ivan Zorin (`localzet`), <creator@localzet.com>, https://www.localzet.com.
Source: https://github.com/localzet/Proxy. AGPL-3.0-or-later; [LICENSE](LICENSE). Сохраняются исходные уведомления авторов и лицензии сторонних компонентов.

[Authors](.github/AUTHORS.md) · [Contributing](.github/CONTRIBUTING.md) · [Security](.github/SECURITY.md)
