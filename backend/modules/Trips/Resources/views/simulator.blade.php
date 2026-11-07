<x-filament-panels::page>
    @php($run = $this->run())
    @php($trip = $this->trip())
    @php($riders = $this->riders())
    @php($rider = $this->rider())
    @php($view = $this->herView())
    @php($running = $trip?->isRunning() ?? false)

    @once
        <style>
            .tr-sim-row { display: flex; flex-wrap: wrap; gap: 0.5rem; align-items: center; }
            .tr-sim-pick { display: flex; flex-wrap: wrap; gap: 1rem; align-items: end; }
            .tr-sim-pick label { display: grid; gap: 0.25rem; font-weight: 600; font-size: 0.875rem; }
            .tr-sim-pick select {
                border: 1px solid color-mix(in srgb, currentColor 25%, transparent); border-radius: 0.5rem;
                padding: 0.375rem 0.625rem; background: transparent; color: inherit; min-width: 16rem;
            }
            .tr-sim-map { width: 100%; height: 34rem; border: 1px solid color-mix(in srgb, currentColor 14%, transparent); border-radius: 0.75rem; }
            .tr-sim-big { font-size: 1.25rem; font-weight: 700; }
            .tr-sim-muted { opacity: 0.7; font-size: 0.875rem; }
        </style>
    @endonce

    <x-filament::section heading="1. Pick a run and a rider" description="This uses the real records. Riders on the run are sent the real 'van has set off' message when you start.">
        <div class="tr-sim-pick">
            <label>Run today
                <select wire:model.live="runId">
                    @forelse ($this->runs() as $id => $label)
                        <option value="{{ $id }}">{{ $label }}</option>
                    @empty
                        <option value="">No run goes today</option>
                    @endforelse
                </select>
            </label>
            <label>See it as this rider
                <select wire:model.live="riderId">
                    <option value="">Nobody in particular</option>
                    @foreach ($riders as $option)
                        <option value="{{ $option['rider_id'] }}">{{ $option['name'] }}, gets on at {{ $option['stop'] }} ({{ $option['time'] }})</option>
                    @endforeach
                </select>
            </label>
        </div>
        @if ($run && $riders->isEmpty())
            <p class="tr-sim-muted" style="margin-top: 0.75rem">Nobody is riding this run today. A rider needs an active seat and a running plan.</p>
        @endif
    </x-filament::section>

    @if ($run)
        <x-filament::section heading="2. Drive the van">
            <div class="tr-sim-row" style="margin-bottom: 1rem">
                @if (! $trip)
                    <x-filament::button wire:click="start" icon="heroicon-o-play">Start the trip</x-filament::button>
                @elseif ($running)
                    <x-filament::button wire:click="end" color="gray" icon="heroicon-o-stop">End the trip</x-filament::button>
                @endif
                @if ($trip)
                    <x-filament::button wire:click="restart" color="gray" wire:confirm="Throw today's trip away and start again? Pickups and positions for it are deleted.">Start over</x-filament::button>
                @endif
                <span class="tr-sim-muted">{{ $trip ? ($running ? 'On the road.' : 'Ended. Start over to drive again.') : 'Not started.' }}</span>
            </div>

            @if ($running)
                <x-admin.table :headers="['Stop', 'Due', 'Put the van', '']">
                    @foreach ($trip->stopsInOrder() as $index => $stop)
                        <x-admin.row :muted="$stop->latitude === null">
                            <td>
                                {{ $stop->name }}
                                @if ($rider && $rider['stop_id'] === $stop->id) <strong>(her stop)</strong> @endif
                                @if ($view['stops'][$index]['reached'] ?? false) ✓ @endif
                            </td>
                            <td>{{ $run->timeAtStop($stop->id) }}</td>
                            <td>
                                <div class="tr-sim-row">
                                    <x-filament::button size="xs" color="gray" wire:click="placeAtStop({{ $stop->id }}, 0)">Here</x-filament::button>
                                    @if (! $loop->last)
                                        <x-filament::button size="xs" color="gray" wire:click="placeAtStop({{ $stop->id }}, 50)">Halfway to the next</x-filament::button>
                                        <x-filament::button size="xs" color="gray" wire:click="placeAtStop({{ $stop->id }}, 85)">Nearly at the next</x-filament::button>
                                    @endif
                                </div>
                            </td>
                            <td class="tr-sim-muted">{{ $stop->latitude === null ? 'No pin. Set one under Network > Stops.' : '' }}</td>
                        </x-admin.row>
                    @endforeach
                </x-admin.table>
                <p class="tr-sim-muted" style="margin-top: 0.75rem">Or click anywhere on the map below to drop the van there. The van never counts as going backwards along the route.</p>
            @endif
        </x-filament::section>

        <x-filament::section heading="3. What she sees">
            @if ($rider)
                <p class="tr-sim-big">
                    @if (! $trip) The van has not set off yet.
                    @elseif (! $running) Today's trip has ended.
                    @elseif ($view['stops_away'] === 0) At her stop, or past it.
                    @else {{ $view['stops_away'] }} {{ \Illuminate\Support\Str::plural('stop', $view['stops_away']) }} away
                    @endif
                </p>
                <p class="tr-sim-muted">
                    {{ $rider['name'] }}, {{ $rider['phone'] }}. Log in to the app with that number to watch her screen change.
                    @php($pickup = $trip?->pickups->firstWhere('rider_id', $rider['rider_id'])?->status)
                    @if ($pickup) The mate marked her: {{ $pickup->label() }}. @endif
                </p>
                @if ($running)
                    <div class="tr-sim-row" style="margin: 0.75rem 0">
                        <span>As the mate:</span>
                        <x-filament::button size="xs" color="success" wire:click="mark('picked_up')">She is on board</x-filament::button>
                        <x-filament::button size="xs" color="warning" wire:click="mark('missed')">She is not here</x-filament::button>
                    </div>
                @endif
            @else
                <p class="tr-sim-muted">Pick a rider above to see how many stops away the van is for her, and to mark her picked up.</p>
            @endif

            @if ($trip)
                <iframe wire:ignore.self class="tr-sim-map" title="The live map"
                    src="{{ url('/t/'.$trip->public_id) }}?fast=1&pick=1{{ $rider ? '&stop='.$rider['stop_id'] : '' }}"></iframe>
                <p class="tr-sim-muted" style="margin-top: 0.5rem">
                    This is the page a share link opens: <a href="{{ url('/t/'.$trip->public_id) }}" target="_blank" style="text-decoration: underline">{{ url('/t/'.$trip->public_id) }}</a>
                </p>
            @endif
        </x-filament::section>
    @endif

    @script
        <script>
            // A click on the map inside the frame drops the van there.
            window.addEventListener('message', (event) => {
                if (event.origin === window.location.origin && event.data?.aveoPick) {
                    $wire.placeVan(event.data.aveoPick.latitude, event.data.aveoPick.longitude);
                }
            });

            // After any button here, tell the map to look again now and not wait for its next turn.
            $wire.on('aveo-refresh-map', () => {
                document.querySelector('.tr-sim-map')?.contentWindow?.postMessage('aveoRefresh', window.location.origin);
            });
        </script>
    @endscript
</x-filament-panels::page>
