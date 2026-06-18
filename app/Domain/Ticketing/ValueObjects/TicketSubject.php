<?php

declare(strict_types=1);

namespace App\Domain\Ticketing\ValueObjects;

use InvalidArgumentException;

/**
 * A validated ticket subject line. Validates in the constructor — if built,
 * it is within bounds and non-blank.
 */
final readonly class TicketSubject
{
    public const int MIN_LENGTH = 3;

    public const int MAX_LENGTH = 160;

    public string $value;

    public function __construct(string $value)
    {
        $trimmed = trim($value);
        $length = mb_strlen($trimmed);

        if ($length < self::MIN_LENGTH) {
            throw new InvalidArgumentException(
                'Subject must be at least '.self::MIN_LENGTH.' characters.'
            );
        }

        if ($length > self::MAX_LENGTH) {
            throw new InvalidArgumentException(
                'Subject must not exceed '.self::MAX_LENGTH.' characters.'
            );
        }

        $this->value = $trimmed;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
