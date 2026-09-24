<?php

declare(strict_types=1);

namespace Vasoft\Joke\Auth\Rights;

use Vasoft\Joke\Auth\AuthConfig;
use Vasoft\Joke\Config\Exceptions\ConfigException;
use Vasoft\Joke\Container\Exceptions\ParameterResolveException;
use Vasoft\Joke\Container\ServiceContainer;
use Vasoft\Joke\Contract\Auth\RightsCheckerInterface;
use Vasoft\Joke\Contract\Auth\UserInterface;
use Vasoft\Joke\Contract\Auth\RightsSourceInterface;

/**
 * Проверщик прав доступа с гибридной системой кэширования.
 *
 * Особенности реализации:
 * 1. Ленивая инициализация источников прав (создаются только при первом обращении).
 * 2. Кэширование результатов проверки для каждого пользователя (избегает повторных запросов).
 * 3. Ранний выход: проверка останавливается сразу после нахождения права.
 * 4. Отрицательное кэширование: запоминает отсутствие права, чтобы не искать его снова.
 *
 * @see RightsCheckerInterface
 * @see AuthConfig
 */
class RightsChecker implements RightsCheckerInterface
{
    /**
     * Определения источников из конфигурации.
     *
     * @var list<callable|class-string<RightsSourceInterface>|RightsSourceInterface>
     */
    private array $sourceDefinition;
    /**
     * Кэш созданных экземпляров источников.
     * Ключ: индекс в массиве определений.
     *
     * @var list<RightsSourceInterface>
     */
    private array $sources = [];
    /**
     * Кэш прав для авторизованных пользователей.
     * Ключ: ID пользователя, Значение: плоский список прав.
     *
     * @var array<int|string, array<string,bool>>
     */
    private array $userRights = [];
    /**
     * Кэш прав для анонимных пользователей.
     *
     * @var array<string,bool>
     */
    private array $anonymousRights = [];

    public function __construct(
        AuthConfig $config,
        private readonly ServiceContainer $container,
    ) {
        $this->sourceDefinition = $config->rightSources;
    }

    /**
     * {@inheritDoc}
     *
     * Сначала проверяет локальный кэш. При отсутствии данных опрашивает источники
     * последовательно до первого совпадения или исчерпания списка.
     *
     * @throws ConfigException           Если компонент не реализует интерфейс
     * @throws ParameterResolveException При ошибках связывания параметров
     */
    public function can(UserInterface $user, string $module, string $right): bool
    {
        $expected = $module . ':' . $right;
        if (null === $user->id && array_key_exists($expected, $this->anonymousRights)) {
            return $this->anonymousRights[$expected];
        }
        if (
            null !== $user->id
            && array_key_exists($user->id, $this->userRights)
            && array_key_exists($expected, $this->userRights[$user->id])
        ) {
            return $this->userRights[$user->id][$expected];
        }

        foreach ($this->sourceDefinition as $index => $definition) {
            $source = $this->getSource($index, $definition);
            $rights = array_fill_keys($source->getRights($user), true);
            $this->rightsAppend($user, $rights);
            if (array_key_exists($expected, $rights)) {
                return $rights[$expected];
            }
        }
        $this->rightsAppend($user, [$expected => false]);

        return false;
    }

    /**
     * Получает экземпляр источника, используя кэш инициализации.
     *
     * @param int                                                                $index            Индекс источника
     * @param callable|class-string<RightsSourceInterface>|RightsSourceInterface $sourceDefinition Определение
     *
     * @throws ConfigException           Если компонент не реализует интерфейс
     * @throws ParameterResolveException При ошибках связывания параметров
     */
    private function getSource(
        int $index,
        callable|string|RightsSourceInterface $sourceDefinition,
    ): RightsSourceInterface {
        if (isset($this->sources[$index])) {
            return $this->sources[$index];
        }
        $source = $this->resolveSource($sourceDefinition);

        $this->sources[$index] = $source;

        return $source;
    }

    /**
     * Добавляет полученные права в соответствующий кэш пользователя.
     *
     * @param array<string,bool> $rights
     */
    private function rightsAppend(UserInterface $user, array $rights): void
    {
        if (!$user->authorized) {
            $this->anonymousRights = [...$this->anonymousRights, ...$rights];

            return;
        }
        if (array_key_exists($user->id, $this->userRights)) {
            $this->userRights[$user->id] = [...$this->userRights[$user->id], ...$rights];

            return;
        }
        $this->userRights[$user->id] = $rights;
    }

    /**
     * Возвращает экземпляр источника прав на основе его определения.
     *
     * @throws ConfigException           Если полученный объект не является источником прав
     * @throws ParameterResolveException При ошибках создания через контейнер
     */
    private function resolveSource(string|callable|RightsSourceInterface $definition): RightsSourceInterface
    {
        if ($definition instanceof RightsSourceInterface) {
            return $definition;
        }
        // @phpstan-ignore argument.templateType
        $source = $this->container->make($definition);

        if (!$source instanceof RightsSourceInterface) {
            throw new ConfigException(
                'Right source should implement "Vasoft\Joke\Contract\Auth\RightsSourceInterface".',
            );
        }

        return $source;
    }
}
