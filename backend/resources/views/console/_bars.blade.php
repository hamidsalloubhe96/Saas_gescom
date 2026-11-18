{{-- Histogramme horizontal simple et accessible. $data : [libellé => valeur]. $format : fonction d'affichage de la valeur. --}}
@php
    $max = max(1, collect($data)->max() ?: 1);
    $format = $format ?? fn ($v) => number_format($v, 0, ',', ' ');
@endphp
@forelse($data as $label => $value)
    <div style="margin-bottom:.55rem">
        <div style="display:flex;justify-content:space-between;font-size:.88rem"><span>{{ $label }}</span><strong>{{ $format($value) }}</strong></div>
        <div class="bar" role="img" aria-label="{{ $label }} : {{ $format($value) }}"><span style="width:{{ $value / $max * 100 }}%"></span></div>
    </div>
@empty
    <p class="muted" style="margin:0">Aucune donnée.</p>
@endforelse
