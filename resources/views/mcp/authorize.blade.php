<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>Connect {{ $client->name }} · {{ config('app.name', 'HRMS') }}</title>
    <link rel="icon" href="/favicon.svg" type="image/svg+xml">
    <style>
        :root { --background: #f4f6f5; --foreground: #14212b; --card: #ffffff; --primary: #0e6b57; --primary-foreground: #ffffff; --muted: #56636d; --border: #dbe2df; --warning: #92600a; --warning-soft: #fbf0d6; }
        @media (prefers-color-scheme: dark) {
            :root { --background: #0d1415; --foreground: #e6ecea; --card: #151e20; --primary: #43b89c; --primary-foreground: #06201a; --muted: #96a4a1; --border: #253235; --warning: #e7b75b; --warning-soft: #2b2312; }
        }
        * { box-sizing: border-box; }
        body { margin: 0; min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 24px 16px; background: var(--background); color: var(--foreground); font-family: ui-sans-serif, system-ui, -apple-system, 'Segoe UI', Roboto, sans-serif; }
        .card { width: 100%; max-width: 440px; background: var(--card); border: 1px solid var(--border); border-radius: 12px; box-shadow: 0 12px 32px rgba(20, 33, 43, 0.08); padding: 28px; }
        .brand { font-weight: 700; font-size: 15px; letter-spacing: 0.04em; color: var(--primary); margin-bottom: 18px; }
        h1 { font-weight: 650; font-size: 22px; line-height: 1.25; margin: 0 0 8px; }
        p { margin: 0 0 14px; font-size: 14.5px; line-height: 1.55; color: var(--muted); }
        .who { display: flex; align-items: center; gap: 12px; background: var(--background); border: 1px solid var(--border); border-radius: 10px; padding: 12px 14px; margin: 16px 0; }
        .avatar { width: 40px; height: 40px; border-radius: 50%; background: var(--primary); color: var(--primary-foreground); display: flex; align-items: center; justify-content: center; font-weight: 600; font-size: 14px; flex-shrink: 0; }
        .who b { display: block; font-size: 15px; }
        .who small { color: var(--muted); font-size: 12.5px; }
        ul { margin: 0 0 18px; padding: 0 0 0 18px; color: var(--muted); font-size: 14px; line-height: 1.6; }
        .warn { font-size: 12.5px; color: var(--warning); background: var(--warning-soft); border-radius: 10px; padding: 10px 12px; margin-bottom: 18px; line-height: 1.5; }
        .actions { display: flex; gap: 10px; }
        form { flex: 1; margin: 0; }
        button { width: 100%; height: 44px; border-radius: 8px; font: 600 14.5px ui-sans-serif, system-ui, sans-serif; cursor: pointer; border: 1px solid var(--border); background: var(--card); color: var(--foreground); }
        button.primary { background: var(--primary); border-color: var(--primary); color: var(--primary-foreground); }
        button.primary:hover { filter: brightness(0.92); }
        button:disabled { opacity: 0.6; cursor: wait; }
        .foot { margin-top: 16px; font-size: 12px; color: var(--muted); text-align: center; }
        .foot a { color: var(--primary); }
    </style>
</head>
<body>
    @php
        $initials = collect(explode(' ', trim((string) $user->name)))->filter()->map(fn ($p) => mb_substr($p, 0, 1))->take(2)->implode('');
        $user->loadMissing('company', 'role');
        $roleLabel = $user->is_super_admin ? 'Super admin' : trim(($user->role?->name ?? 'User').($user->company ? ' · '.$user->company->name : ''));
    @endphp
    <div class="card">
        <div class="brand">{{ strtoupper(config('app.name', 'HRMS')) }}</div>

        <h1>Connect {{ $client->name }} to {{ config('app.name', 'HRMS') }}?</h1>
        <p>{{ $client->name }} wants to use {{ config('app.name', 'HRMS') }} on your behalf through the MCP connector.</p>

        <div class="who">
            <div class="avatar">{{ strtoupper($initials ?: 'H') }}</div>
            <div>
                <b>{{ $user->name }}</b>
                <small>{{ $user->email }} · {{ $roleLabel }}</small>
            </div>
        </div>

        <p style="margin-bottom:6px;color:var(--foreground);font-weight:500;">It will be able to, as you:</p>
        <ul>
            @if ($user->is_super_admin)
                <li>View, create and edit companies and change their status</li>
                <li>Read and change platform settings</li>
            @else
                <li>Use every area your role allows: {{ collect($user->permissionList())->map(fn ($p) => str($p)->before('.'))->unique()->implode(', ') ?: 'your profile only' }}</li>
                <li>Read and change company records, exactly as you can in the web app</li>
            @endif
        </ul>

        @php($redirectHost = parse_url((string) $request->redirect_uri, PHP_URL_HOST))
        @if ($redirectHost)
            <p style="font-size:13px;">After you approve, you will be sent back to <b style="color:var(--foreground)">{{ $redirectHost }}</b>. Only approve if that is the app you are connecting.</p>
        @endif

        <div class="warn">Changes are recorded in the audit log under your name. You can disconnect it any time from Settings → AI access.</div>

        <div class="actions">
            <form method="POST" action="{{ route('passport.authorizations.deny') }}">
                @csrf
                @method('DELETE')
                <input type="hidden" name="state" value="{{ $request->state }}">
                <input type="hidden" name="client_id" value="{{ $client->id }}">
                <input type="hidden" name="auth_token" value="{{ $authToken }}">
                <button type="submit">Cancel</button>
            </form>
            <form method="POST" action="{{ route('passport.authorizations.approve') }}" id="authorizeForm">
                @csrf
                <input type="hidden" name="state" value="{{ $request->state }}">
                <input type="hidden" name="client_id" value="{{ $client->id }}">
                <input type="hidden" name="auth_token" value="{{ $authToken }}">
                <button type="submit" class="primary" id="authorizeButton">Authorize</button>
            </form>
        </div>

        <div class="foot">Not you? <a href="{{ route('login') }}">Sign in with a different account</a></div>
    </div>
    <script>
        document.getElementById('authorizeForm').addEventListener('submit', function () {
            var b = document.getElementById('authorizeButton');
            b.disabled = true; b.textContent = 'Connecting…';
        });
    </script>
</body>
</html>
