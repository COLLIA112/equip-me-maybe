@props(['equipment'])
@php $glance = $equipment->availabilityGlance(); @endphp
<span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold tabular-nums {{ $glance['classes'] }}">{{ $glance['label'] }}</span>
