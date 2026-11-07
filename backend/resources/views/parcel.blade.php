<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>Aveo Ox · {{ $delivery->business->name }}</title>
    <link rel="icon" href="{{ asset('favicon.png') }}">
    <style>
        :root { --bg: #ffffff; --card: #F4F8FB; --text: #14324A; --muted: #5E6E7B; --line: #DCE5EC; --gold: #F2B01E; --blue: #4DB6E8; }
        @media (prefers-color-scheme: dark) {
            :root { --bg: #0F2233; --card: #14324A; --text: #F4F8FB; --muted: #A9B8C4; --line: #24485F; }
        }
        * { box-sizing: border-box; }
        body { margin: 0; background: var(--bg); color: var(--text); font: 17px/1.45 -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; }
        main { max-width: 480px; margin: 0 auto; padding: 28px 20px 40px; }
        .logo { height: 30px; display: block; margin-bottom: 28px; }
        .hello { color: var(--muted); margin: 0 0 4px; }
        h1 { font-size: 26px; line-height: 1.2; margin: 0 0 10px; }
        .detail { margin: 0 0 22px; }
        .card { background: var(--card); border-radius: 18px; padding: 18px; }
        ol { list-style: none; margin: 0; padding: 0; }
        li { display: flex; align-items: center; gap: 12px; padding: 9px 0; color: var(--muted); }
        li.done { color: var(--text); font-weight: 600; }
        .dot { width: 14px; height: 14px; border-radius: 50%; border: 3px solid var(--line); flex: none; }
        li.done .dot { background: var(--gold); border-color: var(--gold); }
        .driver { margin-top: 14px; padding-top: 14px; border-top: 1px solid var(--line); }
        .from { margin-top: 22px; color: var(--muted); font-size: 15px; }
    </style>
</head>
<body>
<main>
    <img class="logo" src="{{ asset('images/logo-light.png') }}" alt="Aveo">
    <p class="hello">Hello {{ $firstName }}, your {{ $delivery->business->name }} order</p>
    <h1>{{ $headline }}</h1>
    @if ($detail)
        <p class="detail">{{ $detail }}</p>
    @endif

    @if ($steps)
        <div class="card">
            <ol>
                @foreach ($steps as $step)
                    <li @class(['done' => $step['done']])><span class="dot"></span>{{ $step['label'] }}</li>
                @endforeach
            </ol>
            @if ($driver)
                <p class="driver">{{ $driver }} is bringing it.</p>
            @endif
        </div>
    @endif

    <p class="from">
        Delivered by Aveo Ox for {{ $delivery->business->name }}@if ($delivery->reference), order {{ $delivery->reference }}@endif.
        Questions about the order itself go to {{ $delivery->business->name }}.
    </p>
</main>
</body>
</html>
