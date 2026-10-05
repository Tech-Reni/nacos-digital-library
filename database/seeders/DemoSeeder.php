<?php

namespace Database\Seeders;

use App\Enums\BookStatus;
use App\Enums\ElectionStatus;
use App\Enums\Level;
use App\Enums\Programme;
use App\Enums\Role;
use App\Models\Announcement;
use App\Models\Book;
use App\Models\Department;
use App\Models\Election;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Sample data for local development and testing only. Never runs in
 * production (see DatabaseSeeder).
 */
class DemoSeeder extends Seeder
{
    public const PASSWORD = 'Password1!';

    public function run(): void
    {
        if (User::query()->exists()) {
            $this->command->info('Demo data already present, skipping.');

            return;
        }

        $cs = Department::where('slug', 'computer-science')->firstOrFail();

        $accounts = [
            ['Ada Admin', 'F/HD/21/0000001', Role::Admin],
            ['Gbenga Governor', 'F/HD/22/0000002', Role::Governor],
            ['Chika CourseRep', 'F/ND/23/0000003', Role::CourseRep],
            ['Tobi Student', 'F/ND/24/0000004', Role::Student],
        ];

        $users = [];
        foreach ($accounts as [$name, $matric, $role]) {
            $users[$role->value] = User::factory()
                ->for($cs)
                ->role($role)
                ->create([
                    'fullname' => $name,
                    'matric_number' => $matric,
                    'email' => strtolower(explode(' ', $name)[0]).'@example.test',
                    'level' => $role === Role::Student ? Level::ND1 : Level::HND1,
                    'programme' => Programme::FullTime,
                    'password' => self::PASSWORD,
                ]);
        }

        $students = User::factory()->count(20)->for($cs)->create();
        $uploaders = $students->push($users['course_rep']);

        Book::factory()->count(36)->for($cs)->approved()
            ->sequence(fn ($sequence) => ['uploader_id' => $uploaders[$sequence->index % $uploaders->count()]->id])
            ->create();

        Book::factory()->count(5)->for($cs)->status(BookStatus::Pending)
            ->sequence(fn ($sequence) => ['uploader_id' => $uploaders[$sequence->index % $uploaders->count()]->id])
            ->create();

        Announcement::factory()->count(3)->create();

        $election = new Election(['title' => 'NACOS Executive Council Election']);
        $election->status = ElectionStatus::Draft;
        $election->save();

        foreach (['President', 'Vice President', 'General Secretary'] as $order => $title) {
            $position = $election->positions()->create(['title' => $title, 'sort_order' => $order]);

            foreach ($students->random(2) as $i => $candidate) {
                $position->candidates()->create([
                    'user_id' => $candidate->id,
                    'name' => $candidate->fullname,
                    'matric_number' => $candidate->matric_number,
                    'manifesto' => fake()->sentence(12),
                    'sort_order' => $i,
                ]);
            }
        }
    }
}
