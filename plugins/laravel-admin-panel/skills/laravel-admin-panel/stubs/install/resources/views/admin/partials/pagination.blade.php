{{-- Always shows "Showing X–Y of Z" when there is at least one row, even on a single page. --}}
@if($paginator->total() > 0)
<nav aria-label="Pagination" class="d-flex flex-wrap align-items-center gap-3 mt-3">
    <ul class="pagination mb-0">
        <li class="page-item {{ $paginator->onFirstPage() ? 'disabled' : '' }}">
            <a class="page-link" href="{{ $paginator->onFirstPage() ? '#' : $paginator->previousPageUrl() }}">&laquo; Prev</a>
        </li>
        <li class="page-item {{ $paginator->hasMorePages() ? '' : 'disabled' }}">
            <a class="page-link" href="{{ $paginator->hasMorePages() ? $paginator->nextPageUrl() : '#' }}">Next &raquo;</a>
        </li>
    </ul>
    <span class="text-secondary small">
        Showing {{ $paginator->firstItem() }}–{{ $paginator->lastItem() }} of {{ $paginator->total() }}
        @if($paginator->hasPages())
            (page {{ $paginator->currentPage() }} of {{ $paginator->lastPage() }})
        @endif
    </span>
</nav>
@endif
