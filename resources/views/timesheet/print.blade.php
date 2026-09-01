@extends('layout.app')

@section('title', __('timesheet.print_heading') . ' — ' . config('app.name'))

@section('content')

<div class="mx-3 mt-3">

    <div class="ob-widget-card mb-3 d-print-none">
        <div class="ob-widget-card-body d-flex align-items-center gap-2">
            <button type="button" class="btn btn-sm btn-primary" onclick="window.print()">
                <i class="fas fa-print me-1"></i> {{ __('timesheet.print_btn') }}
            </button>
            <a href="{{ route('timesheet.index', ['person' => $personId, 'week' => $week]) }}" class="btn btn-sm btn-outline-secondary">
                <i class="fas fa-arrow-left me-1"></i> {{ __('common.back') }}
            </a>
        </div>
    </div>

    @if(! $personId)
        <p class="ob-widget-empty p-3">{{ __('timesheet.no_staff') }}</p>
    @else
        <h2 style="font-size:var(--font-size-lg); margin-bottom:4px;">
            {{ __('timesheet.print_heading') }} — {{ strtoupper($person->P_NOM) }} {{ $person->P_PRENOM }}
        </h2>
        <p class="mb-2" style="font-size:var(--font-size-sm)">
            {{ ucfirst($first->locale('fr')->isoFormat('D MMM')) }} – {{ ucfirst($end->locale('fr')->isoFormat('D MMM YYYY')) }}
            — <span class="ob-ts-badge {{ $statusClass }}">{{ $statusLabel }}</span>
        </p>

        <div class="ob-ts-wrap">
            <table class="ob-ts-table">
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
                    @foreach($days as $day)
                        <tr class="{{ $day['isWeekend'] ? 'ob-ts-weekend' : '' }}">
                            <td class="ob-ts-day">{{ $day['label'] }}</td>
                            <td>{{ $day['debut1'] ?: '—' }}</td>
                            <td>{{ $day['fin1'] ?: '—' }}</td>
                            <td>{{ $day['debut2'] ?: '—' }}</td>
                            <td>{{ $day['fin2'] ?: '—' }}</td>
                            <td>{{ $day['overtime'] === '0:00' ? '—' : $day['overtime'] }}</td>
                            <td class="ob-ts-total">{{ $day['total'] }}</td>
                            <td class="ob-ts-absence">{{ $day['absence'] }}</td>
                            <td style="text-align:left">{{ $day['comment'] }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="ob-ts-totals">
            <div>{{ __('timesheet.week_total') }} : <strong>{{ $weekTotal }}</strong></div>
            <div>{{ __('timesheet.month_total') }} : <strong>{{ $monthTotal }}</strong></div>
            <div>{{ __('timesheet.year_total') }} : <strong>{{ $yearTotal }}</strong></div>
        </div>
    @endif
</div>

@endsection
