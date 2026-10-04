<x-filament-panels::page>
    @php($day = $this->day())
    @php($runs = $this->runs())

    <x-admin.day-picker model="date" :value="$day->toDateString()">
        {{ $day->format('l j F Y') }}.
        {{ $runs->sum(fn ($row) => $row['riders']->where('absent', false)->count()) }} riders expected on
        {{ $runs->count() }} {{ \Illuminate\Support\Str::plural('run', $runs->count()) }}.
    </x-admin.day-picker>

    @forelse ($runs as $row)
        @php($run = $row['run'])
        @php($mates = $run->driver->mates->where('is_active', true))

        <x-filament::section>
            <x-slot name="heading">
                {{ $run->departureTime() }} &middot; {{ $run->route->name }} &middot; {{ $run->direction->label() }}
            </x-slot>
            <x-slot name="description">
                {{ $run->driver->name }} ({{ $run->driver->phone }}) &middot;
                {{ $run->vehicle->registration_number }}, {{ $run->vehicle->colour }}
                @if ($mates->isNotEmpty())
                    &middot; Mate: {{ $mates->map(fn ($mate) => "{$mate->name} ({$mate->phone})")->join(', ') }}
                @endif
                &middot; {{ $row['riders']->where('absent', false)->count() }} of {{ $run->seats_held }} seats expected
            </x-slot>

            @if ($row['riders']->isEmpty())
                <p>No riders on this run today.</p>
            @else
                <x-admin.table :headers="['Time', 'Stop', 'Rider', 'Phone', 'Today']">
                    @foreach ($row['riders'] as $rider)
                        <x-admin.row :muted="$rider['absent']">
                            <td>{{ $rider['time'] }}</td>
                            <td>{{ $rider['stop'] }}</td>
                            <td>{{ $rider['name'] }}</td>
                            <td>{{ $rider['phone'] }}</td>
                            <td>{{ $rider['absent'] ? 'Not riding' : 'Riding' }}</td>
                        </x-admin.row>
                    @endforeach
                </x-admin.table>
            @endif
        </x-filament::section>
    @empty
        <x-filament::section>
            No runs go out on {{ $day->format('l j F') }}. Add one under Runs and capacity.
        </x-filament::section>
    @endforelse
</x-filament-panels::page>
