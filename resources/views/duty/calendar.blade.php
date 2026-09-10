@extends('layout.app')

@section('title', __('duty.title_calendar') . ' — ' . config('app.name'))

@section('content')

<x-ob-breadcrumb :items="[
    ['label' => __('duty.breadcrumb_duty'), 'url' => route('duty.index')],
    ['label' => __('duty.title_calendar')],
]"/>

<div class="mx-3 mt-3">
    <x-duty-period-nav active="calendar" />
</div>

@php $eventsUrl = request()->has('section') ? route('duty.calendar.events', ['section' => $sectionId]) : route('duty.calendar.events'); @endphp

<div class="mx-3 d-flex align-items-center gap-3 flex-wrap">
    @feature('multi_site')
        <form method="GET" action="{{ route('duty.calendar') }}">
            <x-ob-section-select :selected="$sectionId" name="section" id="duty-cal-section"
                all-label="{{ __('duty.calendar_all_sections') }}" :auto-submit="true" />
        </form>
    @endfeature
</div>

<div class="mx-3 mt-3">
    <div class="ob-widget-card">
        <div class="ob-widget-card-body">
            <div data-ob-calendar
                 data-events-url="{{ $eventsUrl }}"
                 data-initial-view="dayGridMonth"
                 data-empty-text="{{ __('duty.calendar_empty') }}"></div>
        </div>
    </div>
</div>

@endsection

@push('scripts')
    @vite('resources/js/ob-calendar.js')
@endpush
