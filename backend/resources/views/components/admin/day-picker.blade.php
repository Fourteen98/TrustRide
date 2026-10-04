{{--
    A date field bound to a Livewire property on the page, with a line of text beside it.

        <x-admin.day-picker model="date" :value="$day->toDateString()">Saturday 3 October. 3 riders expected.</x-admin.day-picker>
--}}
@props(['model', 'value' => null, 'label' => 'Day'])

@once
    <style>
        .tr-day-picker { display: flex; align-items: center; gap: 0.75rem; flex-wrap: wrap; }
        .tr-day-picker label { font-weight: 600; }
        .tr-day-picker input {
            border: 1px solid color-mix(in srgb, currentColor 25%, transparent);
            border-radius: 0.5rem; padding: 0.375rem 0.625rem; background: transparent; color: inherit;
        }
    </style>
@endonce

<div class="tr-day-picker">
    <label for="tr-day-{{ $model }}">{{ $label }}</label>
    <input id="tr-day-{{ $model }}" type="date" wire:model.live="{{ $model }}" value="{{ $value }}">
    <span>{{ $slot }}</span>
</div>
