@extends('layout.app')

@section('title', __('planning.title') . ' — ' . config('app.name'))

@section('content')

@php $showFilter = $canSeeOthers && $personnel->count() > 1; @endphp

<x-ob-breadcrumb :items="[
    ['label' => __('planning.breadcrumb')],
]"/>

<div class="ob-toolbar mx-3 mt-3">
    <div class="ob-toolbar-title">
        <h1>{{ __('planning.title') }}</h1>
        <a href="{{ route('planning.print') }}" id="planning-print-link" target="_blank"
           class="btn btn-sm btn-outline-secondary ms-auto" title="{{ __('planning.export_pdf_title') }}">
            <i class="fas fa-file-pdf me-1"></i> PDF
        </a>
    </div>
    @if($canSeeOthers)
        @feature('multi_site')
            <form method="GET" action="{{ route('planning.index') }}" class="mt-2">
                <x-ob-section-select :selected="$sectionId" name="section" id="pl-section"
                    all-label="{{ __('planning.all_sections') }}" :auto-submit="true" />
            </form>
        @endfeature
    @endif
</div>

<div class="mx-3 mt-3 row g-3">

    {{-- ── Personnel filter (managers only) ─────────────────────────────── --}}
    <div class="col-lg-3 {{ $showFilter ? '' : 'd-none' }}">
        <div class="ob-widget-card">
            <div class="ob-widget-card-header">
                <div class="ob-widget-card-title">
                    <i class="fas fa-users"></i> {{ __('planning.people_title') }}
                    <span class="ob-badge ob-badge-archive ms-1">{{ $personnel->count() }}</span>
                </div>
            </div>
            <div class="ob-widget-card-body p-2">
                <div class="d-flex gap-2 mb-2">
                    <button type="button" class="btn btn-xs btn-light" data-sp-toggle="all">{{ __('planning.select_all') }}</button>
                    <button type="button" class="btn btn-xs btn-light" data-sp-toggle="none">{{ __('planning.select_none') }}</button>
                </div>
                <div id="planning-people" class="ob-sp-people">
                    @foreach($personnel as $p)
                        <label class="ob-sp-person">
                            <input type="checkbox" data-sp-person value="{{ $p->P_ID }}" @checked((int) $p->P_ID === auth()->id())>
                            <span class="ob-sp-swatch" style="background:{{ $p->color }}"></span>
                            <span class="ob-sp-person-name">{{ strtoupper($p->P_NOM) }} {{ $p->P_PRENOM }}</span>
                        </label>
                    @endforeach
                </div>

                <hr class="my-2">
                <div class="ob-sp-legend">
                    <div class="text-muted mb-1" style="font-size:var(--font-size-xs)">{{ __('planning.legend_note') }}</div>
                    <div class="ob-sp-legend-row"><span class="ob-sp-legend-swatch"></span> {{ __('planning.legend_event') }}</div>
                    <div class="ob-sp-legend-row"><span class="ob-sp-legend-swatch ob-sp-legend-swatch--abs"></span> {{ __('planning.legend_abs_ok') }}</div>
                    <div class="ob-sp-legend-row"><span class="ob-sp-legend-swatch ob-sp-legend-swatch--pending"></span> {{ __('planning.legend_abs_pending') }}</div>
                </div>
            </div>
        </div>
    </div>

    {{-- Hidden single-person filter for solo members (feeds the calendar JS) --}}
    @unless($showFilter)
        <div id="planning-people" class="d-none">
            @foreach($personnel as $p)
                <input type="checkbox" data-sp-person value="{{ $p->P_ID }}" checked>
            @endforeach
        </div>
    @endunless

    {{-- ── Calendar ─────────────────────────────────────────────────────── --}}
    <div class="{{ $showFilter ? 'col-lg-9' : 'col-12' }}">
        <div class="ob-widget-card">
            <div class="ob-widget-card-body">
                <div data-ob-section-planning
                     data-events-url="{{ route('planning.events') }}"
                     data-filter="#planning-people"
                     data-print="#planning-print-link"></div>
            </div>
        </div>

        @unless($showFilter)
            <div class="ob-cal-legend mt-2">
                <span><span class="ob-cal-legend-dot ob-cal-legend-dot--activity"></span> {{ __('planning.legend_event') }}</span>
                <span><span class="ob-cal-legend-dot ob-cal-legend-dot--abs-ok"></span> {{ __('planning.legend_abs_ok') }}</span>
                <span><span class="ob-cal-legend-dot ob-cal-legend-dot--abs-pending"></span> {{ __('planning.legend_abs_pending') }}</span>
            </div>
        @endunless
    </div>
</div>

@endsection

@push('scripts')
    @vite('resources/js/ob-section-planning.js')
@endpush
