<?php

declare(strict_types=1);

namespace Vasoft\Joke\Http\Cookies;

use Vasoft\Joke\Config\AbstractConfig;
use Vasoft\Joke\Config\Exceptions\ConfigException;

/**
 * Конфигурация параметров по умолчанию для создания HTTP cookie.
 *
 * Этот класс определяет политики безопасности и области видимости, которые применяются
 * ко всем кукам, создаваемым через {@see CookieCollection}, если конкретные параметры
 * не были переопределены явно при вызове метода add().
 *
 * Принципы безопасности по умолчанию:
 *   Secure = true: Куки по умолчанию помечаются флагом Secure (передача только по HTTPS).
 *       Для локальной разработки на HTTP необходимо явно вызвать setSecure(false).
 *   HttpOnly = true: Доступ из JavaScript запрещен по умолчанию для защиты от XSS.
 *   SameSite = Lax: Баланс между защитой от CSRF и удобством использования.
 *
 * Класс поддерживает иммутабельность после завершения настройки.
 */
class CookieConfig extends AbstractConfig
{
    /**
     * Время жизни куки в секундах.
     *
     * Значение по умолчанию: 1 год (31536000 сек).
     * Устанавливает максимальный возраст (Max-Age) для персистентных кук.
     * Если установлено в null, кука становится сессионной.
     */
    public private(set) ?int $lifetime = 31536000;
    /**
     * Путь области видимости куки на сервере.
     *
     * Значение по умолчанию: '/'.
     * Кука будет доступна для всех путей текущего домена.
     * Может быть установлен в null, если требуется специфическое поведение.
     */
    public private(set) ?string $path = '/';
    /**
     * Домен области видимости куки.
     *
     * Значение по умолчанию: null.
     * Если null, кука привязывается только к текущему хосту (Host-only cookie) и не будет отправляться на поддомены.
     * Для распространения куки на все поддомены установите значение вида '.example.com'.
     */
    public private(set) ?string $domain = null;
    /**
     * Флаг обязательного использования защищенного соединения (HTTPS).
     *
     * Значение по умолчанию: true.
     *  - null: Флаг Secure устанавливается автоматически в зависимости от схемы текущего запроса.
     *  - true: Кука всегда помечается как Secure (только для HTTPS).
     *  - false: Кука никогда не помечается как Secure (для разработки по HTTP).
     * Если true, браузер откажется передавать куку по незашифрованному HTTP каналу.
     */
    public private(set) ?bool $secure = true;
    /**
     * Флаг запрета доступа к куке через JavaScript (document.cookie).
     *
     * Значение по умолчанию: true.
     * Рекомендуется всегда оставлять включенным для кук сессий и токенов для предотвращения кражи данных
     * через XSS-атаки.
     */
    public private(set) bool $httpOnly = true;
    /**
     * Политика ограничения отправки куки при кросс-сайтовых запросах (CSRF protection).
     *
     * Значение по умолчанию: SameSiteOption::Lax.
     * Позволяет отправлять куку при навигации на сайт (GET-запросы), но блокирует при потенциально
     * опасных операциях (POST, PUT) с других доменов.
     */
    public private(set) SameSiteOption $sameSite = SameSiteOption::Lax;

    /**
     * Устанавливает время жизни куки.
     *
     * @param null|int $lifetime время в секундах или null для сессионной куки
     *
     * @throws ConfigException Если конфигурация в режиме только для чтения
     */
    public function setLifetime(?int $lifetime): static
    {
        $this->guard();
        $this->lifetime = $lifetime;

        return $this;
    }

    /**
     * Устанавливает путь области видимости.
     *
     * @param null|string $path путь URL или null
     *
     * @throws ConfigException Если конфигурация в режиме только для чтения
     */
    public function setPath(?string $path): static
    {
        $this->guard();
        $this->path = $path;

        return $this;
    }

    /**
     * Устанавливает домен области видимости.
     *
     * Передача null сбрасывает домен к значению "Host-only" (кука видна только тому домену,
     * который её установил, и не передается на поддомены).
     *
     * @param null|string $domain Имя домена (например, 'example.com' или '.example.com') или null.
     *
     * @throws ConfigException Если конфигурация в режиме только для чтения
     */
    public function setDomain(?string $domain): static
    {
        $this->guard();
        $this->domain = $domain;

        return $this;
    }

    /**
     * Включает или выключает флаг безопасного соединения (Secure).
     *
     * По умолчанию включено (true). Отключение рекомендуется только для локальной разработки.
     *
     * @param ?bool $secure true для включения флага Secure, false для выключения, null - автоопределение
     *
     * @throws ConfigException Если конфигурация в режиме только для чтения
     */
    public function setSecure(?bool $secure): static
    {
        $this->guard();
        $this->secure = $secure;

        return $this;
    }

    /**
     * Включает или выключает флаг HttpOnly.
     *
     * Настоятельно рекомендуется держать включенным (true) для всех чувствительных кук.
     *
     * @param bool $httpOnly true для запрета доступа из JS, false для разрешения
     *
     * @throws ConfigException Если конфигурация в режиме только для чтения
     */
    public function setHttpOnly(bool $httpOnly): static
    {
        $this->guard();
        $this->httpOnly = $httpOnly;

        return $this;
    }

    /**
     * Устанавливает политику SameSite.
     *
     * @param SameSiteOption $sameSite одно из значений enum: Strict, Lax или None
     *
     * @throws ConfigException Если конфигурация в режиме только для чтения
     */
    public function setSameSite(SameSiteOption $sameSite): static
    {
        $this->guard();
        $this->sameSite = $sameSite;

        return $this;
    }
}
