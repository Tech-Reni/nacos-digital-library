<?php

namespace App\Enums;

enum Level: string
{
    case ND1 = 'ND1';
    case ND2 = 'ND2';
    case ND3 = 'ND3';
    case HND1 = 'HND1';
    case HND2 = 'HND2';
    case HND3 = 'HND3';

    public function label(): string
    {
        return $this->value;
    }
}
