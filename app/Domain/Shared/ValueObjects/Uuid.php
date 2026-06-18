<?php

declare(strict_types=1);

namespace App\Domain\Shared\ValueObjects;

use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * A UUIDv7 identifier. UUIDv7 is chronologically sortable (project law) and is
 * the only UUID version permitted for new identifiers.
 *
 * Validates in the constructor — if the object exists, the value is a valid UUID.
 */
final readonly class Uuid
{
    public function __construct(public string $value)
    {
        if (! Str::isUuid($value)) {
            throw new InvalidArgumentException("Invalid UUID: [{$value}].");
        }
    }

    /**
     * Mint a fresh, chronologically sortable UUIDv7.
     */
    public static function generate(): self
    {
        return new self((string) Str::uuid7());
    }

    public static function fromString(string $value): self
    {
        return new self($value);
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
