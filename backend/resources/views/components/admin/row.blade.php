{{-- One row of an <x-admin.table>. Pass :muted="true" to fade it, for example a rider who is away. --}}
@props(['muted' => false])

<tr {{ $attributes->class(['is-muted' => $muted]) }}>
    {{ $slot }}
</tr>
