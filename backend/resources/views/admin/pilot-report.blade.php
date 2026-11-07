<x-filament-panels::page>
    @php($now = $this->now())
    @php($weeks = $this->weeks())
    @php($share = fn (int $part, int $whole) => \App\Admin\Pages\PilotReport::share($part, $whole))

    <x-filament::section heading="Today">
        <x-admin.table :headers="['Riders with a running plan', 'Seats filled', 'Paying by direct debit', 'Applications waiting', 'Open support cases', 'Paid but not activated']">
            <x-admin.row>
                <td>{{ $now['riders_riding'] }}</td>
                <td>{{ $share($now['seats_taken'], $now['seats_held']) }}</td>
                <td>{{ $now['on_direct_debit'] }}</td>
                <td>{{ $now['applications_waiting'] }}</td>
                <td>{{ $now['open_cases'] }}</td>
                <td>{{ $now['paid_not_activated'] }}</td>
            </x-admin.row>
        </x-admin.table>
    </x-filament::section>

    <x-filament::section heading="Riders and money" description="Weeks run Monday to Sunday. The top row is this week so far.">
        <x-admin.table :headers="['Week', 'Riders with a plan', 'New applications', 'Money in', 'Payments failed', 'Renewed when their plan ended']">
            @foreach ($weeks as $week)
                <x-admin.row>
                    <td>{{ $week['label'] }}</td>
                    <td>{{ $week['riders'] }}</td>
                    <td>{{ $week['applications'] }}</td>
                    <td>{{ \App\Support\Money::format($week['money_in']) }}</td>
                    <td>{{ $week['payments_failed'] }}</td>
                    <td>{{ $share($week['renewed'], $week['ended']) }}</td>
                </x-admin.row>
            @endforeach
        </x-admin.table>
    </x-filament::section>

    <x-filament::section heading="The service" :description="'A trip is on time if it started within '.config('aveo.report.on_time_minutes').' minutes of its departure time. Trips due is worked out from the runs as they are today.'">
        <x-admin.table :headers="['Week', 'Trips run', 'Started on time', 'Riders picked up', 'Not at the stop', 'Support cases', 'Incidents']">
            @foreach ($weeks as $week)
                <x-admin.row>
                    <td>{{ $week['label'] }}</td>
                    <td>{{ $share($week['trips_run'], $week['trips_due']) }}</td>
                    <td>{{ $share($week['trips_on_time'], $week['trips_run']) }}</td>
                    <td>{{ $share($week['picked_up'], $week['picked_up'] + $week['missed']) }}</td>
                    <td>{{ $week['missed'] }}</td>
                    <td>{{ $week['cases'] }}</td>
                    <td>{{ $week['incidents'] }}</td>
                </x-admin.row>
            @endforeach
        </x-admin.table>
    </x-filament::section>

    <x-filament::section heading="Messages sent" description="Every SMS costs money. App notifications are free.">
        <x-admin.table :headers="['Week', 'SMS', 'App notifications']">
            @foreach ($weeks as $week)
                <x-admin.row>
                    <td>{{ $week['label'] }}</td>
                    <td>{{ $week['sms'] }}</td>
                    <td>{{ $week['push'] }}</td>
                </x-admin.row>
            @endforeach
        </x-admin.table>
    </x-filament::section>
</x-filament-panels::page>
