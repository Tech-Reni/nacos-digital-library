<?php

namespace App\Enums;

enum Programme: string
{
    case FullTime = 'full_time';
    case PartTime = 'part_time';
    case Codfel = 'codfel';

    public function label(): string
    {
        return match ($this) {
            self::FullTime => 'Full-time',
            self::PartTime => 'Part-time',
            self::Codfel => 'CODFEL',
        };
    }
}
