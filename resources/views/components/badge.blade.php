@props(['status'])
@php
    $value = $status instanceof \BackedEnum ? $status->value : (string) $status;
    $colors = [
        'available' => 'bg-green-100 text-green-800',
        'reserved' => 'bg-blue-100 text-blue-800',
        'under_repair' => 'bg-red-100 text-red-800',
        'pending' => 'bg-orange-100 text-orange-900',
        'approved' => 'bg-blue-100 text-blue-800',
        'checked_out' => 'bg-blue-100 text-blue-800',
        'returned' => 'bg-slate-200 text-slate-800',
        'completed' => 'bg-green-100 text-green-800',
        'rejected' => 'bg-red-100 text-red-800',
        'cancelled' => 'bg-slate-200 text-slate-700',
    ];
@endphp
<span {{ $attributes->merge(['class' => 'inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium '.($colors[$value] ?? 'bg-slate-100 text-slate-700')]) }}>{{ ucfirst(str_replace('_', ' ', $value)) }}</span>
