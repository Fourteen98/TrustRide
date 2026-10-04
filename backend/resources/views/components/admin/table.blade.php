{{--
    A plain table for admin pages that are not a Filament resource list.

        <x-admin.table :headers="['Time', 'Stop', 'Rider']">
            <x-admin.row :muted="$away">
                <td>06:30</td> <td>Barrier</td> <td>Ama</td>
            </x-admin.row>
        </x-admin.table>

    The styles live here, once, so every such table looks the same in light and dark mode.
--}}
@props(['headers' => []])

@once
    <style>
        .tr-table { width: 100%; border-collapse: collapse; text-align: left; font-size: 0.875rem; }
        .tr-table th { padding: 0.5rem 0.75rem; font-weight: 600; white-space: nowrap; }
        .tr-table td { padding: 0.5rem 0.75rem; border-top: 1px solid color-mix(in srgb, currentColor 14%, transparent); }
        .tr-table tr.is-muted td { opacity: 0.55; }
        .tr-table-wrap { overflow-x: auto; }
    </style>
@endonce

<div class="tr-table-wrap">
    <table {{ $attributes->class('tr-table') }}>
        @if ($headers)
            <thead>
                <tr>
                    @foreach ($headers as $header)
                        <th scope="col">{{ $header }}</th>
                    @endforeach
                </tr>
            </thead>
        @endif
        <tbody>
            {{ $slot }}
        </tbody>
    </table>
</div>
