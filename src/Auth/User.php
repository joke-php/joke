<?php

declare(strict_types=1);

namespace Vasoft\Joke\Auth;

use Vasoft\Joke\Contract\Auth\UserInterface;

class User implements UserInterface
{
    public bool $authorized {
        get {
            return $this->authorized;
        }
    }
    public int|string $id {
        get {
            return $this->id;
        }
    }
    public function can(string $module, string $right): bool
    {
        return true;
    }
}
