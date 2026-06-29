# Сервис-провайдеры (Service Providers)

Система сервис-провайдеров — это механизм инициализации компонентов приложения, управления зависимостями и настройки
контейнера внедрения зависимостей (DI). В фреймворке **Joke** провайдеры отвечают за то, *как* и *когда* создаются
сервисы, а также гарантируют правильный порядок их загрузки.

## Зачем нужны провайдеры?

Вместо того, чтобы регистрировать все сервисы вручную в одном файле bootstrap или смешивать логику создания объектов с
бизнес-логикой контроллеров, провайдеры инкапсулируют процесс настройки:

1. **Регистрация (`register`)**: Определение правил создания объектов (биндинг интерфейсов к реализациям).
2. **Инициализация (`boot`)**: Выполнение логики, требующей наличия всех зависимостей (настройка маршрутов, подключение
   middleware, чтение конфигов).
3. **Управление зависимостями (`requires` и `provides`)**: Явное объявление того, какие сервисы необходимы для работы
   текущего компонента и какие сервисы предоставляет данный провайдер.
4. **Ленивая загрузка**: Возможность отложить инициализацию тяжелых сервисов до момента их первого использования, просто
   поместив провайдер в специальный список конфигурации.
5. **Информирование о предоставляемых конфигурациях (`provideConfigs`)**: (для провайдеров, реализующих
   `ConfigurableServiceProviderInterface`) явное объявление, какие конфигурации предоставляет данный провайдер.
6. **Создание конфигураций по умолчанию (`buildConfig`)**: (для провайдеров, реализующих
   `ConfigurableServiceProviderInterface`) создание экземпляров по умолчанию для предоставляемых конфигураций, если
   файлы не найдены.

## Жизненный цикл провайдера

Каждый провайдер проходит через две основные фазы:

### 1. Регистрация (`register`)

На этом этапе провайдер сообщает контейнеру, какие сервисы он может создать.

* **Что можно делать:** Привязывать интерфейсы к классам, задавать параметры конфигурации, регистрировать одиночки (
  singletons).
* **Чего нельзя делать:** Использовать другие сервисы из контейнера, так как они могут быть еще не зарегистрированы.
  Исключение составляют сервисы, явно объявленные в методе `requires()`.

### 2. Загрузка (`boot`)

Эта фаза наступает после того, как все провайдеры прошли регистрацию.

* **Что можно делать:** Использовать любые сервисы контейнера, регистрировать маршруты, добавлять директивы
  шаблонизатора, настраивать события.
* **Гарантии:** К моменту вызова `boot()` все зависимости, объявленные в `requires()`, уже полностью инициализированы.

## Создание провайдера

Все провайдеры должны реализовывать интерфейс `Vasoft\Joke\Contract\Provider\ServiceProviderInterface`. Для удобства
рекомендуется наследоваться от абстрактного класса `Vasoft\Joke\Provider\AbstractProvider`, который предоставляет
реализации методов `requires()` и `provides()` по умолчанию.

Если провайдер управляет какими-либо конфигурациями, он должен также реализовывать интерфейс
`Vasoft\Joke\Contract\Provider\ConfigurableServiceProviderInterface`.

### Базовый пример

```php
<?php

namespace App\Providers;

use Vasoft\Joke\Provider\AbstractProvider;
use App\Services\MailService;
use App\Contracts\MailInterface;

class MailServiceProvider extends AbstractProvider
{
    public function __construct(
        private readonly ServiceContainer $serviceContainer,
    ) {}
    // Опционально: указываем, какие сервисы предоставляет этот провайдер
    public function provides(): array
    {
        return [MailInterface::class];
    }

    public function register(): void
    {
        // Регистрируем сервис как одиночку
        $this->serviceContainer->registerSingleton(MailInterface::class, MailService::class);
    }

    public function boot(): void
    {
        // Используем сервис после его регистрации
        $mailer = $this->serviceContainer->get(MailInterface::class);
        $mailer->connect();
    }
}
```

### Пример провайдера, предоставляющего конфигурации

```php
<?php

namespace App\Providers;

use Vasoft\Joke\Provider\AbstractProvider;
use Vasoft\Joke\Container\ServiceContainer;
use Vasoft\Joke\Config\AbstractConfig;
use Vasoft\Joke\Contract\Provider\ConfigurableServiceProviderInterface;
use App\Mail\MailConfig;
use App\Services\MailService;
use App\Contracts\MailInterface;

class MailServiceProvider extends AbstractProvider
    implements ConfigurableServiceProviderInterface
{
    public function __construct(
        private readonly ServiceContainer $serviceContainer,
    ) {}

    public function provides(): array
    {
        return [MailInterface::class];
    }

    public function register(): void
    {
        $this->serviceContainer->registerSingleton(MailInterface::class, MailService::class);
    }

    public function boot(): void
    {
        $mailer = $this->serviceContainer->get(MailInterface::class);
        $mailer->connect();
    }
    
    /**
     * Возвращает список классов конфигураций, которые может предоставить этот провайдер.
     *
     * @return list<class-string<AbstractConfig>>
     */
    public static function provideConfigs(): array
    {
        return [MailConfig::class];
    }
     
    /**
     * Создает экземпляр конфигурации по умолчанию.
     * Вызывается только если файл конфигурации не найден.
     *
     * @template T of AbstractConfig
     * @param class-string<T> $configClass
     * @return T
     */
    public function buildConfig(string $configClass, ServiceContainer $container): AbstractConfig
    {
        if (MailConfig::class === $configClass) {
            return new MailConfig(
                driver: 'smtp',
                host: 'localhost',
                port: 25
            );
        }

        throw new UnknownConfigException($configClass);
    }
}
```

## Управление зависимостями

Одной из ключевых особенностей системы является метод `requires()`. Он позволяет явно указать граф зависимостей между
провайдерами. Менеджер провайдеров автоматически построит правильный порядок инициализации независимо от порядка
объявления в конфиге.

```php
public function requires(): array
{
    return [
        \App\Contracts\ConfigInterface::class,
        \App\Contracts\DatabaseConnection::class,
    ];
}
```

**Как это работает:**

1. Перед вызовом `register()` текущего провайдера система проверит список `requires()`.
2. Если для требуемого сервиса есть свой провайдер (в любом из списков), он будет зарегистрирован рекурсивно.
3. Если провайдера нет, система проверит наличие сервиса напрямую в контейнере (`$container->has()`).
4. Если сервис не найден ни там, ни там, будет выброшено исключение `ServiceNotFoundException`.

Это избавляет разработчика от необходимости вручную сортировать провайдеры в конфигурационном файле.

## Отложенные провайдеры (Deferred Providers)

Для оптимизации производительности тяжелые сервисы (например, генераторы PDF, почтовые транспорты) можно загружать
только тогда, когда они действительно нужны.

**Важное изменение:** В отличие от других фреймворков, в Joke **не нужно реализовывать специальный интерфейс** для
отложенной загрузки. Любой провайдер становится отложенным, если вы добавляете его в массив `$deferredProviders` при
настройке ядра.

Единственное требование для провайдера, который планируется использовать как отложенный (или от которого зависят
другие) — реализовать метод `provides()`, возвращающий список предоставляемых сервисов.

```php
<?php

namespace App\Providers;

use Vasoft\Joke\Provider\AbstractProvider;
use App\Services\PdfGenerator;
use App\Contracts\PdfGeneratorInterface;

class PdfServiceProvider extends AbstractProvider
{
    // Обязательно: указываем сервисы для ленивой загрузки
    public function provides(): array
    {
        return [PdfGeneratorInterface::class];
    }

    public function requires(): array
    {
        return [\App\Contracts\ConfigInterface::class];
    }

    public function register(): void
    {
        $this->serviceContainer->registerSingleton(PdfGeneratorInterface::class, PdfGenerator::class);
    }

    public function boot(): void
    {
        // Дополнительная настройка
    }
}
```

**Механизм работы:**

1. Если провайдер добавлен в список отложенных, при старте приложения вызывается только его метод `provides()` (для
   заполнения карты сервисов). Методы `register()` и `boot()` **не вызываются**.
2. Когда код впервые запрашивает сервис, контейнер находит провайдер в карте.
3. Автоматически запускается процесс полной загрузки: выполняются зависимости, затем `register()`, затем `boot()`.
4. Создается и возвращается экземпляр сервиса.

Если тот же самый провайдер добавить в список обычных, он загрузится сразу при старте. Гибкость достигается выбором
списка конфигурации, а не изменением кода класса.

## Регистрация провайдеров

Регистрация провайдеров полностью декларативна и осуществляется через файл конфигурации ядра `bootstrap/kernel.php`. Вам
не нужно вручную вызывать методы регистрации или использовать специальные билдеры — фреймворк делает это автоматически
на основе настроек в `KernelConfig`.

### Как это работает

1. Вы создаете или редактируете файл `bootstrap/kernel.php`.
2. Возвращаете экземпляр `KernelConfig`, добавляя в него нужные классы провайдеров.
3. Разделяете провайдеры на две группы:
    * **Обычные (`addProvider`)**: Загружаются сразу при старте приложения.
    * **Отложенные (`addDeferredProvider`)**: Загружаются лениво, при первом запросе сервиса.

### Пример конфигурации (`bootstrap/kernel.php`)

```php
<?php

use Vasoft\Joke\Application\KernelConfig;
use App\Providers\AppServiceProvider;
use App\Providers\RouteServiceProvider;
use App\Providers\MailServiceProvider;
use App\Providers\QueueServiceProvider;

return (new KernelConfig())
    // Провайдеры, которые нужны сразу (маршруты, базовые сервисы)
    ->addProvider(AppServiceProvider::class)
    ->addProvider(RouteServiceProvider::class)
    
    // Тяжелые сервисы, которые можно загрузить позже
    ->addDeferredProvider(MailServiceProvider::class)
    ->addDeferredProvider(QueueServiceProvider::class)
    
    // Настройка путей к конфигам (опционально)
    ->setBaseConfigPath('config')
    ->setLazyConfigPath('config/lazy');
```

### Что происходит под капотом?

При запуске приложения класс `Application` считывает файл конфигурации ядра и выполняет следующую последовательность:

1. Инициализирует окружение и `ConfigManager`.
2. Передает списки провайдеров во внутренний `ProviderManager`.
3. Для **обычных** провайдеров сразу вызывает `register()`, а затем `boot()`.
4. Для **отложенных** вызывает только `provides()` для построения карты сервисов. Их полная загрузка происходит
   автоматически при первом обращении к предоставляемым ими сервисам.

> **Примечание:** Один и тот же класс провайдера не может находиться одновременно в списках обычных и отложенных. Это
> вызовет исключение `ProviderException` при старте.

## Обработка ошибок

Система провайдеров выбрасывает специфичные исключения, помогающие диагностировать проблемы конфигурации:

| Исключение                 | Описание                                                                                                            |
|:---------------------------|:--------------------------------------------------------------------------------------------------------------------|
| `ServiceNotFoundException` | Провайдер требует сервис, который не предоставлен другими провайдерами и отсутствует в контейнере.                  |
| `MultipleProvideException` | Два разных провайдера заявляют, что предоставляют один и тот же сервис (конфликт имен).                             |
| `ProviderException`        | Общее исключение. Часто указывает на циклическую зависимость или попытку добавить один класс в оба списка загрузки. |
| `ConfigException`          | Ошибка при загрузке или создании конфигурации через провайдер.                                                      |

## Интроспекция и отладка

`ProviderManager` предоставляет методы для получения информации о состоянии системы:

```php
// Получить список классов всех зарегистрированных провайдеров
$registered = $manager->getRegisteredProviders();

// Получить список классов всех загруженных (прошедших boot) провайдеров
$loaded = $manager->getLoadedProviders();

// Получить карту всех сервисов, доступных для ленивой загрузки
$services = $manager->getProvidedServices();
```

## Лучшие практики

1. **Реализуйте `provides()`**: Даже если провайдер обычный, явно укажите предоставляемые сервисы для прозрачности графа
   зависимостей.
2. **Разделяйте `register` и `boot`**: Не используйте другие сервисы внутри `register()`, если они не объявлены в
   `requires()`.
3. **Используйте ленивую загрузку**: Если сервис используется редко, просто перенесите его класс из `addProvider` в
   `addDeferredProvider` в конфиге ядра.
4. **Избегайте циклов**: Если провайдер A требует B, а B требует A, приложение не запустится. Вынесите общую логику в
   третий сервис.
5. **Типизируйте зависимости**: В методе `requires()` возвращайте полные имена классов или интерфейсов (`class-string`).