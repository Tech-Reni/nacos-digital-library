<?php

namespace App\Enums;

enum ReviewAction: string
{
    case Approved = 'approved';
    case ChangesRequested = 'changes_requested';
    case Rejected = 'rejected';

    public function resultingStatus(): BookStatus
    {
        return match ($this) {
            self::Approved => BookStatus::Approved,
            self::ChangesRequested => BookStatus::ChangesRequested,
            self::Rejected => BookStatus::Rejected,
        };
    }
}
