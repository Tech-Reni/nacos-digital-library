<?php

use Illuminate\Support\Facades\Schedule;

/*
| Shared hosting has no long-running queue worker. Instead the cron runs
| `schedule:run` every minute, and this drains whatever jobs are waiting,
| stopping before the next minute's run starts.
*/
Schedule::command('queue:work --stop-when-empty --max-time=50 --tries=3')
    ->everyMinute()
    ->withoutOverlapping()
    ->runInBackground();

Schedule::command('queue:prune-failed --hours=168')->daily();
