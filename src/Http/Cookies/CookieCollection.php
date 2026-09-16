<?php

declare(strict_types=1);

namespace Vasoft\Joke\Http\Cookies;

use Vasoft\Joke\Http\Cookies\Exceptions\CookieException;

/**
 * Коллекция HTTP cookie, обеспечивающая уникальность записей на основе комбинации имени, домена и пути.
 *
 * Реализует интерфейс IteratorAggregate для возможности обхода коллекции в цикле foreach.
 * Внутреннее хранение организовано в виде ассоциативного массива, где ключ формируется
 * как композитный индекс: "name#domain#path".
 *
 * @implements \IteratorAggregate<string, Cookie>
 */
class CookieCollection implements \IteratorAggregate
{
    /** @var array<string, Cookie> Ассоциативный массив кук, где ключ — композитный индекс. */
    private array $cookies = [];

    /**
     * @param CookieConfig $config             Конфигурация cookie
     * @param bool         $isSecureConnection выполняется защищенное соединение или нет
     */
    public function __construct(private readonly CookieConfig $config, private readonly bool $isSecureConnection) {}

    /**
     * Возвращает итератор для обхода коллекции кук.
     *
     * @return \Traversable<string, Cookie> итератор, возвращающий пары ключ-Cookie
     */
    public function getIterator(): \Traversable
    {
        return new \ArrayIterator($this->cookies);
    }

    /**
     * Добавляет новую куку в коллекцию или перезаписывает существующую с той же сигнатурой.
     *
     * Создает объект {@see Cookie}, автоматически подставляя значения по умолчанию из {@see CookieConfig}
     * для всех параметров, которые не были явно переданы (равны null).
     *
     * Уникальность записи гарантируется тройкой параметров: Name + Domain + Path.
     *
     * @param string              $name     Имя куки. Обязательный параметр.
     * @param string              $value    Значение куки. Будет URL-кодировано внутри объекта Cookie.
     * @param null|int            $lifetime Время жизни куки в секундах.
     *                                      Если null, используется значение из конфига.
     *                                      Если 0 или null (в зависимости от конфига), кука становится сессионной.
     * @param null|string         $path     Путь области видимости куки на сервере.
     *                                      Если null, используется значение из конфига (обычно '/').
     * @param null|string         $domain   Домен области видимости куки.
     *                                      Если null, используется значение из конфига.
     *                                      Если в конфиге тоже null, кука привязывается только к текущему хосту (Host-only).
     * @param null|bool           $secure   Флаг передачи куки только по HTTPS.
     *                                      Если null, используется значение из конфига.
     *                                      В рантайме может быть переопределено менеджером кук в зависимости от типа соединения.
     * @param null|bool           $httpOnly Флаг запрета доступа к куке через JavaScript (защита от XSS).
     *                                      Если null, используется значение из конфига (рекомендуется true).
     * @param null|SameSiteOption $sameSite Политика ограничения отправки куки при кросс-сайтовых запросах.
     *                                      Если null, используется значение из конфига (обычно Lax).
     *
     * @throws CookieException если переданные параметры или значения из конфигурации не проходят валидацию
     *                         внутри конструктора {@see Cookie}
     */
    public function add(
        string $name,
        string $value,
        ?int $lifetime = null,
        ?string $path = null,
        ?string $domain = null,
        ?bool $secure = null,
        ?bool $httpOnly = null,
        ?SameSiteOption $sameSite = null,
    ): void {
        $cookie = new Cookie(
            $name,
            $value,
            $lifetime ?? $this->config->lifetime,
            $path ?? $this->config->path,
            $domain ?? $this->config->domain,
            $secure ?? $this->config->secure ?? $this->isSecureConnection,
            $httpOnly ?? $this->config->httpOnly,
            $sameSite ?? $this->config->sameSite,
        );
        $index = $this->getIndex($cookie->name, $cookie->domain, $cookie->path);
        $this->cookies[$index] = $cookie;
    }

    /**
     * Планирует удаление куки путем установки заглушки с истекшим временем жизни.
     *
     * ВАЖНО: Для успешного удаления браузером параметры $domain и $path должны абсолютно точно
     * совпадать с теми, с которыми кука была изначально установлена.
     *
     * Если параметры $domain или $path не указаны (null), будут использованы значения по умолчанию из
     * конфигурации. Это сработает корректно только если удаляемая кука также была создана с этими
     * настройками по умолчанию
     *
     * Метод создает объект Cookie с пустым значением, временем жизни 0 и теми же параметрами домена/пути,
     * затем сохраняет его в коллекцию под соответствующим ключом.
     *
     * @param string  $name   имя удаляемой куки
     * @param ?string $domain домен удаляемой куки (должен точно совпадать с оригиналом)
     * @param ?string $path   путь удаляемой куки (должен точно совпадать с оригиналом)
     *
     * @throws CookieException
     */
    public function remove(string $name, ?string $domain = null, ?string $path = null): void
    {
        $finalDomain = $domain ?? $this->config->domain;
        $finalPath = $path ?? $this->config->path;
        $index = $this->getIndex($name, $finalDomain, $finalPath);
        $this->cookies[$index] = new Cookie($name, '', 0, $finalDomain, $finalPath);
    }

    /**
     * Формирует уникальный композитный ключ для хранения куки.
     *
     * Использует символ '#' как разделитель между компонентами.
     *
     * @param string  $name   имя куки
     * @param ?string $domain домен куки
     * @param ?string $path   путь куки
     *
     * @return string сформированный ключ вида "name#domain#path"
     */
    private function getIndex(string $name, ?string $domain, ?string $path): string
    {
        return $name . '#' . ($domain ?? '') . '#' . ($path ?? '');
    }
}
