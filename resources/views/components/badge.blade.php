@props(['status'])
@php
    $value = $status instanceof \BackedEnum ? $status->value : (string) $status;
    $colors = [
        'available' => 'bg-green-100 text-green-800',
        'reserved' => 'bg-blue-100 text-blue-800',
        'under_repair' => 'bg-red-100 text-red-800',
        'pending' => 'bg-amber-100 text-amber-800',
        'approved' => 'bg-blue-100 text-blue-800',
        'checked_out' => 'bg-indigo-100 text-indigo-800',
        'returned' => 'bg-green-100 text-green-800',
        'rejected' => 'bg-red-100 text-red-800',
        'cancelled' => 'bg-slate-200 text-slate-700',
    ];
@endphp
<span {{ $attributes->merge(['class' => 'inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium '.($colors[$value] ?? 'bg-slate-100 text-slate-700')]) }}>{{ ucfirst(str_replace('_', ' ', $value)) }}</span>
