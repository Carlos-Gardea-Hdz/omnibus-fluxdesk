<?php

declare(strict_types=1);

namespace App\Domain\Ticketing\Enums;

/**
 * Allow-list of Flux palette tokens a category may use for its badge.
 *
 * Constraining the colour to a backed enum (never a free string) means the
 * `color=` prop fed to a Flux badge can never inject markup or an arbitrary
 * class — the picker only ever offers these tokens.
 */
enum CategoryColor: string
{
    case Zinc = 'zinc';
    case Red = 'red';
    case Orange = 'orange';
    case Amber = 'amber';
    case Yellow = 'yellow';
    case Lime = 'lime';
    case Green = 'green';
    case Emerald = 'emerald';
    case Teal = 'teal';
    case Cyan = 'cyan';
    case Sky = 'sky';
    case Blue = 'blue';
    case Indigo = 'indigo';
    case Violet = 'violet';
    case Purple = 'purple';
    case Fuchsia = 'fuchsia';
    case Pink = 'pink';
    case Rose = 'rose';

    /**
     * The Flux palette token. Safe to interpolate into a `color=` prop because
     * it can only ever be one of the cases above.
     */
    public function token(): string
    {
        return $this->value;
    }

    /**
     * Title-cased label for the colour picker (e.g. "Sky", "Rose").
     */
    public function display(): string
    {
        return ucfirst($this->value);
    }
}
