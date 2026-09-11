# Middleware

Joke поддерживает многоуровневую систему middleware с возможностью именования, группировки и тонкого контроля над
порядком выполнения. Middleware позволяют инкапсулировать кросс-функциональную логику: аутентификацию, логирование,
CORS, обработку ошибок и т.д.

Все middleware реализуются как классы, совместимые с вызываемым интерфейсом (например, invokable-классы или callable).

## Уровни middleware

Система поддерживает три уровня применения:

- **Глобальные middleware** — выполняются до определения маршрута.
- **Middleware маршрутизатора** — применяются после сопоставления маршрута, но до его обработчика; могут быть привязаны
  к группам.
- **Middleware отдельного маршрута** — регистрируются непосредственно на конкретном маршруте.

Порядок выполнения:

- Глобальные middleware
- Определение маршрута
- Middleware маршрутизатора с учетом групп
- Middleware маршрута
- Обработчик маршрута

## Безымянные middleware

Безымянные middleware регистрируются по классу и всегда добавляются как новые экземпляры. Если зарегистрировать один и
тот же класс дважды — он будет выполнен дважды:

```php
$router->get('/hello', fn() => 'hi','hello')
    ->addMiddleware(CustomMiddleware1::class)
    ->addMiddleware(CustomMiddleware1::class);
```

> Используйте безымянные middleware осознанно — дублирование может привести к неожиданным побочным эффектам.

## Именованные middleware

Именованные middleware регистрируются с уникальным ключом. При повторной регистрации с тем же именем — предыдущий
заменяется:

```php
$router->get('/hello', fn() => 'hi','hello')
    ->addMiddleware(CustomMiddleware1::class, 'auth')
    ->addMiddleware(CustomMiddleware2::class, 'auth'); // Заменит CustomMiddleware1
```

## Глобальные middleware

Регистрируются на уровне приложения и выполняются до того, как будет определён маршрут. Полезны для логирования, CORS,
обработки исключений и т.п.

```php
// bootstrap/app.php

require __DIR__ . '/../vendor/autoload.php';

use Vasoft\Joke\Contract\Routing\RouterInterface;
use Vasoft\Joke\Application\Application;
use Vasoft\Joke\Routing\Router;
use Vasoft\Joke\Container\ServiceContainer;

return new Application(dirname(__DIR__), new ServiceContainer())
    ->addMiddleware(SomeMiddleware1::class,'myNamedMiddleware')
    ->addMiddleware(SomeMiddleware2::class);
```

## Middleware маршрутизатора

Регистрируются через `addRouteMiddleware()` и применяются после сопоставления маршрута, но до его обработчика.
Можно ограничить их применение только определёнными группами маршрутов:

```php
// bootstrap/app.php

require __DIR__ . '/../vendor/autoload.php';

use Vasoft\Joke\Contract\Routing\RouterInterface;
use Vasoft\Joke\Application\Application;
use Vasoft\Joke\Routing\Router;
use Vasoft\Joke\Container\ServiceContainer;

return new Application(dirname(__DIR__), new ServiceContainer())
    ->addRouteMiddleware(SomeMiddleware1::class,'myNamedMiddleware1')
    ->addRouteMiddleware(SomeMiddleware2::class)
    ->addRouteMiddleware(SomeMiddleware3::class,'myNamedMiddleware2',['example','internal']);
```

Если группы не указаны — middleware применяется ко всем маршрутам.

## Middleware отдельных маршрутов

Назначаются напрямую при регистрации маршрута:

```php
$router->get('/hello', fn() => 'hi')
    ->addMiddleware(CustomMiddleware1::class)
    ->addMiddleware(CustomMiddleware2::class,'custom')
    ;
```

### Переопределение именованных middleware

Если маршрут регистрирует middleware с именем, уже заданным на уровне маршрутизатора, — он заменяет его, но сохраняет
позицию в цепочке:

```php
// На уровне маршрутизатора
->addRouteMiddleware(LoggerMiddleware::class, 'logger')

// На уровне маршрута
->addMiddleware(DebugLoggerMiddleware::class, 'logger')
```

> Это позволяет гибко кастомизировать поведение без изменения общей структуры middleware-цепочки.

### Стандартные именованные middleware

Для обеспечения единственности и предсказуемости ключевых middleware фреймворк предоставляет перечисление
`Vasoft\Joke\Middleware\StdMiddleware`

Оно определяет зарезервированные имена для встроенных middleware:

```php
enum StdMiddleware: string
{
    case SESSION = 'session';     // Управление сессией
    case EXCEPTION = 'exception'; // Обработка исключений
    case CSRF = 'csrf';           // Защита от CSRF-атак
}
```

> Эти имена используются автоматически при подключении веб-маршрутов. Если вы регистрируете собственный
> middleware с тем же именем (например, 'session'), он заменит стандартный — но сохранит его позицию в цепочке.

Пример: замена стандартного middleware сессии

```php
use Vasoft\Joke\Middleware\StdMiddleware;
$router->get('/custom', fn() => '...')
    ->addMiddleware(CustomSessionMiddleware::class, StdMiddleware::SESSION->value);
```

Теперь вместо встроенного SessionMiddleware будет использоваться ваш CustomSessionMiddleware, но он по-прежнему будет
выполняться в той же точке цепочки — до CSRF и после глобальных middleware.

## Middleware, регистрируемые по умолчанию

При создании экземпляра Application фреймворк автоматически регистрирует следующие встроенные middleware:

- Глобальный уровень:
    - `ExceptionMiddleware` с именем `StdMiddleware::EXCEPTION->value` — перехватывает необработанные исключения
- Уровень маршрутизатора
    - `SessionMiddleware` с именем `StdMiddleware::SESSION->value` — управляет сессией (по умолчанию в блокирующем
      режиме);
    - `CsrfMiddleware` с именем `StdMiddleware::CSRF->value`, применяемый только к маршрутам из группы
      `StdGroup::WEB->value` (то есть ко всем маршрутам, загружаемым из файла маршрутов).

Эти middleware обеспечивают базовую безопасность и стабильность веб-приложений «из коробки». При необходимости их можно
заменить, зарегистрировав собственный middleware с тем же именем.

## Контракт middleware

Все middleware в Joke должны реализовывать интерфейс `Vasoft\Joke\Contract\Middleware\MiddlewareInterface`.

Этот контракт гарантирует единообразное поведение и совместимость с цепочкой middleware. Интерфейс определяет
единственный метод:

```php
public function handle(HttpRequest $request, callable $next): mixed;
```

- $request — входящий HTTP-запрос (Vasoft\Joke\Http\HttpRequest);
- $next — callable без параметров, который при вызове возвращает результат выполнения следующего звена цепочки;
- Метод должен вернуть значение:
    - скалярным/составным значением (например, строкой), которое будет объединено с другими частями ответа,
    - экземпляром Vasoft\Joke\Http\Response\Response (или его наследником), который станет финальным HTTP-ответом.

Цепочка middleware строится как вложенная композиция: каждый middleware может модифицировать результат до и/или после
вызова $next().

Пример реализации:

```php
use Vasoft\Joke\Contract\Middleware\MiddlewareInterface;
use Vasoft\Joke\Http\HttpRequest;

class LoggingMiddleware implements MiddlewareInterface
{
    public int $index = 0;

    public function handle(HttpRequest $request, callable $next): string
    {
        // Действия до обработчика
        $prefix = "[MW {$this->index}] Начало > ";

        // Получаем результат следующего звена
        $innerResult = $next();

        // Действия после обработчика
        $suffix = " < [MW {$this->index}] Конец";

        return $prefix . $innerResult . $suffix;
    }
}
```

Если зарегистрировать несколько таких middleware, результат будет выглядеть так:

```text
[MW 0] Начало > [MW 1] Начало > Ответ контроллера < [MW 1] Конец < [MW 0] Конец`
```

Пример с прерыванием цепочки:

```php
use Vasoft\Joke\Http\Response\HtmlResponse;
use Vasoft\Joke\Http\Response\ResponseStatus;

public function handle(HttpRequest $request, callable $next): mixed
{
    if (!$this->userIsAuthorized($request)) {
        // Возвращаем готовый Response — цепочка не продолжится
        return new HtmlResponse()
            ->setBody('Forbidden')
            ->setStatus(ResponseStatus::FORBIDDEN);
    }

    return $next(); // передаём управление дальше
}
```

> Middleware могут получать зависимости через конструктор — они будут автоматически внедрены DI-контейнером.

Такая гибкая модель позволяет:

- оборачивать вывод (например, для логирования или кэширования),
- полностью перехватывать запрос (например, для аутентификации или редиректов),
- сохранять совместимость между простыми и сложными сценариями.