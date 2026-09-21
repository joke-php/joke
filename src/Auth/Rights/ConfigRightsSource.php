<?php

namespace Vasoft\Joke\Auth\Rights;

use Vasoft\Joke\Contract\Auth\RightsSourceInterface;
use Vasoft\Joke\Contract\Auth\UserInterface;

class ConfigRightsSource implements RightsSourceInterface
{

    public function getRights(UserInterface $user): array
    {
        return [];
    }
}