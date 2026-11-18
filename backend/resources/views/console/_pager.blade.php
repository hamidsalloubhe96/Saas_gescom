@if($paginator->hasPages())
    <nav class="pager" aria-label="Pagination">
        @if($paginator->onFirstPage())<span class="dis">← Précédent</span>@else<a href="{{ $paginator->previousPageUrl() }}">← Précédent</a>@endif
        <span class="cur">Page {{ $paginator->currentPage() }} / {{ $paginator->lastPage() }}</span>
        @if($paginator->hasMorePages())<a href="{{ $paginator->nextPageUrl() }}">Suivant →</a>@else<span class="dis">Suivant →</span>@endif
    </nav>
@endif
