<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>{{ config('app.name') }}</title>
    <style>
        :root { --green: #0b8f3a; --ink: #111827; --muted: #52606d; --bg: #f3f7fa; --card: #fff; }
        @media (prefers-color-scheme: dark) { :root { --ink: #e5e7eb; --muted: #9ca3af; --bg: #0b1220; --card: #111a2b; } }
        * { box-sizing: border-box; }
        body { margin: 0; min-height: 100vh; display: grid; place-items: center; padding: 16px;
               background: var(--bg); color: var(--ink); font: 16px/1.5 system-ui, -apple-system, "Segoe UI", sans-serif; }
        main { max-width: 560px; width: 100%; background: var(--card); border-radius: 16px; padding: 32px;
               border-top: 4px solid var(--green); box-shadow: 0 10px 30px rgba(0,0,0,.06); }
        h1 { margin: 0 0 8px; font-size: 1.5rem; }
        p { margin: 0 0 16px; color: var(--muted); }
        dl { display: grid; grid-template-columns: auto 1fr; gap: 6px 16px; margin: 0; font-size: .9rem; }
        dt { color: var(--muted); }
        .ok { color: var(--green); font-weight: 600; }
    </style>
</head>
<body>
    <main>
        <h1>{{ config('app.name') }}</h1>
        <p>The new foundation is running. Screens arrive in the next PRs.</p>
        <dl>
            <dt>Environment</dt><dd>{{ app()->environment() }}</dd>
            <dt>Database</dt><dd class="ok">{{ $database }}</dd>
            <dt>Departments</dt><dd>{{ $departments }}</dd>
            <dt>Users</dt><dd>{{ $users }}</dd>
            <dt>Books</dt><dd>{{ $books }}</dd>
        </dl>
    </main>
</body>
</html>
