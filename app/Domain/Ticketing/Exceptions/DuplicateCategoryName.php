<?php

declare(strict_types=1);

namespace App\Domain\Ticketing\Exceptions;

use RuntimeException;

/**
 * Thrown when a category name collides (case-insensitively) with an existing
 * one. Fails loud (project law); the presentation layer catches it and renders
 * an inline field error. The DB `unique('name')` constraint is the final guard.
 */
final class DuplicateCategoryName extends RuntimeException
{
    public static function for(string $name): self
    {
        return new self("A category named [{$name}] already exists.");
    }
}
