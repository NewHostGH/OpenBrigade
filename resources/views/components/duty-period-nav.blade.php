@props(['active' => 'week'])

{{-- Day / Week / Month switcher shared by the three guard views (all read the
     same astreinte data). `active` = day | week | month. --}}
<div class="btn-group btn-group-sm mb-3" role="group" aria-label="{{ __('duty.breadcrumb_duty') }}">
    <a href="{{ route('duty.today') }}"
       class="btn {{ $active === 'day' ? 'btn-primary' : 'btn-outline-secondary' }}">
        <i class="fas fa-calendar-day me-1"></i>{{ __('duty.period_day') }}
    </a>
    <a href="{{ route('duty.index') }}"
       class="btn {{ $active === 'week' ? 'btn-primary' : 'btn-outline-secondary' }}">
        <i class="fas fa-calendar-week me-1"></i>{{ __('duty.period_week') }}
    </a>
    <a href="{{ route('duty.on-call') }}"
       class="btn {{ $active === 'month' ? 'btn-primary' : 'btn-outline-secondary' }}">
        <i class="fas fa-calendar-alt me-1"></i>{{ __('duty.period_month') }}
    </a>
    <a href="{{ route('duty.calendar') }}"
       class="btn {{ $active === 'calendar' ? 'btn-primary' : 'btn-outline-secondary' }}">
        <i class="fas fa-calendar me-1"></i>{{ __('duty.period_calendar') }}
    </a>
</div>
