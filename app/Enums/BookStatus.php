<?php

namespace App\Enums;

enum BookStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case ChangesRequested = 'changes_requested';
    case Rejected = 'rejected';
    case Archived = 'archived';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending review',
            self::Approved => 'Approved',
            self::ChangesRequested => 'Changes requested',
            self::Rejected => 'Rejected',
            self::Archived => 'Archived',
        };
    }
}
