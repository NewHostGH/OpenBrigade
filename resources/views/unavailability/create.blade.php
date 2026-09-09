@extends('layout.app')

@section('title', __('unavailability.declare_absence') . ' — ' . config('app.name'))

@section('content')

<x-ob-breadcrumb :items="[
    ['label' => __('unavailability.breadcrumb'), 'url' => route('unavailability.index')],
    ['label' => __('unavailability.declare_absence')],
]"/>

<div class="mx-3 mt-3">
    <div class="ob-widget-card mb-3">
        <div class="ob-widget-card-header">
            <div class="ob-widget-card-title"><i class="fas fa-user-clock me-2"></i>{{ __('unavailability.declare_absence') }}</div>
        </div>
        <div class="p-3">

            <form method="POST" action="{{ route('unavailability.store') }}">
                @csrf

                @if($errors->any())
                    <div class="alert alert-danger py-2 mb-3">
                        <ul class="mb-0 ps-3" style="font-size:var(--font-size-sm);">
                            @foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach
                        </ul>
                    </div>
                @endif

                <div class="row g-3 mb-2">
                    @if($canManage && $personnel->isNotEmpty())
                        <div class="col-md-6">
                            <label class="form-label form-label-sm">{{ __('unavailability.field_person') }}</label>
                            <select name="person" class="form-select form-select-sm">
                                <option value="">{{ __('unavailability.field_person_self') }}</option>
                                @foreach($personnel as $p)
                                    <option value="{{ $p->P_ID }}" @selected(old('person') == $p->P_ID)>
                                        {{ strtoupper($p->P_NOM) }} {{ $p->P_PRENOM }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    @endif

                    <div class="col-md-6">
                        <label class="form-label form-label-sm">{{ __('unavailability.field_type') }} <span class="text-danger">*</span></label>
                        <select name="type" class="form-select form-select-sm" required>
                            <option value="">—</option>
                            @foreach($types as $t)
                                <option value="{{ $t->TI_CODE }}" @selected(old('type') === $t->TI_CODE)>
                                    {{ $t->TI_LIBELLE }}@if($t->TI_FLAG) — {{ __('unavailability.type_needs_validation') }}@endif
                                </option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label form-label-sm d-block">{{ __('unavailability.field_scope') }}</label>
                    @php $scope = old('scope', 'full'); @endphp
                    <div class="btn-group btn-group-sm flex-wrap" role="group">
                        @foreach(['full' => 'scope_full', 'morning' => 'scope_morning', 'afternoon' => 'scope_afternoon', 'hours' => 'scope_hours'] as $val => $key)
                            <input type="radio" class="btn-check" name="scope" id="scope-{{ $val }}" value="{{ $val }}" @checked($scope === $val)>
                            <label class="btn btn-outline-primary" for="scope-{{ $val }}">{{ __('unavailability.'.$key) }}</label>
                        @endforeach
                    </div>
                </div>

                <div class="row g-3 mb-2">
                    <div class="col-md-3 col-6">
                        <label class="form-label form-label-sm">{{ __('unavailability.field_start') }} <span class="text-danger">*</span></label>
                        <input type="date" name="debut" class="form-control form-control-sm" value="{{ old('debut') }}" required>
                    </div>
                    <div class="col-md-3 col-6">
                        <label class="form-label form-label-sm">{{ __('unavailability.field_end') }}</label>
                        <input type="date" name="fin" class="form-control form-control-sm" value="{{ old('fin') }}">
                    </div>
                    <div class="col-md-3 col-6" data-hours-field>
                        <label class="form-label form-label-sm">{{ __('unavailability.field_hour_start') }}</label>
                        <input type="time" name="heure_debut" class="form-control form-control-sm" value="{{ old('heure_debut') }}">
                    </div>
                    <div class="col-md-3 col-6" data-hours-field>
                        <label class="form-label form-label-sm">{{ __('unavailability.field_hour_end') }}</label>
                        <input type="time" name="heure_fin" class="form-control form-control-sm" value="{{ old('heure_fin') }}">
                    </div>
                </div>
                <p class="text-muted mb-3" style="font-size:var(--font-size-xs)">
                    <i class="fas fa-info-circle me-1"></i>{{ __('unavailability.field_end_hint') }}
                    {{ __('unavailability.field_hours_hint') }}
                </p>

                <div class="mb-3">
                    <label class="form-label form-label-sm">{{ __('unavailability.field_comment') }}</label>
                    <input type="text" name="comment" class="form-control form-control-sm" maxlength="50" value="{{ old('comment') }}">
                </div>

                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-sm btn-primary"><i class="fas fa-check me-1"></i> {{ __('unavailability.declare_absence') }}</button>
                    <a href="{{ route('unavailability.index') }}" class="btn btn-sm btn-outline-secondary">{{ __('common.back') }}</a>
                </div>
            </form>
        </div>
    </div>
</div>

@endsection
