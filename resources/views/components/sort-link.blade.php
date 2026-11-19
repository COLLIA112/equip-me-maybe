@props(['field', 'label'])
@php
    $current = request('sort') === $field;
    $direction = $current && request('direction') === 'asc' ? 'desc' : 'asc';
    $url = request()->url().'?'.http_build_query(array_merge(request()->except('page'), ['sort' => $field, 'direction' => $direction]));
@endphp
<a href="{{ $url }}" class="font-semibold text-blue-700 hover:underline" aria-label="Sort by {{ $label }} {{ $direction === 'asc' ? 'ascending' : 'descending' }}">{{ $label }}@if($current)<span aria-hidden="true"> {{ request('direction') === 'asc' ? '↑' : '↓' }}</span>@endif</a>
