<?php

use App\Models\Book;
use App\Models\Department;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

// Temporary landing page proving the stack works end to end. Replaced by the
// real home/dashboard in the design-system and auth PRs.
Route::get('/', fn () => view('foundation', [
    'database' => DB::connection()->getDriverName().' connected',
    'departments' => Department::count(),
    'users' => User::count(),
    'books' => Book::count(),
]));
