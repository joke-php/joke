<?php

declare(strict_types=1);

namespace Vasoft\Joke\Contract\Container;

use Vasoft\Joke\Container\Exceptions\ContainerException;
use Vasoft\Joke\Container\Exceptions\ParameterResolveException;
use Vasoft\Joke\Container\Exceptions\ServiceNotFoundException;

interface DiContainerInterface
{
    /**
     * Возвращает экземпляр резолвера параметров.
     *
     * Гарантирует, что резолвер создаётся только один раз и не вызывает
     * рекурсивного разрешения зависимостей.
     */
    public function getParameterResolver(): ResolverInterface;

    /**
     * Регистрирует сервис как синглтон.
     *
     * Сервис будет создан один раз при первом обращении и переиспользоваться во всех последующих вызовах.
     * При замене ResolverInterface убедитесь, что передаёте текущий контейнер ($this) в конструктор резолвера.
     *
     * @param string                 $name    Имя сервиса (обычно интерфейс или абстрактный класс)
     * @param callable|object|string $service Определение сервиса:
     *                                        - строка с именем класса
     *                                        - callable (фабрика)
     *                                        - готовый объект
     */
    public function registerSingleton(string $name, callable|object|string $service): void;

    /**
     * Регистрирует псевдоним (алиас) для имени сервиса.
     *
     * После регистрации запрос сервиса по имени `$alias` будет эквивалентен
     * запросу по имени `$concrete`.
     *
     * @param string $alias    Псевдоним, под которым будет доступен сервис
     * @param string $concrete Имя сервиса, интерфейса или другого зарегистрированного имени,
     *                         на которое следует перенаправить запрос
     *
     * @throws ContainerException В случае ошибок
     */
    public function registerAlias(string $alias, string $concrete): static;

    /**
     * Получает экземпляр сервиса.
     *
     * Сначала ищет среди синглтонов, затем среди прототипов.
     * Возвращает null, если сервис не зарегистрирован.
     * В v2.0 будет бросать исключение вместо возврата null.
     *
     * @template T of object
     *
     * @param class-string<T>|string $name Имя сервиса
     *
     * @return ($name is class-string ? T : object) Экземпляр сервиса или null, если не найден
     *
     * @throws ParameterResolveException Если не удаётся разрешить зависимости
     * @throws ContainerException        В случае ошибок уровня контейнера
     * @throws ServiceNotFoundException  Если сервис не найден
     */
    public function get(string $name): object;

    /**
     * Проверяет наличие сервиса в контейнере без его создания.
     *
     * @param string $name Имя сервиса или алиас
     */
    public function has(string $name): bool;
}
