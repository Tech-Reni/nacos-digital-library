<?php

namespace App\Enums;

/**
 * Account roles, ordered from least to most privileged.
 */
enum Role: string
{
    case Student = 'student';
    case CourseRep = 'course_rep';
    case Governor = 'governor';
    case Admin = 'admin';

    public function label(): string
    {
        return match ($this) {
            self::Student => 'Student',
            self::CourseRep => 'Course Rep',
            self::Governor => 'Governor',
            self::Admin => 'Admin',
        };
    }

    public function rank(): int
    {
        return match ($this) {
            self::Student => 0,
            self::CourseRep => 1,
            self::Governor => 2,
            self::Admin => 3,
        };
    }

    /**
     * Whether this role has at least the privileges of the given role.
     */
    public function atLeast(self $role): bool
    {
        return $this->rank() >= $role->rank();
    }
}
