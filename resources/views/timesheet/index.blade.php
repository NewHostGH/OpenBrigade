@extends('layout.app')

@section('title', __('timesheet.title') . ' — ' . config('app.name'))

@section('content')

<x-ob-breadcrumb :items="[
    ['label' => __('timesheet.breadcrumb')],
]"/>

<div class="ob-toolbar mx-3 mt-3">
    <div class="ob-toolbar-title">
        <h1>{{ __('timesheet.title') }}</h1>
        @if($personId)
            <a href="{{ route('timesheet.print', ['person' => $personId, 'week' => $week]) }}" target="_blank"
               class="btn btn-sm btn-outline-secondary ms-auto" title="{{ __('timesheet.export_pdf_title') }}">
                <i class="fas fa-file-pdf me-1"></i> PDF
            </a>
        @endif
    </div>

    <div class="d-flex align-items-center gap-3 mt-2 flex-wrap">
        {{-- Person picker (managers with several salaried staff) --}}
        @if($canSeeOthers && $personnel->count() > 1)
            <form method="GET" action="{{ route('timesheet.index') }}" class="d-flex align-items-center gap-2">
                <input type="hidden" name="week" value="{{ $week }}">
                <label class="fw-semibold" style="font-size:var(--font-size-sm)">{{ __('timesheet.person') }}</label>
                <select name="person" class="form-select form-select-sm" style="width:auto" onchange="this.form.submit()">
                    @foreach($personnel as $p)
                        <option value="{{ $p->P_ID }}" @selected((int) $p->P_ID === $personId)>
                            {{ strtoupper($p->P_NOM) }} {{ $p->P_PRENOM }}
                        </option>
                    @endforeach
                </select>
            </form>
        @endif

        {{-- Week navigation --}}
        <div class="d-flex align-items-center gap-2 ms-auto">
            <a href="{{ route('timesheet.index', ['person' => $personId, 'week' => $prevWeek]) }}"
               class="btn btn-sm btn-outline-secondary"><i class="fas fa-chevron-left"></i></a>
            <span class="fw-semibold" style="font-size:var(--font-size-sm); min-width:180px; text-align:center">
                {{ ucfirst($first->locale('fr')->isoFormat('D MMM')) }} – {{ ucfirst($end->locale('fr')->isoFormat('D MMM YYYY')) }}
            </span>
            <a href="{{ route('timesheet.index', ['person' => $personId, 'week' => $nextWeek]) }}"
               class="btn btn-sm btn-outline-secondary"><i class="fas fa-chevron-right"></i></a>
            @if($week !== 0)
                <a href="{{ route('timesheet.index', ['person' => $personId]) }}"
                   class="btn btn-sm btn-outline-primary">{{ __('timesheet.this_week') }}</a>
            @endif
        </div>
    </div>
</div>

@if(session('status'))
    <div class="mx-3 mt-3"><div class="alert alert-success py-2 mb-0">{{ session('status') }}</div></div>
@endif

@if(! $personId)
    <div class="mx-3 mt-3"><p class="ob-widget-empty p-3">{{ __('timesheet.no_staff') }}</p></div>
@else
<div class="mx-3 mt-3">
    <div class="ob-widget-card">
        <div class="ob-widget-card-header d-flex align-items-center">
            <div class="ob-widget-card-title">
                <i class="fas fa-clock"></i>
                {{ strtoupper($person->P_NOM) }} {{ $person->P_PRENOM }}
            </div>
            <span class="ob-ts-badge {{ $statusClass }} ms-auto">{{ $statusLabel }}</span>
        </div>
        <div class="ob-widget-card-body">

            <form method="POST" action="{{ route('timesheet.save') }}">
                @csrf
                <input type="hidden" name="person" value="{{ $personId }}">
                <input type="hidden" name="week" value="{{ $week }}">

                <div class="ob-ts-wrap">
                    <table class="ob-ts-table" id="ts-grid">
                        <thead>
                            <tr>
                                <th rowspan="2" class="ob-ts-day">{{ __('timesheet.col_day') }}</th>
                                <th colspan="2">{{ __('timesheet.col_morning') }}</th>
                                <th colspan="2">{{ __('timesheet.col_afternoon') }}</th>
                                <th rowspan="2">{{ __('timesheet.col_overtime') }}</th>
                                <th rowspan="2">{{ __('timesheet.col_total') }}</th>
                                <th rowspan="2">{{ __('timesheet.col_absence') }}</th>
                                <th rowspan="2">{{ __('timesheet.col_comment') }}</th>
                            </tr>
                            <tr>
                                <th><i>{{ __('timesheet.col_start') }}</i></th>
                                <th><i>{{ __('timesheet.col_end') }}</i></th>
                                <th><i>{{ __('timesheet.col_start') }}</i></th>
                                <th><i>{{ __('timesheet.col_end') }}</i></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($days as $i => $day)
                                @php $cls = ($day['isToday'] ? 'ob-ts-today ' : ($day['isWeekend'] ? 'ob-ts-weekend ' : '')); @endphp
                                <tr class="{{ $cls }}" data-ts-row>
                                    <td class="ob-ts-day">{{ $day['label'] }}</td>
                                    <input type="hidden" name="days[{{ $i }}][date]" value="{{ $day['key'] }}">
                                    @foreach(['debut1', 'fin1', 'debut2', 'fin2'] as $field)
                                        <td>
                                            <input type="time" class="form-control form-control-sm ob-ts-input" data-ts-time
                                                   name="days[{{ $i }}][{{ $field }}]" value="{{ $day[$field] }}"
                                                   @disabled(! $editable)>
                                        </td>
                                    @endforeach
                                    <td>
                                        <input type="text" class="form-control form-control-sm ob-ts-input" data-ts-ot
                                               name="days[{{ $i }}][overtime]" value="{{ $day['overtime'] === '0:00' ? '' : $day['overtime'] }}"
                                               placeholder="0:00" pattern="[0-9]{1,2}:[0-5][0-9]" @disabled(! $editable)>
                                    </td>
                                    <td class="ob-ts-total" data-ts-total>{{ $day['total'] }}</td>
                                    <td class="ob-ts-absence">{{ $day['absence'] }}</td>
                                    <td>
                                        <input type="text" class="form-control form-control-sm ob-ts-comment" maxlength="1000"
                                               name="days[{{ $i }}][comment]" value="{{ $day['comment'] }}"
                                               placeholder="{{ __('timesheet.comment_placeholder') }}" @disabled(! $editable)>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="ob-ts-totals">
                    <div>{{ __('timesheet.week_total') }} : <strong data-ts-week>{{ $weekTotal }}</strong></div>
                    <div>{{ __('timesheet.month_total') }} : <strong>{{ $monthTotal }}</strong></div>
                    <div>{{ __('timesheet.year_total') }} : <strong>{{ $yearTotal }}</strong></div>
                </div>

                <div class="d-flex align-items-center gap-2 mt-3 flex-wrap">
                    @if($editable)
                        <button type="submit" class="btn btn-sm btn-primary">
                            <i class="fas fa-save me-1"></i> {{ __('timesheet.save') }}
                        </button>
                    @else
                        <span class="text-muted" style="font-size:var(--font-size-sm)">
                            <i class="fas fa-lock me-1"></i> {{ __('timesheet.locked_hint') }}
                        </span>
                    @endif
                </div>
            </form>

            {{-- Workflow actions (separate forms) --}}
            <div class="d-flex align-items-center gap-2 mt-2 flex-wrap">
                @if($canSubmit)
                    <form method="POST" action="{{ route('timesheet.submit') }}">
                        @csrf
                        <input type="hidden" name="person" value="{{ $personId }}">
                        <input type="hidden" name="week" value="{{ $week }}">
                        <button type="submit" class="btn btn-sm btn-outline-primary">
                            <i class="fas fa-paper-plane me-1"></i> {{ __('timesheet.submit_for_validation') }}
                        </button>
                    </form>
                @endif

                @if($canDecide)
                    <form method="POST" action="{{ route('timesheet.decide') }}" class="d-flex gap-2">
                        @csrf
                        <input type="hidden" name="person" value="{{ $personId }}">
                        <input type="hidden" name="week" value="{{ $week }}">
                        <button type="submit" name="decision" value="validate" class="btn btn-sm btn-success">
                            <i class="fas fa-check me-1"></i> {{ __('timesheet.validate') }}
                        </button>
                        <button type="submit" name="decision" value="reject" class="btn btn-sm btn-outline-danger">
                            <i class="fas fa-times me-1"></i> {{ __('timesheet.reject') }}
                        </button>
                    </form>
                @endif
            </div>

            @if($editable)
                <p class="text-muted mt-2 mb-0" style="font-size:var(--font-size-xs)">
                    <i class="fas fa-info-circle me-1"></i> {{ __('timesheet.editable_hint') }}
                </p>
            @endif
        </div>
    </div>
</div>
@endif

@endsection

@push('scripts')
    @vite('resources/js/ob-timesheet.js')
@endpush
