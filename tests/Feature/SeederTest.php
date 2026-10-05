<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Book;
use App\Models\Department;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SeederTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function production_seeding_only_creates_reference_data(): void
    {
        $this->app['env'] = 'production';

        // Invoke directly: `db:seed` would stop at its production confirmation prompt.
        $this->app->make(DatabaseSeeder::class)->setContainer($this->app)->__invoke();

        $this->assertSame(3, Department::count());
        $this->assertSame(0, User::count());
        $this->assertSame(0, Book::count());
    }

    #[Test]
    public function demo_seeding_creates_one_account_per_role(): void
    {
        $this->seed(DatabaseSeeder::class);

        foreach (Role::cases() as $role) {
            $this->assertTrue(User::where('role', $role)->exists(), "Missing {$role->value} account");
        }

        $admin = User::where('role', Role::Admin)->firstOrFail();
        $this->assertTrue(Hash::check(DemoSeeder::PASSWORD, $admin->password));
        $this->assertGreaterThan(0, Book::approved()->count());
    }

    #[Test]
    public function seeding_twice_is_safe(): void
    {
        $this->seed(DatabaseSeeder::class);
        $users = User::count();

        $this->seed(DatabaseSeeder::class);

        $this->assertSame($users, User::count());
        $this->assertSame(3, Department::count());
    }
}
