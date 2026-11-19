@props(['paginator'])
<p {{ $attributes->merge(['class' => 'mb-3 text-sm tabular-nums text-slate-600']) }} role="status">Showing {{ $paginator->firstItem() ?? 0 }}–{{ $paginator->lastItem() ?? 0 }} of {{ $paginator->total() }}</p>
