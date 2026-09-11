# План изменений к версии 2.0

Разработка микро-фреймворка **Joke** продолжается. После анализа текущей архитектуры сформирован план ключевых изменений
для следующей мажорной версии — **v2.0**.

Эта статья описывает **запланированные** изменения, которые нарушат обратную совместимость. Цель этих правок — сделать
код фреймворка более чистым, логичным и удобным для поддержки.

> **Статус:** Это предварительный план. Список изменений может быть дополнен или скорректирован в процессе
> разработки. Финальный релиз v2.0 выйдет только после полного тестирования всех нововведений.

## Основные направления рефакторинга

### 1. Структурная реорганизация (Namespace Refactoring)

Удалено пространство имен `Vasoft\Joke\Core`. И для еще некоторых классов изменилось пространство имен - необходимо
произвести замену согласно таблице:

| В версии v1.x                                            | Текущий путь (v2.0)                                     | 
|----------------------------------------------------------|---------------------------------------------------------|
| `Vasoft\Joke\Core\Application`                           | `Vasoft\Joke\Application\Application`                   |
| `Vasoft\Joke\Core\ServiceContainer`                      | `Vasoft\Joke\Container\ServiceContainer`                |
| `Vasoft\Joke\Core\BaseContainer`                         | `Vasoft\Joke\Container\BaseContainer`                   |
| `Vasoft\Joke\Core\ParameterResolver`                     | `Vasoft\Joke\Container\ParameterResolver`               |
| `Vasoft\Joke\Core\Routing\Route`                         | `Vasoft\Joke\Routing\Route`                             |
| `Vasoft\Joke\Core\Routing\Router`                        | `Vasoft\Joke\Routing\Router`                            |
| `Vasoft\Joke\Core\Routing\StdGroup`                      | `Vasoft\Joke\Routing\StdGroup`                          |
| `Vasoft\Joke\Core\HttpRequest`                           | `Vasoft\Joke\Http\HttpRequest`                          |
| `Vasoft\Joke\Core\Request\HttpMethod`                    | `Vasoft\Joke\Http\HttpMethod`                           |
| `Vasoft\Joke\Core\Request\ServerCollection`              | `Vasoft\Joke\Http\ServerCollection`                     |
| `Vasoft\Joke\Core\Response\Response`                     | `Vasoft\Joke\Http\Response\Response`                    |
| `Vasoft\Joke\Core\Response\JsonResponse`                 | `Vasoft\Joke\Http\Response\JsonResponse`                |
| `Vasoft\Joke\Core\Response\HtmlResponse`                 | `Vasoft\Joke\Http\Response\HtmlResponse`                |
| `Vasoft\Joke\Core\Response\BinaryResponse`               | `Vasoft\Joke\Http\Response\BinaryResponse`              |
| `Vasoft\Joke\Core\Response\ResponseStatus`               | `Vasoft\Joke\Http\Response\ResponseStatus`              |
| `Vasoft\Joke\Core\Middlewares\CsrfMiddleware`            | `Vasoft\Joke\Http\Csrf\CsrfMiddleware`                  |
| `Vasoft\Joke\Core\Middlewares\ExceptionMiddleware`       | `Vasoft\Joke\Middleware\ExceptionMiddleware`            |
| `Vasoft\Joke\Core\Middlewares\SessionMiddleware`         | `Vasoft\Joke\Http\Middleware\SessionMiddleware`         |
| `Vasoft\Joke\Core\Middlewares\ReadonlySessionMiddleware` | `Vasoft\Joke\Http\Middleware\ReadonlySessionMiddleware` |
| `Vasoft\Joke\Core\Middlewares\StdMiddleware`             | `Vasoft\Joke\Middleware\StdMiddleware`                  |
| `Vasoft\Joke\Core\Middlewares\MiddlewareCollection`      | `Vasoft\Joke\Middleware\MiddlewareCollection`           |
| `Vasoft\Joke\Core\Middlewares\MiddlewareDto`             | `Vasoft\Joke\Middleware\MiddlewareDto`                  |
| `Vasoft\Joke\Core\Collections\HeadersCollection`         | `Vasoft\Joke\Collections\HeadersCollection`             |
| `Vasoft\Joke\Core\Collections\PropsCollection`           | `Vasoft\Joke\Collections\PropsCollection`               |
| `Vasoft\Joke\Core\Collections\ReadonlyPropsCollection`   | `Vasoft\Joke\Collections\ReadonlyPropsCollection`       |
| `Vasoft\Joke\Core\Collections\StringCollection`          | `Vasoft\Joke\Collections\StringCollection`              |
| `Vasoft\Joke\Core\Collections\Session`                   | `Vasoft\Joke\Session\SessionCollection`                 |
| `Vasoft\Joke\Core\Request\Request`                       | `Vasoft\Joke\Foundation\Request`                        |
| `Vasoft\Joke\Types\TypeConverter`                        | `Vasoft\Joke\Support\Types\TypeConverter`               |

### 2. Изменение сигнатуры конструктора приложения

В конструкторе `Vasoft\Joke\Application` убран параметр `$routeConfigWeb` теперь его необходимо передавать через
конфигурацию.
Было:

```php
// bootstrap/app.php
<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Vasoft\Joke\Application\Application;
use Vasoft\Joke\Container\ServiceContainer;

return new Application(dirname(__DIR__), 'routes/custom-web.php', new ServiceContainer());
```

Стало:

```php
// bootstrap/app.php
<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Vasoft\Joke\Application\Application;
use Vasoft\Joke\Container\ServiceContainer;

return new Application(dirname(__DIR__), new ServiceContainer());
```

```php
<?php

/** @var Environment $env */
declare(strict_types=1);

use Vasoft\Joke\Application\ApplicationConfig;
use Vasoft\Joke\Config\Environment;

return new ApplicationConfig()
    ->setFileRoues('routes/custom-web.php');
```

По умолчанию путь `routes/web.php`.

### 3. Изменение поведения контейнера зависимостей

Изменился `Vasoft\Joke\Contract\Container::get(string $name): object;` теперь не может возвращать null. Если сервис не найден - выбрасывается исключение `Vasoft\Joke\Container\Exceptions\ServiceNotFoundException`. В соответствии с этим изменен `Vasoft\Joke\Container\BaseContainer`.

### 4. Единая точка информации о путях проекта

Пути проекта необходимо получать через объект Vasoft\Joke\Support\FileSystem (алиас 'normalizer.path'). Будут удалены
свойства и методы:

- Vasoft\Joke\Application::$basePath
- Vasoft\Joke\Config\Environment::getBasePath()
- Vasoft\Joke\Config\EnvironmentLoader::getBasePath()
- FileRelatedCache в параметры конструктора будут добавлены сервис FileSystem

## Стратегия перехода

Я понимаю важность стабильности для проектов, использующих Joke. Поэтому переход на v2.0 будет проходить в два этапа:

1. **Подготовка (версия 1.2+):**
    * Все старые классы будут сохранены как оболочки (aliases), вызывающие новые классы.
    * При использовании старых путей будут генерироваться предупреждения `E_USER_DEPRECATED`.
    * Это даст время на постепенный рефакторинг кода без поломки работы приложения.
    * Параметр конструктора приложения `$routeConfigWeb` так же учитывается, но может получать пустое значение (для
      следования новому механизму)
    * Предусмотреть обработку исключения `Vasoft\Joke\Container\Exceptions\ServiceNotFoundException`

2. **Релиз (версия 2.0):**
    * Полное удаление устаревших оболочек.
    * Код, использующий старые пути или удаленные методы, потребует обновления.
    * Параметр конструктора приложения `$routeConfigWeb` будет удален.
    * Убрать обработку ситуации когда метод `BaseContainer::get` возвращал `null`.

## Обратная связь

Поскольку это план будущих изменений, я открыт для обсуждения. Если вы используете Joke в своих учебных или боевых
проектах и видите риски в предложенных изменениях — пожалуйста, оставляйте комментарии в Issues на GitHub или в
обсуждениях канала.

Финальный список изменений будет утвержден перед началом активной разработки ветки `2.0`.