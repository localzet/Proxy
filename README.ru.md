# Localzet Proxy

[English documentation](README.md)

Экспериментальный HTTP forward proxy на Localzet Server.

## Состояние и совместимость

Проверены загрузка класса и доступность настроек Server. Аутентификация, политика адресов назначения, установление CONNECT, ответы при ошибках и полноценный HTTP framing требуют доработки перед работой с недоверенными клиентами.

Эта версия рассчитана на Localzet Server 7.x. Встроенной аутентификации клиентов нет. До запуска настройте `allowedHosts` и `allowedPorts`: список сравнивает точные имена хостов и порты, но не разрешает DNS и не блокирует частные IP, возвращённые для разрешённого имени. Ограничьте egress сетевыми ACL и не публикуйте прокси в открытом доступе.

## Зависимости

- `php`: `>=8.1`

- `localzet/server`: `^7.0`

## Установка

```sh
composer config repositories.localzet-proxy vcs https://github.com/localzet/Proxy
composer require localzet/proxy:dev-main
```

Пакет пока не опубликован в Packagist; указанный VCS source устанавливает текущую ветку Server 7.

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
