## Основные направления рефакторинга при переходе от версии 1.* к версии 2.*

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

Изменился `Vasoft\Joke\Contract\Container::get(string $name): object;` теперь не может возвращать null. Если сервис не
найден - выбрасывается исключение `Vasoft\Joke\Container\Exceptions\ServiceNotFoundException`. В соответствии с этим
изменен `Vasoft\Joke\Container\BaseContainer`.

### 4. Единая точка информации о путях проекта

Пути проекта необходимо получать через объект Vasoft\Joke\Support\FileSystem (алиас 'normalizer.path'). Удалены
свойства и методы:

- Vasoft\Joke\Application::$basePath
- Vasoft\Joke\Config\Environment::getBasePath()
- Vasoft\Joke\Config\EnvironmentLoader::getBasePath()

### 5 FileRelatedCache изменен конструктор

- FileRelatedCache в параметры конструктора добавлен сервис FileSystem

### 6 Из контейнера зависимостей удален метод register

Из интерфейса `Vasoft\Joke\Contract\Container\DiContainerInterface` и базовой реализации
`Vasoft\Joke\Container\BaseContainer` удален метод register.

Замените вызовы `register()` на явные методы в зависимости от требуемого поведения. Все найденные вызовы должны быть
заменены на `registerSingleton()` или прямое использование `make()`.:

**Было:**

```php
// Неоднозначно: что это — фабрика или синглтон?
$container->register(ServiceInterface::class, new ServiceFactory());

// Неоднозначно: новый экземпляр или переиспользование?
$container->register('logger', Logger::class);
```

**Стало:**

Для **синглтонов** (один экземпляр на всё время жизни приложения):

```php
// Готовый объект
$container->registerSingleton(ServiceInterface::class, new Service());

// Фабрика, результат которой кэшируется
$container->registerSingleton(
    ServiceInterface::class,
    fn() => new Service($dependency)
);

// Класс (будет создан один раз через рефлексию)
$container->registerSingleton(Logger::class, Logger::class);
```

Для **прототипов** (новый экземпляр при каждом запросе):

```php
// Используйте make() напрямую для получения новых экземпляров
$service = $container->make(Service::class);

// Или зарегистрируйте фабрику и вызывайте её явно
$factory = fn() => new Service($dependency);
$service = $factory(); // каждый раз новый объект
```

#### Особые случаи

**Callable-объекты с `__invoke`:**

Если вы использовали объекты-фабрики:

```php
// Было
$container->register('service', new ServiceFactory());

// Стало — явно укажите поведение
$container->registerSingleton('service', new ServiceFactory()); // как синглтон
// или
$service = $container->make(fn() => (new ServiceFactory())()); // как прототип
```
