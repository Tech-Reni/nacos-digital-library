<?php

namespace Database\Seeders;

use App\Models\Department;
use Illuminate\Database\Seeder;

/**
 * Reference data. Safe to run in every environment, any number of times.
 */
class DepartmentSeeder extends Seeder
{
    public function run(): void
    {
        $departments = [
            ['slug' => 'computer-science', 'name' => 'Computer Science', 'is_active' => true],
            ['slug' => 'mass-communication', 'name' => 'Mass Communication', 'is_active' => false],
            ['slug' => 'accountancy', 'name' => 'Accountancy', 'is_active' => false],
        ];

        foreach ($departments as $department) {
            Department::updateOrCreate(['slug' => $department['slug']], $department);
        }
    }
}
