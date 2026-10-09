# Разработка собственных транспортов почты

В этом руководстве описывается архитектура почтовой подсистемы фреймворка Joke и шаги, необходимые для реализации
собственного транспорта (например, SMTP или интеграции с внешним API).

## Архитектура почтового модуля

Почтовая система построена на принципе разделения ответственности:

1. **DTO (Data Transfer Objects):** Классы `Email` и `Address` отвечают за хранение данных письма. Они не знают ничего о
   способах доставки.
2. **Коллекции:** `HeadersCollection` управляет заголовками, обеспечивая их регистронезависимость, валидацию по
   стандартам RFC и защиту от инъекций.
3. **Утилиты:** `MimeConverter` отвечает за кодирование не-ASCII символов (кириллицы) в формат MIME Encoded-Word.
4. **Транспорт:** Реализация интерфейса `TransportInterface`, которая берет готовый объект `Email` и доставляет его
   адресату.

## Контракт транспорта

Любой транспорт должен реализовывать интерфейс `\Vasoft\Joke\Contract\Mail\TransportInterface`:

```php
interface TransportInterface
{
    /**
     * @throws MailException При ошибках отправки почты
     */
    public function send(Email $email): void;
}
```

## Пошаговая реализация

### Шаг 1: Создание класса конфигурации

Если вашему транспорту необходимы параметры рекомендую создать класс конфигурации приложения или конфигурации почтовой
системы вашего приложения. При этом рекомендую использовать класс `Vasoft\Joke\Config\AbstractConfig`, который
обеспечивает заморозку конфигурации при первом обращении.

```php
<?php
declare(strict_types=1);

namespace App\Mail\MailConfig;

use Vasoft\Joke\Config\AbstractConfig;

class MailConfig extends AbstractConfig
{
    public private(set) string $host = '';
    public private(set) string $port = '';

    public function setHost(string $host): mixed
    {
        $this->guard();
        $this->host = $host;
        
        return $this;
    }

    public function setPort(string $port): mixed
    {
        $this->guard();
        $this->port = $port;
        
        return $this;
    }
}
```

### Шаг 2: Создание класса транспорта

Создайте класс, реализующий `TransportInterface`. Например, `SmtpTransport`.

```php
<?php
declare(strict_types=1);

namespace App\Mail;

use App\Mail\MailConfig;
use Vasoft\Joke\Contract\Mail\TransportInterface;
use Vasoft\Joke\Mail\Email;
use Vasoft\Joke\Mail\MailException;

class SmtpTransport implements TransportInterface
{
    public function __construct(private readonly MailConfig $config){}

    public function send(Email $email): void
    {
        // Ваша логика подключения к SMTP и отправки команд
    }
}
```

### Шаг 3: Подготовка заголовков

Перед отправкой убедитесь, что в письме есть все необходимые базовые заголовки. Вызовите метод
`$email->ensureBaseHeaders()`. Он добавит `Content-Type` с указанием UTF-8, если это еще не сделано.

### Шаг 4: Работа с заголовками и кодированием

Для формирования корректных заголовков используйте встроенные утилиты фреймворка:

1. **Санитизация:** Вызовите `$email->headers->sanitize()`. Это удалит пустые значения и проверит заголовки на наличие
   управляющих символов (защита от Header Injection).
2. **MIME-кодирование:** Используйте `MimeConverter::encode($value, $name)` для каждого заголовка. Этот метод
   автоматически определит, нужна ли кодировка (для кириллицы), и разобьет длинные строки согласно RFC 2822.

> **Важно:** Не кодируйте заголовки вручную до вызова `sanitize()`, иначе валидатор может счесть служебные символы
> кодировки (например, `=?`) недопустимыми.

### Шаг 5: Обработка адресатов

Используйте методы рендеринга из `NativeTransport` как пример того, как правильно формировать строки для полей `To` и
`From`:

* Имена с кириллицей должны быть закодированы через `MimeConverter`.
* Email-адреса должны оставаться в чистом ASCII-виде.
* Специальные символы в именах должны быть экранированы или взяты в кавычки.

### Шаг 6: Доступность в контейнере

Если вы установили свой транспорт в конфигурации приложения - он будет доступен через сервис провайдер:

```php
use Vasoft\Joke\Container\ServiceContainer;
use Vasoft\Joke\Contract\Mail\TransportInterface;
// ...
// Доступ через контейнер зависимостей
public function action1(ServiceContainer $container): void{
    /** @var TransportInterface $transport */
    $transport = $container->get(TransportInterface::class);
}
// Доступ через автосвязывание параметров
public function action1(TransportInterface $transport): void{

}
// ...  
```

Если вам необходимо добавить транспорт в дополнение к транспорту приложения по умолчанию - можно зарегистрировать его в
сервис контейнере. Для этого можно использовать собственный сервис провайдер и зарегистрировать его в контейнере
зависимостей. (Подробнее в статье [Сервис-провайдеры](./providers.md))

## Рекомендации по безопасности

* Никогда не передавайте "сырые" данные от пользователя напрямую в заголовки без предварительной санитизации.
* Всегда используйте `Content-Transfer-Encoding` (например, `base64` или `quoted-printable`) для тела письма, чтобы
  гарантировать целостность данных при передаче через разные MTA.
* Проверяйте возвращаемые значения функций отправки и выбрасывайте `MailException` в случае неудачи.