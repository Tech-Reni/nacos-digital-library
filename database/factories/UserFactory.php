<?php

namespace Database\Factories;

use App\Enums\Level;
use App\Enums\Programme;
use App\Enums\Role;
use App\Enums\UserStatus;
use App\Models\Department;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    protected static ?string $password;

    public function definition(): array
    {
        $level = fake()->randomElement(Level::cases());
        $type = str_starts_with($level->value, 'HND') ? 'HD' : 'ND';

        return [
            'fullname' => fake()->name(),
            'matric_number' => sprintf('F/%s/%02d/%07d', $type, fake()->numberBetween(20, 25), fake()->unique()->numberBetween(1000000, 9999999)),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'department_id' => Department::factory(),
            'level' => $level,
            'programme' => fake()->randomElement(Programme::cases()),
            'password' => static::$password ??= Hash::make('Password1!'),
            'remember_token' => Str::random(10),
        ];
    }

    public function role(Role $role): static
    {
        return $this->afterMaking(fn (User $user) => $user->role = $role);
    }

    public function suspended(): static
    {
        return $this->afterMaking(fn (User $user) => $user->status = UserStatus::Suspended);
    }

    public function unverified(): static
    {
        return $this->state(fn () => ['email_verified_at' => null]);
    }
}
