@extends('layout.app')

@section('title', __('repos.title') . ' — ' . config('app.name'))

@section('content')

<x-ob-breadcrumb :items="[
    ['label' => __('repos.breadcrumb')],
]"/>

<div class="ob-toolbar mx-3 mt-3">
    <div class="ob-toolbar-title">
        <h1>{{ __('repos.title') }}</h1>
    </div>

    <div class="d-flex align-items-center gap-3 mt-2 flex-wrap">
        @if($canSeeOthers)
            @feature('multi_site')
                <form method="GET" action="{{ route('repos.index') }}">
                    <input type="hidden" name="month" value="{{ $month }}">
                    <x-ob-section-select :selected="$sectionId" name="section" id="repos-section"
                        all-label="{{ __('repos.all_sections') }}" :auto-submit="true" />
                </form>
            @endfeature
        @endif

        <div class="d-flex align-items-center gap-2 ms-auto">
            <a href="{{ route('repos.index', ['month' => $prevMonth] + request()->only('section')) }}"
               class="btn btn-sm btn-outline-secondary"><i class="fas fa-chevron-left"></i></a>
            <span class="fw-semibold" style="font-size:var(--font-size-sm); min-width:150px; text-align:center">
                {{ ucfirst($first->locale('fr')->isoFormat('MMMM YYYY')) }}
            </span>
            <a href="{{ route('repos.index', ['month' => $nextMonth] + request()->only('section')) }}"
               class="btn btn-sm btn-outline-secondary"><i class="fas fa-chevron-right"></i></a>
            @if($month !== 0)
                <a href="{{ route('repos.index', request()->only('section')) }}"
                   class="btn btn-sm btn-outline-primary">{{ __('repos.this_month') }}</a>
            @endif
            @if($canEditAny && $autoEnabled)
                <a href="{{ route('repos.index', ['month' => $month, 'suggest' => 1] + request()->only('section')) }}"
                   class="btn btn-sm btn-outline-info" title="{{ __('repos.suggest_hint') }}">
                    <i class="fas fa-wand-magic-sparkles me-1"></i> {{ __('repos.suggest') }}
                </a>
            @endif
        </div>
    </div>
</div>

@if(session('status'))
    <div class="mx-3 mt-3"><div class="alert alert-success py-2 mb-0">{{ session('status') }}</div></div>
@endif

@if($rows->isEmpty())
    <div class="mx-3 mt-3"><p class="ob-widget-empty p-3">{{ __('repos.no_staff') }}</p></div>
@else
<div class="mx-3 mt-3">
    @if($suggesting)
        <div class="alert alert-info py-2"><i class="fas fa-wand-magic-sparkles me-1"></i> {{ __('repos.suggest_banner') }}</div>
    @endif

    <form method="POST" action="{{ route('repos.save') }}">
        @csrf
        <input type="hidden" name="month" value="{{ $month }}">

        <div class="ob-sp-wrap ob-widget-card">
            <table class="ob-sp-table mb-0">
                <thead>
                    <tr>
                        <th class="ob-sp-name">{{ __('repos.col_personnel') }}</th>
                        @foreach($days as $day)
                            <th class="{{ $day['isWeekend'] ? 'ob-sp-weekend' : '' }} {{ $day['isToday'] ? 'ob-sp-today' : '' }}">
                                {{ $day['day'] }}<span class="ob-sp-wd">{{ $day['weekday'] }}</span>
                            </th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @foreach($rows as $row)
                        @if($row->editable)<input type="hidden" name="persons[]" value="{{ $row->P_ID }}">@endif
                        <tr>
                            <td class="ob-sp-name">
                                {{ $row->name }}
                                @unless($row->editable)<i class="fas fa-lock fa-xs text-muted ms-1" title="{{ __('repos.readonly_hint') }}"></i>@endunless
                            </td>
                            @foreach($days as $day)
                                @php $c = $row->cells[$day['key']]; @endphp
                                <td class="ob-sp-cell {{ $day['isWeekend'] ? 'ob-sp-weekend' : '' }} {{ $day['isToday'] ? 'ob-sp-today' : '' }}">
                                    <div class="ob-repos-cell">
                                        @if($row->editable)
                                            <label class="ob-repos-toggle {{ $c['jourSuggested'] ? 'is-suggested' : '' }}" title="{{ __('repos.col_day_rest') }}">
                                                <input type="checkbox" name="jour[{{ $row->P_ID }}][]" value="{{ $day['key'] }}" @checked($c['jour'])>
                                                <span>J</span>
                                            </label>
                                            <label class="ob-repos-toggle ob-repos-toggle--night {{ $c['nuitSuggested'] ? 'is-suggested' : '' }}" title="{{ __('repos.col_night_rest') }}">
                                                <input type="checkbox" name="nuit[{{ $row->P_ID }}][]" value="{{ $day['key'] }}" @checked($c['nuit'])>
                                                <span>N</span>
                                            </label>
                                        @else
                                            @if($c['jour'])<span class="ob-repos-badge">J</span>@endif
                                            @if($c['nuit'])<span class="ob-repos-badge ob-repos-badge--night">N</span>@endif
                                        @endif
                                    </div>
                                </td>
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="d-flex align-items-center gap-3 mt-3 flex-wrap">
            @if($canEditAny)
                <button type="submit" class="btn btn-sm btn-primary"><i class="fas fa-save me-1"></i> {{ __('repos.save') }}</button>
            @endif
            <span class="text-muted" style="font-size:var(--font-size-xs)">
                <span class="ob-repos-badge">J</span> {{ __('repos.col_day_rest') }}
                <span class="ob-repos-badge ob-repos-badge--night ms-2">N</span> {{ __('repos.col_night_rest') }}
                — {{ __('repos.hint') }}
            </span>
        </div>
    </form>
</div>
@endif

@endsection
