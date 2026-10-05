<?php

namespace Database\Factories;

use App\Enums\BookStatus;
use App\Enums\Level;
use App\Models\Book;
use App\Models\Department;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Book>
 */
class BookFactory extends Factory
{
    public function definition(): array
    {
        return [
            'title' => rtrim(fake()->sentence(fake()->numberBetween(2, 6)), '.'),
            'author' => fake()->name(),
            'description' => fake()->paragraph(),
            'department_id' => Department::factory(),
            'level' => fake()->randomElement(Level::cases()),
            'page_count' => fake()->numberBetween(20, 600),
        ];
    }

    public function uploadedBy(User $user): static
    {
        return $this->afterMaking(fn (Book $book) => $book->uploader_id = $user->id);
    }

    public function approved(): static
    {
        return $this->afterMaking(function (Book $book): void {
            $book->status = BookStatus::Approved;
            $book->approved_at = now();
        });
    }

    public function status(BookStatus $status): static
    {
        return $this->afterMaking(fn (Book $book) => $book->status = $status);
    }
}
