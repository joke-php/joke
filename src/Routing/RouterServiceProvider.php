<?php

declare(strict_types=1);

namespace Vasoft\Joke\Routing;

use Vasoft\Joke\Application\ApplicationConfig;
use Vasoft\Joke\Support\FileSystem;
use Vasoft\Joke\Container\ServiceContainer;
use Vasoft\Joke\Provider\AbstractProvider;

class RouterServiceProvider extends AbstractProvider
{
    public function __construct(
        private readonly ServiceContainer $serviceContainer,
        private readonly ApplicationConfig $applicationConfig,
    ) {}

    public function register(): void
    {
        // Empty body
    }

    public function boot(): void
    {
        /** @var FileSystem $pathNormalize */
        $pathNormalize = $this->serviceContainer->get(FileSystem::class);
        $router = $this->serviceContainer->getRouter();
        $router->addAutoGroups([StdGroup::WEB->value]);
        $file = $pathNormalize->normalizeFile($this->applicationConfig->getFileRoutes());
        $pathNormalize->includeFile($file, ['router' => $router]);
        $router->cleanAutoGroups();
    }

    public function provides(): array
    {
        return [];
    }

    public function requires(): array
    {
        return [];
    }
}
