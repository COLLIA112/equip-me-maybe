@if ($paginator->hasPages())
    @php $control = 'inline-flex min-h-11 min-w-11 items-center justify-center rounded-md border px-3 text-sm'; @endphp
    <nav aria-label="{{ __('Pagination Navigation') }}" class="flex flex-wrap items-center justify-between gap-2 tabular-nums">
        @if ($paginator->onFirstPage())
            <span aria-disabled="true" class="{{ $control }} border-slate-200 bg-slate-50 text-slate-600">Previous</span>
        @else
            <a href="{{ $paginator->previousPageUrl() }}" rel="prev" class="{{ $control }} border-slate-300 bg-white font-semibold text-blue-700 hover:bg-slate-100">Previous</a>
        @endif

        <span aria-current="page" class="text-sm text-slate-600 sm:hidden">{{ __('Page :page of :last', ['page' => $paginator->currentPage(), 'last' => $paginator->lastPage()]) }}</span>
        <div class="hidden flex-wrap items-center gap-2 sm:flex">
            @foreach ($elements as $element)
                @if (is_string($element))
                    <span class="px-2 text-sm text-slate-600">{{ $element }}</span>
                @elseif (is_array($element))
                    @foreach ($element as $page => $url)
                        @if ($page == $paginator->currentPage())
                            <span aria-current="page" class="{{ $control }} border-blue-700 bg-blue-700 font-semibold text-white">{{ $page }}</span>
                        @else
                            <a href="{{ $url }}" aria-label="{{ __('Go to page :page', ['page' => $page]) }}" class="{{ $control }} border-slate-300 bg-white font-semibold text-blue-700 hover:bg-slate-100">{{ $page }}</a>
                        @endif
                    @endforeach
                @endif
            @endforeach
        </div>

        @if ($paginator->hasMorePages())
            <a href="{{ $paginator->nextPageUrl() }}" rel="next" class="{{ $control }} border-slate-300 bg-white font-semibold text-blue-700 hover:bg-slate-100">Next</a>
        @else
            <span aria-disabled="true" class="{{ $control }} border-slate-200 bg-slate-50 text-slate-600">Next</span>
        @endif
    </nav>
@endif
