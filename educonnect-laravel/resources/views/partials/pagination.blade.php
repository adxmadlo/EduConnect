@if ($paginateur->hasPages())
    <div class="flex-between" style="padding:12px 18px;border-top:1px solid var(--border)">
        <span class="muted" style="font-size:12px">
            {{ $paginateur->firstItem() }}–{{ $paginateur->lastItem() }} sur {{ $paginateur->total() }}
        </span>
        <div class="flex gap-8">
            @if ($paginateur->onFirstPage())
                <span class="btn btn--ghost btn--sm" aria-disabled="true">Précédent</span>
            @else
                <a class="btn btn--ghost btn--sm" href="{{ $paginateur->previousPageUrl() }}" rel="prev">Précédent</a>
            @endif

            @if ($paginateur->hasMorePages())
                <a class="btn btn--ghost btn--sm" href="{{ $paginateur->nextPageUrl() }}" rel="next">Suivant</a>
            @else
                <span class="btn btn--ghost btn--sm" aria-disabled="true">Suivant</span>
            @endif
        </div>
    </div>
@endif
