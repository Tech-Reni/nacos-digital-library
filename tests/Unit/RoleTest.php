<?php

namespace Tests\Unit;

use App\Enums\Role;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class RoleTest extends TestCase
{
    #[Test]
    public function roles_are_ordered_by_privilege(): void
    {
        $this->assertTrue(Role::Admin->atLeast(Role::Governor));
        $this->assertTrue(Role::Governor->atLeast(Role::CourseRep));
        $this->assertTrue(Role::CourseRep->atLeast(Role::Student));
        $this->assertTrue(Role::Student->atLeast(Role::Student));

        $this->assertFalse(Role::Student->atLeast(Role::CourseRep));
        $this->assertFalse(Role::Governor->atLeast(Role::Admin));
    }
}
