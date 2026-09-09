@extends('layout.app')

@section('title', __('availability.title') . ' — ' . config('app.name'))

@section('content')

@php
    // Period colour palette, assigned by position.
    $palette = ['#2563eb', '#16a34a', '#d97706', '#7c3aed', '#0891b2', '#db2777'];
    $periodColor = [];
    foreach ($periods as $i => $per) {
        $periodColor[$per->DP_ID] = $palette[$i % count($palette)];
    }
    $showFilter = $canSeeOthers && $personnel->count() > 1;
    $blocked = $blocked ?? [];
@endphp

<x-ob-breadcrumb :items="[
    ['label' => __('availability.breadcrumb')],
]"/>

<div class="ob-toolbar mx-3 mt-3">
    <div class="ob-toolbar-title">
        <h1>{{ __('availability.title') }}</h1>
        <a href="{{ route('availability.print', request()->only('section')) }}" target="_blank"
           class="btn btn-sm btn-outline-secondary ms-auto" title="{{ __('availability.export_pdf_title') }}">
            <i class="fas fa-file-pdf me-1"></i> PDF
        </a>
    </div>
    <div class="d-flex align-items-center gap-3 mt-2 flex-wrap">
        <a href="{{ route('availability.index', ['week' => $prevWeek] + request()->only('section')) }}"
           class="btn btn-sm btn-outline-secondary"><i class="fas fa-chevron-left"></i></a>
        <span class="fw-semibold" style="font-size:var(--font-size-sm); min-width:180px; text-align:center">
            {{ ucfirst($first->locale('fr')->isoFormat('D MMM')) }} – {{ ucfirst($end->locale('fr')->isoFormat('D MMM YYYY')) }}
        </span>
        <a href="{{ route('availability.index', ['week' => $nextWeek] + request()->only('section')) }}"
           class="btn btn-sm btn-outline-secondary"><i class="fas fa-chevron-right"></i></a>
        @if($week !== 0)
            <a href="{{ route('availability.index', request()->only('section')) }}"
               class="btn btn-sm btn-outline-primary">{{ __('availability.this_week') }}</a>
        @endif

        @if($canSeeOthers)
            @feature('multi_site')
                <form method="GET" action="{{ route('availability.index') }}" class="ms-auto">
                    <input type="hidden" name="week" value="{{ $week }}">
                    <x-ob-section-select :selected="$sectionId" name="section" id="av-section"
                        all-label="{{ __('availability.all_sections') }}" :auto-submit="true" />
                </form>
            @endfeature
        @endif
    </div>
</div>

@if($periods->isEmpty())
    <div class="mx-3 mt-3"><p class="ob-widget-empty p-3">{{ __('availability.no_periods') }}</p></div>
@else
<div class="mx-3 mt-3 row g-3">

    {{-- Personnel filter (managers) --}}
    <div class="col-lg-3 {{ $showFilter ? '' : 'd-none' }}">
        <div class="ob-widget-card">
            <div class="ob-widget-card-header">
                <div class="ob-widget-card-title">
                    <i class="fas fa-users"></i> {{ __('availability.people_title') }}
                    <span class="ob-badge ob-badge-archive ms-1">{{ $personnel->count() }}</span>
                </div>
            </div>
            <div class="ob-widget-card-body p-2">
                <div class="d-flex gap-2 mb-2">
                    <button type="button" class="btn btn-xs btn-light" data-av-toggle="all">{{ __('availability.select_all') }}</button>
                    <button type="button" class="btn btn-xs btn-light" data-av-toggle="none">{{ __('availability.select_none') }}</button>
                </div>
                <div id="av-people" class="ob-sp-people">
                    @foreach($personnel as $p)
                        <label class="ob-sp-person">
                            <input type="checkbox" data-av-person value="{{ $p->P_ID }}" checked>
                            <span class="ob-sp-person-name">{{ strtoupper($p->P_NOM) }} {{ $p->P_PRENOM }}</span>
                        </label>
                    @endforeach
                </div>

                <hr class="my-2">
                <div style="font-size:var(--font-size-xs)">
                    <div class="text-muted mb-1">{{ __('availability.legend_note') }}</div>
                    @foreach($periods as $per)
                        <div class="ob-sp-legend-row">
                            <span class="ob-av-badge" style="background:{{ $periodColor[$per->DP_ID] }}">{{ mb_substr($per->DP_NAME, 0, 1) }}</span>
                            {{ $per->DP_NAME }}
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>

    {{-- Availability grid --}}
    <div class="{{ $showFilter ? 'col-lg-9' : 'col-12' }}">
        <div class="ob-sp-wrap ob-widget-card">
            <table class="ob-sp-table mb-0" id="av-grid" data-toggle-url="{{ route('availability.toggle') }}">
                <thead>
                    <tr>
                        <th class="ob-sp-name">{{ __('availability.col_personnel') }}</th>
                        @foreach($days as $day)
                            <th class="{{ $day['isWeekend'] ? 'ob-sp-weekend' : '' }} {{ $day['isToday'] ? 'ob-sp-today' : '' }}">
                                {{ $day['day'] }}<span class="ob-sp-wd">{{ $day['weekday'] }}</span>
                            </th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @foreach($personnel as $p)
                        @php $isSelf = (int) $p->P_ID === auth()->id(); @endphp
                        <tr data-av-row="{{ $p->P_ID }}">
                            <td class="ob-sp-name">
                                {{ strtoupper($p->P_NOM) }} {{ $p->P_PRENOM }}
                                @if($isSelf)<i class="fas fa-pen fa-xs text-muted ms-1" title="{{ __('availability.editable_hint') }}"></i>@endif
                            </td>
                            @foreach($days as $day)
                                @php $slots = $byPersonDate[$p->P_ID][$day['key']] ?? []; @endphp
                                <td class="ob-sp-cell {{ $day['isWeekend'] ? 'ob-sp-weekend' : '' }} {{ $day['isToday'] ? 'ob-sp-today' : '' }}">
                                    @if($isSelf && ! $day['isPast'])
                                        @foreach($periods as $per)
                                            @php
                                                $on = in_array($per->DP_ID, $slots);
                                                $isBlocked = ! empty($blocked[$p->P_ID][$day['key']][$per->DP_ID]);
                                            @endphp
                                            @if($isBlocked)
                                                <span class="ob-av-slot is-blocked" title="{{ __('availability.slot_blocked') }}">
                                                    <i class="fas fa-lock"></i>
                                                </span>
                                            @else
                                                <button type="button"
                                                        class="ob-av-slot {{ $on ? 'is-on' : '' }}"
                                                        data-av-slot data-date="{{ $day['key'] }}" data-period="{{ $per->DP_ID }}"
                                                        style="--slot-color:{{ $periodColor[$per->DP_ID] ?? '#64748b' }}"
                                                        title="{{ $per->DP_NAME }}">{{ mb_substr($per->DP_NAME, 0, 1) }}</button>
                                            @endif
                                        @endforeach
                                    @else
                                        @foreach($slots as $periodId)
                                            <span class="ob-av-badge" style="background:{{ $periodColor[$periodId] ?? '#64748b' }}"
                                                  title="{{ optional($periodMap[$periodId] ?? null)->DP_NAME }}">{{ mb_substr(optional($periodMap[$periodId] ?? null)->DP_NAME ?? '·', 0, 1) }}</span>
                                        @endforeach
                                    @endif
                                </td>
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
@endif

@endsection

@push('scripts')
    @vite('resources/js/ob-availability.js')
@endpush
