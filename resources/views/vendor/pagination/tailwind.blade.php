@if ($paginator->hasPages())
    <nav aria-label="{{ __('Pagination Navigation') }}" class="flex flex-wrap items-center justify-between gap-3">
        @if ($paginator->onFirstPage())
            <span aria-disabled="true" class="rounded-md border border-slate-300 bg-white px-3 py-2 text-sm text-slate-600">Previous</span>
        @else
            <a href="{{ $paginator->previousPageUrl() }}" rel="prev" class="rounded-md border border-slate-300 bg-white px-3 py-2 text-sm font-semibold text-blue-700 hover:bg-slate-100">Previous</a>
        @endif

        <div class="hidden flex-wrap items-center gap-1 sm:flex">
            @foreach ($elements as $element)
                @if (is_string($element))
                    <span class="px-2 text-sm text-slate-600">{{ $element }}</span>
                @elseif (is_array($element))
                    @foreach ($element as $page => $url)
                        @if ($page == $paginator->currentPage())
                            <span aria-current="page" class="rounded-md border border-blue-700 bg-blue-700 px-3 py-2 text-sm font-semibold text-white">{{ $page }}</span>
                        @else
                            <a href="{{ $url }}" aria-label="{{ __('Go to page :page', ['page' => $page]) }}" class="rounded-md border border-slate-300 bg-white px-3 py-2 text-sm font-semibold text-blue-700 hover:bg-slate-100">{{ $page }}</a>
                        @endif
                    @endforeach
                @endif
            @endforeach
        </div>

        @if ($paginator->hasMorePages())
            <a href="{{ $paginator->nextPageUrl() }}" rel="next" class="rounded-md border border-slate-300 bg-white px-3 py-2 text-sm font-semibold text-blue-700 hover:bg-slate-100">Next</a>
        @else
            <span aria-disabled="true" class="rounded-md border border-slate-300 bg-white px-3 py-2 text-sm text-slate-600">Next</span>
        @endif
    </nav>
@endif
