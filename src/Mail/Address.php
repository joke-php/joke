<?php

declare(strict_types=1);

namespace Vasoft\Joke\Mail;

readonly class Address implements \Stringable
{
    /**
     * @param non-empty-string $email
     */
    public function __construct(
        public string $email,
        public ?string $name = null,
    ) {
        // @todo добавить проверку
    }

    public function __toString(): string
    {
        if (null === $this->name) {
            return $this->email;
        }

        $escapedName = addcslashes($this->name, '"');

        return sprintf('"%s" <%s>', $escapedName, $this->email);
    }
}
