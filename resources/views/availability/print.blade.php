@extends('layout.app')

@section('title', __('availability.print_heading') . ' — ' . config('app.name'))

@section('content')

@php
    $palette = ['#2563eb', '#16a34a', '#d97706', '#7c3aed', '#0891b2', '#db2777'];
    $periodColor = [];
    foreach ($periods as $i => $per) {
        $periodColor[$per->DP_ID] = $palette[$i % count($palette)];
    }
@endphp

<div class="mx-3 mt-3">

    <div class="ob-widget-card mb-3 d-print-none">
        <div class="ob-widget-card-body d-flex align-items-center gap-2">
            <button type="button" class="btn btn-sm btn-primary" onclick="window.print()">
                <i class="fas fa-print me-1"></i> {{ __('availability.print_btn') }}
            </button>
            <a href="{{ route('availability.index') }}" class="btn btn-sm btn-outline-secondary">
                <i class="fas fa-arrow-left me-1"></i> {{ __('common.back') }}
            </a>
        </div>
    </div>

    <h2 style="font-size:var(--font-size-lg); margin-bottom:8px;">
        {{ __('availability.print_heading') }} — {{ ucfirst($first->locale('fr')->isoFormat('D MMM')) }}
        → {{ ucfirst($first->copy()->addDays(count($days) - 1)->locale('fr')->isoFormat('D MMM YYYY')) }}
    </h2>

    @if($personnel->isEmpty() || $periods->isEmpty())
        <p class="ob-widget-empty p-3">{{ __('availability.empty') }}</p>
    @else
        <div class="mb-2" style="font-size:var(--font-size-xs)">
            @foreach($periods as $per)
                <span class="me-2"><span class="ob-av-badge" style="background:{{ $periodColor[$per->DP_ID] }}">{{ mb_substr($per->DP_NAME, 0, 1) }}</span> {{ $per->DP_NAME }}</span>
            @endforeach
        </div>
        <div class="ob-sp-wrap">
            <table class="ob-sp-table mb-0">
                <thead>
                    <tr>
                        <th class="ob-sp-name">{{ __('availability.col_personnel') }}</th>
                        @foreach($days as $day)
                            <th class="{{ $day['isWeekend'] ? 'ob-sp-weekend' : '' }}">
                                {{ $day['day'] }}<span class="ob-sp-wd">{{ $day['weekday'] }}</span>
                            </th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @foreach($personnel as $p)
                        <tr>
                            <td class="ob-sp-name">{{ strtoupper($p->P_NOM) }} {{ $p->P_PRENOM }}</td>
                            @foreach($days as $day)
                                @php $slots = $byPersonDate[$p->P_ID][$day['key']] ?? []; @endphp
                                <td class="ob-sp-cell {{ $day['isWeekend'] ? 'ob-sp-weekend' : '' }}">
                                    @foreach($slots as $periodId)
                                        <span class="ob-av-badge" style="background:{{ $periodColor[$periodId] ?? '#64748b' }}"
                                              title="{{ optional($periodMap[$periodId] ?? null)->DP_NAME }}">{{ mb_substr(optional($periodMap[$periodId] ?? null)->DP_NAME ?? '·', 0, 1) }}</span>
                                    @endforeach
                                </td>
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>

@endsection
