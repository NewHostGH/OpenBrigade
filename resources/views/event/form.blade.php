@extends('layout.app')

@section('title', ($event ? __('event.btn_edit') : __('event.form_title_new')) . ' — ' . config('app.name'))

@section('content')

@php
    $isEdit      = $event !== null;
    $formAction  = $isEdit ? route('event.update', $event->E_CODE) : route('event.store');
    $user        = auth()->user();
    $userSection = (int) $user->P_SECTION;

    $val      = fn(string $field, $default = '') => old($field, $isEdit ? ($event->$field ?? $default) : $default);
    $horaires = $horaires ?? collect();
    // Build initial horaire rows: old() values override, else existing horaires, else one blank
    $horaireRows = old('horaires', $horaires->map(fn($h) => [
        'EH_DATE_DEBUT' => $h->EH_DATE_DEBUT ? \Carbon\Carbon::parse($h->EH_DATE_DEBUT)->format('Y-m-d') : '',
        'EH_DATE_FIN'   => $h->EH_DATE_FIN   ? \Carbon\Carbon::parse($h->EH_DATE_FIN)->format('Y-m-d')   : '',
        'EH_DEBUT'      => $h->EH_DEBUT ? substr($h->EH_DEBUT, 0, 5) : '',
        'EH_FIN'        => $h->EH_FIN   ? substr($h->EH_FIN,   0, 5) : '',
    ])->toArray()) ?: [['EH_DATE_DEBUT' => '', 'EH_DATE_FIN' => '', 'EH_DEBUT' => '', 'EH_FIN' => '']];

    $breadcrumb = [['label' => __('event.title'), 'url' => route('event.index')]];
    if ($isEdit) {
        $breadcrumb[] = ['label' => $event->E_LIBELLE ?? $event->E_CODE, 'url' => route('event.show', $event->E_CODE)];
        $breadcrumb[] = ['label' => __('event.btn_edit')];
    } else {
        $breadcrumb[] = ['label' => __('event.form_title_new')];
    }
@endphp

<x-ob-breadcrumb :items="$breadcrumb"/>

<div class="mx-3 mt-3">
<div class="ob-widget-card">

    <div class="ob-widget-card-header">
        <div class="ob-widget-card-title">
            <i class="fas fa-{{ $isEdit ? 'edit' : 'plus-circle' }}"></i>
            {{ $isEdit ? __('event.btn_edit') . ' — ' . ($event->E_LIBELLE ?? $event->E_CODE) : __('event.form_title_new') }}
        </div>
        @if($isEdit)
            <a href="{{ route('event.show', $event->E_CODE) }}" class="btn btn-sm btn-outline-secondary">
                <i class="fas fa-eye me-1"></i> {{ __('event.form_btn_view') }}
            </a>
        @endif
    </div>

    <div class="ob-widget-card-body">

        @if ($errors->any())
            <div class="alert alert-danger py-2 mb-4">
                <ul class="mb-0 ps-3">
                    @foreach ($errors->all() as $error)
                        <li style="font-size:var(--font-size-sm)">{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="POST" action="{{ $formAction }}">
            @csrf
            @if ($isEdit) @method('PUT') @endif

            <div class="row g-4">

                {{-- ════════════════════════════════════════════════════════
                     LEFT COLUMN
                     ════════════════════════════════════════════════════════ --}}
                <div class="col-lg-7">

                    {{-- Identification --}}
                    <p class="ob-form-label"><i class="fas fa-id-card me-1"></i> {{ __('event.form_section_identity') }}</p>

                    <div class="row g-3 mb-4">
                        <div class="col-sm-4">
                            <label class="form-label fw-semibold" for="TE_CODE">
                                {{ __('event.form_type_label') }} <span class="text-danger">*</span>
                            </label>
                            <select id="TE_CODE" name="TE_CODE"
                                    class="form-select form-select-sm @error('TE_CODE') is-invalid @enderror"
                                    required>
                                <option value="">{{ __('event.form_choose') }}</option>
                                @foreach ($groupedTypes as $group)
                                    <optgroup label="{{ ucfirst($group['label']) }}">
                                        @foreach ($group['types'] as $t)
                                            <option value="{{ $t->TE_CODE }}"
                                                    {{ $val('TE_CODE') === $t->TE_CODE ? 'selected' : '' }}>
                                                {{ $t->TE_LIBELLE }}
                                            </option>
                                        @endforeach
                                    </optgroup>
                                @endforeach
                            </select>
                            @error('TE_CODE')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-sm-8">
                            <label class="form-label fw-semibold" for="E_LIBELLE">
                                {{ __('event.form_intitule_label') }} <span class="text-danger">*</span>
                            </label>
                            <input type="text" id="E_LIBELLE" name="E_LIBELLE"
                                   class="form-control form-control-sm @error('E_LIBELLE') is-invalid @enderror"
                                   value="{{ $val('E_LIBELLE') }}"
                                   maxlength="60" required autofocus>
                            @error('E_LIBELLE')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        {{-- Renfort: attach to the main event (only while not attached) --}}
                        @php $renfortType = \App\Http\Controllers\EventController::RENFORT_TYPE; @endphp
                        <div class="col-12" data-renfort-parent data-renfort-type="{{ $renfortType }}"
                             @if($val('TE_CODE') !== $renfortType) hidden @endif>
                            @if($renfortParent)
                                <div class="alert alert-info py-2 mb-0" style="font-size:var(--font-size-sm)">
                                    <i class="fas fa-link me-1"></i>
                                    {{ __('event.form_renfort_attached') }}
                                    <a href="{{ route('event.show', $renfortParent->E_CODE) }}" class="fw-semibold">{{ $renfortParent->E_LIBELLE ?: $renfortParent->E_CODE }}</a>.
                                    <span class="text-muted">{{ __('event.form_renfort_detach_hint') }}</span>
                                </div>
                            @else
                                <label class="form-label fw-semibold" for="E_PARENT">{{ __('event.form_renfort_parent_label') }}</label>
                                <select id="E_PARENT" name="E_PARENT"
                                        class="form-select form-select-sm @error('E_PARENT') is-invalid @enderror"
                                        @disabled($parentCandidates->isEmpty())>
                                    <option value="">{{ __('event.form_renfort_parent_none') }}</option>
                                    @foreach($parentCandidates as $c)
                                        <option value="{{ $c->E_CODE }}" @selected((string) old('E_PARENT') === (string) $c->E_CODE)>{{ $c->label }}</option>
                                    @endforeach
                                </select>
                                <small class="text-muted">
                                    {{ $parentCandidates->isEmpty() ? __('event.form_renfort_parent_empty') : __('event.form_renfort_parent_hint') }}
                                </small>
                            @endif
                        </div>
                    </div>

                    {{-- Localisation --}}
                    <p class="ob-form-label"><i class="fas fa-map-marker-alt me-1"></i> {{ __('event.form_section_location') }}</p>

                    <div class="row g-3 mb-2">
                        <div class="col-sm-6">
                            <label class="form-label fw-semibold" for="E_LIEU">{{ __('event.form_lieu_label') }}</label>
                            <input type="text" id="E_LIEU" name="E_LIEU"
                                   class="form-control form-control-sm @error('E_LIEU') is-invalid @enderror"
                                   value="{{ $val('E_LIEU') }}" maxlength="50">
                            @error('E_LIEU')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        @feature('multi_site')
                        <div class="col-sm-6">
                            <label class="form-label fw-semibold" for="S_ID">{{ __('event.form_section_label') }}</label>
                            <select id="S_ID" name="S_ID"
                                    class="form-select form-select-sm @error('S_ID') is-invalid @enderror">
                                @foreach ($sections as $s)
                                    <option value="{{ $s->S_ID }}"
                                            {{ (int) $val('S_ID', $userSection) === (int) $s->S_ID ? 'selected' : '' }}>
                                        {{ $s->S_CODE }}{{ $s->S_DESCRIPTION ? ' — ' . $s->S_DESCRIPTION : '' }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        @else
                            {{-- Multi sites désactivé : la donnée est conservée telle quelle. --}}
                            <input type="hidden" name="S_ID" value="{{ $val('S_ID', $userSection) }}">
                        @endfeature
                    </div>
                    <div class="row g-3 mb-4">
                        <div class="col-12">
                            <label class="form-label" for="E_ADDRESS">{{ __('event.form_address_label') }}</label>
                            <input type="text" id="E_ADDRESS" name="E_ADDRESS"
                                   class="form-control form-control-sm"
                                   value="{{ $val('E_ADDRESS') }}" maxlength="255">
                        </div>
                        <div class="col-sm-8">
                            <label class="form-label" for="E_LIEU_RDV">{{ __('event.form_lieu_rdv_label') }}</label>
                            <input type="text" id="E_LIEU_RDV" name="E_LIEU_RDV"
                                   class="form-control form-control-sm"
                                   value="{{ $val('E_LIEU_RDV') }}" maxlength="150">
                        </div>
                        <div class="col-sm-4">
                            <label class="form-label" for="E_HEURE_RDV">{{ __('event.form_heure_rdv_label') }}</label>
                            <input type="time" id="E_HEURE_RDV" name="E_HEURE_RDV"
                                   class="form-control form-control-sm"
                                   value="{{ $val('E_HEURE_RDV') ? substr($val('E_HEURE_RDV'), 0, 5) : '' }}">
                        </div>
                    </div>

                    {{-- Organisation --}}
                    <p class="ob-form-label"><i class="fas fa-people-carry me-1"></i> {{ __('event.form_section_org') }}</p>

                    <div class="row g-3 mb-4">
                        <div class="col-sm">
                            <label class="form-label fw-semibold" for="E_CHEF">{{ __('event.form_chef_label') }}</label>
                            <select id="E_CHEF" name="E_CHEF"
                                    class="form-select form-select-sm @error('E_CHEF') is-invalid @enderror">
                                <option value="">{{ __('event.form_chef_none') }}</option>
                                @foreach ($chefs as $chef)
                                    <option value="{{ $chef->P_ID }}"
                                            {{ (int) $val('E_CHEF') === (int) $chef->P_ID ? 'selected' : '' }}>
                                        {{ strtoupper($chef->P_NOM) }} {{ ucfirst(mb_strtolower($chef->P_PRENOM)) }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-sm-3">
                            <label class="form-label" for="E_TEL">{{ __('event.form_tel_label') }}</label>
                            <input type="tel" id="E_TEL" name="E_TEL"
                                   class="form-control form-control-sm"
                                   value="{{ $val('E_TEL') }}" maxlength="15">
                        </div>
                        <div class="col-auto" style="max-width:90px;">
                            <label class="form-label" for="E_NB">{{ __('event.form_effectif_label') }}</label>
                            <input type="number" id="E_NB" name="E_NB"
                                   class="form-control form-control-sm"
                                   value="{{ $val('E_NB', 0) }}" min="0" max="9999">
                        </div>
                    </div>

                    {{-- Contact sur place --}}
                    <p class="ob-form-label"><i class="fas fa-address-book me-1"></i> {{ __('event.form_section_contact') }}</p>

                    <div class="row g-3 mb-4">
                        <div class="col-sm-6">
                            <label class="form-label" for="E_CONTACT_LOCAL">{{ __('event.form_contact_name_label') }}</label>
                            <input type="text" id="E_CONTACT_LOCAL" name="E_CONTACT_LOCAL"
                                   class="form-control form-control-sm"
                                   value="{{ $val('E_CONTACT_LOCAL') }}" maxlength="50">
                        </div>
                        <div class="col-sm-3">
                            <label class="form-label" for="E_CONTACT_TEL">{{ __('event.form_contact_tel_label') }}</label>
                            <input type="tel" id="E_CONTACT_TEL" name="E_CONTACT_TEL"
                                   class="form-control form-control-sm"
                                   value="{{ $val('E_CONTACT_TEL') }}" maxlength="20">
                        </div>
                        <div class="col-sm-3">
                            <label class="form-label" for="E_WHATSAPP">{{ __('event.form_whatsapp_label') }}</label>
                            <input type="text" id="E_WHATSAPP" name="E_WHATSAPP"
                                   class="form-control form-control-sm"
                                   value="{{ $val('E_WHATSAPP') }}" maxlength="30">
                        </div>
                    </div>

                    {{-- Conférence --}}
                    <p class="ob-form-label"><i class="fas fa-video me-1"></i> {{ __('event.form_section_conf') }}</p>

                    <div class="row g-3 mb-4">
                        <div class="col-12">
                            <label class="form-label" for="E_WEBEX_URL">{{ __('event.form_conf_url_label') }}</label>
                            <input type="url" id="E_WEBEX_URL" name="E_WEBEX_URL"
                                   class="form-control form-control-sm"
                                   value="{{ $val('E_WEBEX_URL') }}" maxlength="500">
                        </div>
                        <div class="col-sm-8">
                            <label class="form-label" for="E_WEBEX_PIN">{{ __('event.form_conf_pin_label') }}</label>
                            <input type="text" id="E_WEBEX_PIN" name="E_WEBEX_PIN"
                                   class="form-control form-control-sm"
                                   value="{{ $val('E_WEBEX_PIN') }}" maxlength="20">
                        </div>
                        <div class="col-sm-4">
                            <label class="form-label" for="E_WEBEX_START">{{ __('event.form_conf_start_label') }}</label>
                            <input type="time" id="E_WEBEX_START" name="E_WEBEX_START"
                                   class="form-control form-control-sm"
                                   value="{{ $val('E_WEBEX_START') ? substr($val('E_WEBEX_START'), 0, 5) : '' }}">
                        </div>
                    </div>

                </div>

                {{-- ════════════════════════════════════════════════════════
                     RIGHT COLUMN
                     ════════════════════════════════════════════════════════ --}}
                <div class="col-lg-5">

                    {{-- Créneaux --}}
                    <div class="d-flex align-items-center justify-content-between mb-1">
                        <p class="ob-form-label mb-0"><i class="fas fa-clock me-1"></i> {{ __('event.form_section_creneaux') }} <span class="text-danger">*</span></p>
                        <button type="button" class="btn btn-xs btn-outline-primary py-0 px-2"
                                id="addHoraire" style="font-size:var(--font-size-xs)">
                            <i class="fas fa-plus me-1"></i> {{ __('event.form_add_partie') }}
                        </button>
                    </div>

                    <div id="horairesContainer">
                        @foreach($horaireRows as $i => $h)
                        <fieldset class="border rounded p-3 mb-2 ob-horaire-fieldset">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <legend class="float-none w-auto mb-0 px-1"
                                        style="font-size:var(--font-size-xs); font-weight:600;
                                               color:var(--text-muted-soft); text-transform:uppercase; letter-spacing:.04em;">
                                    {{ __('event.form_partie_label') }} <span class="ob-partie-num">{{ $i + 1 }}</span>
                                </legend>
                                @if($i > 0)
                                <button type="button" class="btn btn-xs btn-light py-0 px-1 text-danger ob-remove-horaire">
                                    <i class="fas fa-times"></i>
                                </button>
                                @endif
                            </div>
                            <div class="row g-2">
                                <div class="col-6">
                                    <label class="form-label">{{ __('event.form_date_debut_label') }} <span class="text-danger">*</span></label>
                                    <input type="date" name="horaires[{{ $i }}][EH_DATE_DEBUT]"
                                           class="form-control form-control-sm"
                                           value="{{ $h['EH_DATE_DEBUT'] ?? '' }}" required>
                                </div>
                                <div class="col-6">
                                    <label class="form-label">{{ __('event.form_date_fin_label') }}</label>
                                    <input type="date" name="horaires[{{ $i }}][EH_DATE_FIN]"
                                           class="form-control form-control-sm"
                                           value="{{ $h['EH_DATE_FIN'] ?? '' }}">
                                </div>
                                <div class="col-6">
                                    <label class="form-label">{{ __('event.form_heure_debut_label') }}</label>
                                    <input type="time" name="horaires[{{ $i }}][EH_DEBUT]"
                                           class="form-control form-control-sm"
                                           value="{{ $h['EH_DEBUT'] ?? '' }}">
                                </div>
                                <div class="col-6">
                                    <label class="form-label">{{ __('event.form_heure_fin_label') }}</label>
                                    <input type="time" name="horaires[{{ $i }}][EH_FIN]"
                                           class="form-control form-control-sm"
                                           value="{{ $h['EH_FIN'] ?? '' }}">
                                </div>
                            </div>
                        </fieldset>
                        @endforeach
                    </div>

                    {{-- Statut --}}
                    <p class="ob-form-label"><i class="fas fa-flag me-1"></i> {{ __('event.form_section_status') }}</p>

                    <div class="d-flex flex-column gap-2 mb-4">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="E_OPEN_TO_EXT"
                                   name="E_OPEN_TO_EXT" value="1"
                                   {{ $val('E_OPEN_TO_EXT') ? 'checked' : '' }}>
                            <label class="form-check-label" for="E_OPEN_TO_EXT">{{ __('event.form_open_to_ext') }}</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="E_VISIBLE_OUTSIDE"
                                   name="E_VISIBLE_OUTSIDE" value="1"
                                   {{ $val('E_VISIBLE_OUTSIDE') ? 'checked' : '' }}>
                            <label class="form-check-label" for="E_VISIBLE_OUTSIDE">{{ __('event.form_visible_outside') }}</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="E_EXTERIEUR"
                                   name="E_EXTERIEUR" value="1"
                                   {{ $val('E_EXTERIEUR') ? 'checked' : '' }}>
                            <label class="form-check-label" for="E_EXTERIEUR">{{ __('event.form_exterieur') }}</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="E_HIDDEN"
                                   name="E_HIDDEN" value="1"
                                   {{ $isEdit && !$val('E_VISIBLE_INSIDE', 1) ? 'checked' : '' }}>
                            <label class="form-check-label" for="E_HIDDEN">{{ __('event.form_hidden') }}</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="E_ALLOW_REINFORCEMENT"
                                   name="E_ALLOW_REINFORCEMENT" value="1"
                                   {{ $val('E_ALLOW_REINFORCEMENT') ? 'checked' : '' }}>
                            <label class="form-check-label" for="E_ALLOW_REINFORCEMENT">{{ __('event.form_allow_reinf') }}</label>
                        </div>
                        @if ($isEdit)
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="E_CLOSED"
                                       name="E_CLOSED" value="1"
                                       {{ $val('E_CLOSED') ? 'checked' : '' }}>
                                <label class="form-check-label" for="E_CLOSED">{{ __('event.form_closed') }}</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="E_CANCELED"
                                       name="E_CANCELED" value="1"
                                       {{ $val('E_CANCELED') ? 'checked' : '' }}>
                                <label class="form-check-label text-danger" for="E_CANCELED">{{ __('event.form_canceled') }}</label>
                            </div>
                        @endif
                        <div class="row g-2 mt-1 align-items-center">
                            <div class="col-auto">
                                <label class="form-label mb-0" for="E_AUTOCLOSE_BEFORE" style="font-size:var(--font-size-sm)">
                                    {{ __('event.form_autoclose_label') }}
                                </label>
                            </div>
                            <div class="col-auto">
                                <input type="number" id="E_AUTOCLOSE_BEFORE" name="E_AUTOCLOSE_BEFORE"
                                       class="form-control form-control-sm" style="width:70px"
                                       value="{{ $val('E_AUTOCLOSE_BEFORE') }}" min="0" max="999">
                            </div>
                            <div class="col-auto">
                                <span style="font-size:var(--font-size-sm);color:var(--text-muted-soft)">{{ __('event.form_autoclose_unit') }}</span>
                            </div>
                        </div>
                    </div>

                </div>

            </div>{{-- /row g-4 --}}

            {{-- ── Full-width bottom fields ─────────────────────────────────────── --}}

            <p class="ob-form-label mt-2"><i class="fas fa-lock me-1"></i> {{ __('event.form_consignes_label') }} <span class="text-muted fw-normal" style="font-size:var(--font-size-xs)">{{ __('event.form_consignes_hint') }}</span></p>
            <div class="mb-4">
                <textarea id="E_CONSIGNES" name="E_CONSIGNES" rows="2"
                          class="form-control form-control-sm" maxlength="500">{{ $val('E_CONSIGNES') }}</textarea>
            </div>

            <p class="ob-form-label"><i class="fas fa-sticky-note me-1"></i> {{ __('event.form_comment_label') }} <span class="text-muted fw-normal" style="font-size:var(--font-size-xs)">{{ __('event.form_comment_hint') }}</span></p>
            <div class="mb-4">
                <textarea id="E_COMMENT" name="E_COMMENT" rows="3"
                          class="form-control form-control-sm" maxlength="5000">{{ $val('E_COMMENT') }}</textarea>
            </div>

            {{-- Actions --}}
            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-primary btn-sm">
                    <i class="fas fa-save me-1"></i>
                    {{ $isEdit ? __('event.form_btn_save') : __('event.form_btn_create') }}
                </button>
                <a href="{{ $isEdit ? route('event.show', $event->E_CODE) : route('event.index') }}"
                   class="btn btn-outline-secondary btn-sm">
                    {{ __('common.cancel') }}
                </a>
            </div>

        </form>
    </div>{{-- /ob-widget-card-body --}}
</div>{{-- /ob-widget-card --}}

{{-- Delete zone (edit only) --}}
@if ($isEdit && auth()->user()->hasPermission(19))
    <div class="ob-widget-card mt-3" style="border-color:var(--bs-danger) !important;">
        <div class="ob-widget-card-header text-danger">
            <div class="ob-widget-card-title">
                <i class="fas fa-trash me-1"></i> {{ __('event.danger_zone_title') }}
            </div>
        </div>
        <div class="ob-widget-card-body">
            <p class="text-muted small mb-2">
                {{ __('event.danger_zone_desc') }}
            </p>
            <form method="POST" action="{{ route('event.destroy', $event->E_CODE) }}"
                  onsubmit="return confirm('{{ __('event.confirm_delete_event') }}');">
                @csrf @method('DELETE')
                <button type="submit" class="btn btn-danger btn-sm">
                    <i class="fas fa-trash me-1"></i> {{ __('event.btn_delete_event') }}
                </button>
            </form>
        </div>
    </div>
@endif

</div>

@push('scripts')
@vite('resources/js/ob-event-form.js')
@endpush

@endsection
