<x-filament-panels::page>
    @php($day = $this->day())
    @php($runs = $this->runs())

    <div style="display: flex; align-items: center; gap: 12px; flex-wrap: wrap;">
        <label for="day" style="font-weight: 600;">Day</label>
        <input id="day" type="date" wire:model.live="date" value="{{ $day->toDateString() }}"
               style="border: 1px solid #d5d1c7; border-radius: 8px; padding: 6px 10px; background: transparent; color: inherit;">
        <span>{{ $day->format('l j F Y') }}. {{ $runs->sum(fn ($row) => $row['riders']->where('absent', false)->count()) }} riders expected on {{ $runs->count() }} {{ \Illuminate\Support\Str::plural('run', $runs->count()) }}.</span>
    </div>

    @forelse ($runs as $row)
        @php($run = $row['run'])
        @php($expected = $row['riders']->where('absent', false)->count())

        <x-filament::section>
            <x-slot name="heading">
                {{ $run->departureTime() }} &middot; {{ $run->route->name }} &middot; {{ $run->direction->label() }}
            </x-slot>
            <x-slot name="description">
                {{ $run->driver->name }} ({{ $run->driver->phone }}) &middot;
                {{ $run->vehicle->registration_number }}, {{ $run->vehicle->colour }}
                @if ($run->driver->mates->where('is_active', true)->isNotEmpty())
                    &middot; Mate: {{ $run->driver->mates->where('is_active', true)->map(fn ($mate) => "{$mate->name} ({$mate->phone})")->join(', ') }}
                @endif
                &middot; {{ $expected }} of {{ $run->seats_held }} seats expected
            </x-slot>

            @if ($row['riders']->isEmpty())
                <p>No riders on this run today.</p>
            @else
                <table style="width: 100%; border-collapse: collapse; text-align: left;">
                    <thead>
                        <tr>
                            <th style="padding: 6px 8px;">Time</th>
                            <th style="padding: 6px 8px;">Stop</th>
                            <th style="padding: 6px 8px;">Rider</th>
                            <th style="padding: 6px 8px;">Phone</th>
                            <th style="padding: 6px 8px;">Today</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($row['riders'] as $rider)
                            <tr style="border-top: 1px solid rgba(128,128,128,.25); {{ $rider['absent'] ? 'opacity: .55;' : '' }}">
                                <td style="padding: 6px 8px;">{{ $rider['time'] }}</td>
                                <td style="padding: 6px 8px;">{{ $rider['stop'] }}</td>
                                <td style="padding: 6px 8px;">{{ $rider['name'] }}</td>
                                <td style="padding: 6px 8px;">{{ $rider['phone'] }}</td>
                                <td style="padding: 6px 8px;">{{ $rider['absent'] ? 'Not riding' : 'Riding' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </x-filament::section>
    @empty
        <x-filament::section>
            No runs go out on {{ $day->format('l j F') }}. Add one under Runs and capacity.
        </x-filament::section>
    @endforelse
</x-filament-panels::page>
