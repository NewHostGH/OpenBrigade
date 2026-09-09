@extends('layout.app')

@section('title', 'Indisponibilités — ' . config('app.name'))

@section('content')

<x-ob-breadcrumb :items="[
    ['label' => 'Absences / Indisponibilités'],
]"/>

{{-- Tab navigation --}}
<div class="mx-3 mt-3 d-flex gap-2 flex-wrap">
    <a href="{{ route('unavailability.index', ['tab' => 'section', 'status' => $status]) }}"
       class="btn btn-sm {{ $tab === 'section' ? 'btn-primary' : 'btn-outline-secondary' }}">
        <i class="fas fa-users me-1"></i> {{ __('unavailability.tab_section') }}
    </a>
    <a href="{{ route('unavailability.index', ['tab' => 'mine', 'status' => $status]) }}"
       class="btn btn-sm {{ $tab === 'mine' ? 'btn-primary' : 'btn-outline-secondary' }}">
        <i class="fas fa-user me-1"></i> {{ __('unavailability.tab_mine') }}
    </a>
    <span class="ms-2 d-flex gap-1">
        <a href="{{ route('unavailability.index', ['tab' => $tab, 'status' => 'pending']) }}"
           class="btn btn-sm {{ $status === 'pending' ? 'btn-warning' : 'btn-outline-secondary' }}">{{ __('unavailability.status_pending') }}</a>
        <a href="{{ route('unavailability.index', ['tab' => $tab, 'status' => 'accepted']) }}"
           class="btn btn-sm {{ $status === 'accepted' ? 'btn-success' : 'btn-outline-secondary' }}">{{ __('unavailability.status_accepted') }}</a>
        <a href="{{ route('unavailability.index', ['tab' => $tab, 'status' => 'all']) }}"
           class="btn btn-sm {{ $status === 'all' ? 'btn-secondary' : 'btn-outline-secondary' }}">{{ __('unavailability.status_all') }}</a>
    </span>
</div>

<x-ob-toolbar
    :title="__('unavailability.page_title')"
    :total="$items->total()"
    :columns="$columns"
    table-id="indispoTable">

    <a href="{{ route('unavailability.create') }}" class="btn btn-sm btn-primary">
        <i class="fas fa-plus me-1"></i> {{ __('unavailability.declare_absence') }}
    </a>
</x-ob-toolbar>

@if(session('status'))
    <div class="mx-3 mt-2"><div class="alert alert-success py-2 mb-0">{{ session('status') }}</div></div>
@endif

<x-ob-commandbar table-id="indispoTable" :total="$items->total()" total-label="indisponibilité">
    <x-ob-table
        :columns="$columns"
        :items="$items"
        storage-key="indispoColsV2"
        :show-select="false"
        table-id="indispoTable"
    />
    <x-slot:pagination>{{ $items->links() }}</x-slot:pagination>
</x-ob-commandbar>

@endsection
