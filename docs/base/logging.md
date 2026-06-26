# Система логирования

Система логирования в Joke — это типизированный, гибкий и расширяемый механизм записи событий приложения. В отличие от
PSR-3, она использует строгую типизацию через enum `LogLevel`, поддерживает множественные обработчики и **настраиваемое
форматирование сообщений**. Благодаря ранней инициализации, логгер доступен даже на этапе загрузки ядра.

---

## Основные компоненты

### 1. **Абстрактный логгер (`AbstractLogger`)**

Базовый класс, реализующий все методы уровней (`info()`, `error()` и т.д.) через делегирование абстрактному методу
`log()`. Позволяет создавать кастомные логгеры, реализуя всего один метод.

### 2. **Конкретный логгер (`Vasoft\Joke\Logging\Logger`)**

Наследуется от `AbstractLogger` и предоставляет готовую реализацию с поддержкой:

- нескольких обработчиков,
- внедряемого форматтера сообщений,
- сохранения исходного шаблона в `context['rawMessage']`.

### 3. **Форматирование сообщений**

Форматирование вынесено в отдельный контракт:

#### Интерфейс `MessageFormatterInterface`

```php
interface MessageFormatterInterface
{
    public function interpolate(object|string $message, array $context = []): string;
}
```

Позволяет реализовать любую стратегию преобразования сообщения: PSR-3-совместимую, JSON, безопасную и т.д.

#### Стандартная реализация: `DefaultMessageFormatter`

- Заменяет плейсхолдеры `{key}` на значения из контекста.
- Безопасно сериализует массивы (с ограничением глубины).
- Преобразует `\Throwable` в полную трассировку с учётом `previous`.

### 4. **Уровни логирования (`Vasoft\Joke\Logging\LogLevel`)**

Это **строковый backed-enum** (`enum LogLevel: string`), соответствующий стандарту PSR-3 и RFC 5424.  
Каждый уровень имеет:

- **строковое значение** (например, `'error'`, `'debug'`) — доступно через `$level->value`,
- **числовую серьёзность** — доступна через метод `$level->severity()` и используется для фильтрации.

| Уровень     | Строковое значение | Серьёзность | Назначение                          |
|-------------|--------------------|-------------|-------------------------------------|
| `EMERGENCY` | `'emergency'`      | 800         | Система неработоспособна            |
| `ALERT`     | `'alert'`          | 700         | Требуется немедленное вмешательство |
| `CRITICAL`  | `'critical'`       | 600         | Критические ошибки                  |
| `ERROR`     | `'error'`          | 500         | Ошибки времени выполнения           |
| `WARNING`   | `'warning'`        | 400         | Предупреждения                      |
| `NOTICE`    | `'notice'`         | 300         | Нормальные, но значимые события     |
| `INFO`      | `'info'`           | 200         | Общая информация                    |
| `DEBUG`     | `'debug'`          | 100         | Отладочная информация               |

> **Важно**: при сравнении уровней (например, в `StreamHandler`) используется **числовая серьёзность**, а не строковое
> значение.

### 5. **Обработчики (`LogHandlerInterface`)**

Интерфейс определяет контракт для записи логов.  
Обработчик получает **уже отформатированное сообщение**, уровень и контекст, и сам решает, записывать ли его.

В поставке есть `StreamHandler`, но вы можете создавать свои.

---

## Интеграция в ядро

Логгер инициализируется **на самом раннем этапе** — до загрузки провайдеров и маршрутов.

### Настройка через `bootstrap/kernel.php`

Файл `bootstrap/kernel.php` (если существует) позволяет задать логгер явно:

```php
<?php
/** @var \Vasoft\Joke\Config\Environment $env */
/** @var \Vasoft\Joke\Support\Normalizers\Path $paths */

use Vasoft\Joke\Application\KernelConfig;
use Vasoft\Joke\Logging\Handlers\StreamHandler;
use Vasoft\Joke\Logging\LogLevel;
use Vasoft\Joke\Logging\Logger;
use Vendor\Project\CustomFormatter;

$loggerHandlers = [
    new StreamHandler($paths->logPath . 'all.log'),
    new StreamHandler($paths->logPath . 'info.log', LogLevel::INFO, LogLevel::INFO),
    new StreamHandler($paths->logPath . 'error.log', LogLevel::ERROR, formatter: new CustomFormatter()),
];

return new KernelConfig()
    ->setLogger(static fn() => new Logger($loggerHandlers));
```

Если файл отсутствует, создаётся логгер по умолчанию с `DefaultMessageFormatter`, пишущий всё в `var/log/error.log`.

> **Важно**: Joke **не использует `NullLogger` по умолчанию**. Даже при минимальной конфигурации ошибки будут записаны.

### Доступ в DI-контейнере

После регистрации логгер доступен:

- По интерфейсу: `$container->get(LoggerInterface::class)`
- По алиасу: `$container->get('logger')`

---

## Встроенный обработчик: `StreamHandler`

Записывает логи в файл или поток (например, `php://stderr`). Поддерживает:

- **Диапазон уровней**: можно логировать только `INFO` или всё от `WARNING` до `CRITICAL`.
- **Автоматическое создание директорий**.
- **Простую ротацию**: при превышении размера (по умолчанию 10 МБ) файл переименовывается в `.old`.
- **Опциональный собственный форматтер**: если задан и в контексте есть `'rawMessage'`, хендлер переформатирует
  сообщение независимо от глобального форматтера логгера.

> Важно: встроенная ротация файлов не является потокобезопасной. В многопроцессной среде (например, под Apache или
> PHP-FPM) возможна запись части логов в файл с расширением .old.

Пример:

```php
new StreamHandler('app.log', LogLevel::WARNING, LogLevel::ERROR);
// Логирует только WARNING, ERROR
```

---

## Обработка ошибок

`ExceptionMiddleware` автоматически логирует все необработанные исключения через `'logger'`:

- `JokeException` → возвращается статус из исключения.
- Любые другие `\Exception` → ответ 500.

Это гарантирует, что ни одна ошибка не останется незамеченной.

---

## Создание собственных компонентов

### Собственный логгер

Для создания собственного логгера унаследуйте его от `AbstractLogger` и реализуйте метод `log()`:

```php
use Vasoft\Joke\Logging\AbstractLogger;
use Vasoft\Joke\Logging\LogLevel;

class TestLogger extends AbstractLogger
{
    private array $records = [];

    public function log(LogLevel $level, object|string $message, array $context = []): void
    {
        $this->records[] = compact('level', 'message', 'context');
    }

    public function getRecords(): array
    {
        return $this->records;
    }
}
```

### Собственный форматтер

Если требуется собственная логика форматирования создайте свой форматтер, реализующий `MessageFormatterInterface`:

```php
use Vasoft\Joke\Contract\Logging\MessageFormatterInterface;

class JsonMessageFormatter implements MessageFormatterInterface
{
    public function interpolate(object|string $message, array $context = []): string
    {
        return json_encode([
            'template' => $message,
            'context' => $context,
            'timestamp' => date('c'),
        ]);
    }
}
```

### Собственный обработчик

Реализуйте `LogHandlerInterface`:

```php
use Vasoft\Joke\Contract\Logging\LogHandlerInterface;
use Vasoft\Joke\Logging\LogLevel;

class SentryHandler implements LogHandlerInterface
{
    public function write(LogLevel $level, string $message, array $context = []): void
    {
        if ($level->severity() >= LogLevel::ERROR->severity()) {
            \Sentry\captureMessage($message);
        }
    }
}
```

### Рекомендации

- **Работайте с `context['rawMessage']` только тогда, когда хендлер использует свой собственный форматтер.**  
  Если форматирование выполняется на уровне логгера, используйте готовое сообщение из параметра `$message`.
- **Делайте хендлеры ленивыми**: открывайте ресурсы (файлы, соединения) только при первом вызове `write()`.
- **Обрабатывайте исключения внутри хендлера**, чтобы сбой одного обработчика не сломал остальные.
- Для тестов можно использовать `NullLogger`, но в продакшене всегда предпочтительнее рабочий логгер.

---

## Заключение

Система логирования Joke сочетает простоту использования и мощную расширяемость. Благодаря чёткому разделению
ответственности (`AbstractLogger`, `MessageFormatterInterface`, `LogHandlerInterface`) и ранней инициализации, вы можете
легко адаптировать её под любые задачи: от простого файла до отправки в Sentry или ELK-стек.