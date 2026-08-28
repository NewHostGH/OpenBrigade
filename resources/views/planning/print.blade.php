@extends('layout.app')

@section('title', __('planning.print_heading') . ' — ' . ucfirst($first->locale('fr')->isoFormat('MMMM YYYY')) . ' — ' . config('app.name'))

@section('content')

<div class="mx-3 mt-3">

    {{-- Toolbar (screen only) --}}
    <div class="ob-widget-card mb-3 d-print-none">
        <div class="ob-widget-card-body d-flex align-items-center gap-2">
            <button type="button" class="btn btn-sm btn-primary" onclick="window.print()">
                <i class="fas fa-print me-1"></i> {{ __('planning.print_btn') }}
            </button>
            <a href="{{ route('planning.index') }}" class="btn btn-sm btn-outline-secondary">
                <i class="fas fa-arrow-left me-1"></i> {{ __('common.back') }}
            </a>
        </div>
    </div>

    <h2 style="font-size:var(--font-size-lg); margin-bottom:8px;">
        {{ __('planning.print_heading') }} — {{ ucfirst($first->locale('fr')->isoFormat('MMMM YYYY')) }}
    </h2>

    @foreach($people as $person)
        <div class="ob-widget-card mb-3" style="break-inside:avoid;">
            <div class="ob-widget-card-header">
                <div class="ob-widget-card-title"><i class="fas fa-user me-1"></i> {{ $person['name'] }}</div>
            </div>
            <div class="ob-widget-card-body p-0">

                {{-- Activités --}}
                <div class="px-2 pt-2" style="font-size:var(--font-size-xs);font-weight:600;text-transform:uppercase;color:var(--text-muted-soft)">
                    {{ __('planning.print_section_events') }} ({{ $person['events']->count() }})
                </div>
                @if($person['events']->isEmpty())
                    <p class="ob-widget-empty px-3 py-2 mb-0">{{ __('planning.print_events_empty') }}</p>
                @else
                    <table class="table table-sm mb-0">
                        <thead style="background:var(--table-header-bg);color:var(--table-header-text)">
                            <tr>
                                <th>{{ __('planning.print_col_date') }}</th>
                                <th>{{ __('planning.print_col_time') }}</th>
                                <th>{{ __('planning.print_col_activity') }}</th>
                                <th>{{ __('planning.print_col_type') }}</th>
                                <th>{{ __('planning.print_col_status') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($person['events'] as $e)
                                <tr>
                                    <td style="font-size:var(--font-size-sm)">{{ \Carbon\Carbon::parse($e->event_date)->locale('fr')->isoFormat('ddd D MMM') }}</td>
                                    <td style="font-size:var(--font-size-sm)">{{ $e->event_time && $e->event_time !== '00:00' ? $e->event_time : '—' }}</td>
                                    <td style="font-size:var(--font-size-sm)">{{ $e->E_LIBELLE ?: $e->E_CODE }}</td>
                                    <td style="font-size:var(--font-size-sm)">{{ $e->TE_LIBELLE ?? '—' }}</td>
                                    <td style="font-size:var(--font-size-sm)">{{ $e->E_CLOSED ? __('planning.status_closed') : __('planning.status_open') }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif

                {{-- Absences --}}
                <div class="px-2 pt-3" style="font-size:var(--font-size-xs);font-weight:600;text-transform:uppercase;color:var(--text-muted-soft)">
                    {{ __('planning.print_section_absences') }} ({{ $person['absences']->count() }})
                </div>
                @if($person['absences']->isEmpty())
                    <p class="ob-widget-empty px-3 py-2 mb-0">{{ __('planning.print_absences_empty') }}</p>
                @else
                    <table class="table table-sm mb-0">
                        <thead style="background:var(--table-header-bg);color:var(--table-header-text)">
                            <tr>
                                <th>{{ __('planning.print_col_period') }}</th>
                                <th>{{ __('planning.print_col_type') }}</th>
                                <th>{{ __('planning.print_col_status') }}</th>
                                <th>{{ __('planning.print_col_comment') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($person['absences'] as $a)
                                <tr>
                                    <td style="font-size:var(--font-size-sm)">
                                        {{ \Carbon\Carbon::parse($a->I_DEBUT)->locale('fr')->isoFormat('D MMM') }}
                                        @if($a->I_FIN && $a->I_FIN !== $a->I_DEBUT)
                                            → {{ \Carbon\Carbon::parse($a->I_FIN)->locale('fr')->isoFormat('D MMM') }}
                                        @endif
                                    </td>
                                    <td style="font-size:var(--font-size-sm)">{{ $a->TI_LIBELLE ?: __('planning.absence_default') }}</td>
                                    <td style="font-size:var(--font-size-sm)">{{ $a->I_ACCEPT ? __('planning.status_accepted') : __('planning.pending') }}</td>
                                    <td style="font-size:var(--font-size-sm)">{{ $a->I_COMMENT ?: '—' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif

            </div>
        </div>
    @endforeach
</div>

@endsection
