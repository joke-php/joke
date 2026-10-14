<?php

declare(strict_types=1);

namespace Vasoft\Joke\Tests\Fixtures\Http\RateLimiter;

use Vasoft\Joke\Contract\Http\RateLimiter\StorageInterface;

class ArrayStorage implements StorageInterface
{
    private array $data = [];
    private int $time;

    public function __construct(
        ?int $time = null,
        private readonly int $value = 0,
        private readonly bool $exception = false,
    ) {
        $this->time = $time ?? time();
    }

    public function increment(string $key, int $ttl = 3600): int
    {
        if ($this->exception) {
            throw new \Exception('Test exception');
        }

        $current = (int) ($this->get($key) ?? $this->value);
        $newValue = $current + 1;

        $this->data[$key] = [
            'value' => (string) $newValue,
            'expiresAt' => $this->time + $ttl,
        ];

        return $newValue;
    }

    private function get(string $key): ?string
    {
        $entry = $this->data[$key] ?? null;
        if (null === $entry) {
            return null;
        }

        if (null !== $entry['expiresAt'] && $entry['expiresAt'] <= $this->time) {
            unset($this->data[$key]);

            return null;
        }

        return $entry['value'];
    }
}
