<div class="ob-widget-card">
    <div class="ob-widget-card-header">
        <div class="ob-widget-card-title">
            <i class="fas fa-calendar-day"></i> {{ __('dashboard.agenda.title') }}
        </div>
        <a class="ob-widget-card-link" href="{{ route('planning.index') }}"
           title="{{ __('dashboard.agenda.open_calendar') }}">
            <i class="fas fa-external-link-alt"></i>
        </a>
    </div>
    <div class="ob-widget-card-body">
        <div data-ob-calendar data-compact
             data-events-url="{{ route('planning.events') }}"
             data-initial-view="listMonth"
             data-height="360"
             data-empty-text="{{ __('dashboard.agenda.empty') }}"></div>
    </div>
</div>

@once
    @push('scripts')
        @vite('resources/js/ob-calendar.js')
    @endpush
@endonce
