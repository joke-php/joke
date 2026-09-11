<?php

declare(strict_types=1);

namespace Vasoft\Joke;

function triggerDeprecation(string $oldClass, string $newClass): void
{
    @trigger_error(
        sprintf(
            'The class "%s" is deprecated and will be removed in v3.0.0. Use "%s" instead.',
            $oldClass,
            $newClass,
        ),
        E_USER_DEPRECATED,
    );
}
