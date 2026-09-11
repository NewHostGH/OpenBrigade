@extends('layout.app')

@section('title', ($event->E_LIBELLE ?? $event->E_CODE) . ' — Activités — ' . config('app.name'))

@section('content')

<x-ob-breadcrumb :items="[
    ['label' => __('event.title'), 'url' => route('event.index')],
    ['label' => $event->E_LIBELLE ?? $event->E_CODE ?? ''],
]"/>

<div class="mx-3 mt-3">

    {{-- ── Header card ───────────────────────────────────────────────────────── --}}
    <div class="ob-widget-card mb-3">
        <div class="ob-widget-card-header">
            <div class="ob-widget-card-title">
                <i class="fas fa-calendar-alt"></i>
                {{ $event->E_LIBELLE ?? $event->E_CODE }}
                @if($event->E_CANCELED)
                    <span class="ob-badge ob-badge-bloqued ms-2">{{ __('event.status_canceled') }}</span>
                @elseif($event->E_CLOSED)
                    <span class="ob-badge ob-badge-archive ms-2">{{ __('event.status_closed') }}</span>
                @else
                    <span class="ob-badge ob-badge-actif ms-2">{{ __('event.status_open') }}</span>
                @endif

                {{-- Informational status flags (display-only; persisted on the event form) --}}
                @if($event->E_OPEN_TO_EXT)
                    <span class="ob-badge ob-badge-ext ms-2" title="{{ __('event.flag_open_to_ext_title') }}">
                        <i class="fas fa-user-friends me-1"></i>{{ __('event.flag_open_to_ext') }}
                    </span>
                @endif
                @if($event->E_VISIBLE_OUTSIDE)
                    <span class="ob-badge ob-badge-int ms-2" title="{{ __('event.flag_visible_outside_title') }}">
                        <i class="fas fa-eye me-1"></i>{{ __('event.flag_visible_outside') }}
                    </span>
                @endif
                @if($event->E_EXTERIEUR)
                    <span class="ob-badge ob-badge-archive ms-2" title="{{ __('event.flag_exterieur_title') }}">
                        <i class="fas fa-map-marker-alt me-1"></i>{{ __('event.flag_exterieur') }}
                    </span>
                @endif
                @if(!$event->E_VISIBLE_INSIDE)
                    <span class="ob-badge ob-badge-bloqued ms-2" title="{{ __('event.flag_hidden_title') }}">
                        <i class="fas fa-eye-slash me-1"></i>{{ __('event.flag_hidden') }}
                    </span>
                @endif
            </div>
            <div class="d-flex gap-2">
                <a href="{{ route('event.trombinoscope', $event->E_CODE) }}"
                   class="btn btn-sm btn-outline-secondary" title="{{ __('event.btn_trombinoscope_title') }}">
                    <i class="fas fa-id-badge me-1"></i> {{ __('event.btn_trombinoscope') }}
                </a>
                <a href="{{ route('event.export.participants', $event->E_CODE) }}"
                   class="btn btn-sm btn-outline-secondary" title="{{ __('event.btn_export_xls_title') }}">
                    <i class="fas fa-file-excel me-1"></i> {{ __('event.btn_export_xls') }}
                </a>
                <a href="{{ route('event.ical', $event->E_CODE) }}"
                   class="btn btn-sm btn-outline-secondary" title="{{ __('event.btn_ical_title') }}">
                    <i class="fas fa-calendar-plus me-1"></i> iCal
                </a>
                @if(auth()->user()->hasPermission(15))
                    <a href="{{ route('event.report', $event->E_CODE) }}"
                       class="btn btn-sm btn-outline-secondary" title="{{ __('event.btn_report_title') }}">
                        <i class="fas fa-file-alt me-1"></i> {{ __('event.btn_report') }}
                    </a>
                    <button type="button" class="btn btn-sm btn-outline-secondary"
                            data-bs-toggle="modal" data-bs-target="#duplicateModal"
                            title="{{ __('event.btn_duplicate_title') }}">
                        <i class="fas fa-copy me-1"></i> {{ __('event.btn_duplicate') }}
                    </button>
                    <a href="{{ route('event.edit', $event->E_CODE) }}"
                       class="btn btn-sm btn-outline-secondary">
                        <i class="fas fa-edit me-1"></i> {{ __('event.btn_edit') }}
                    </a>
                @endif
                <a href="{{ route('event.index') }}" class="btn btn-sm btn-outline-secondary">
                    <i class="fas fa-arrow-left me-1"></i> {{ __('event.btn_back') }}
                </a>
            </div>
        </div>

        <div class="ob-widget-card-body">
            <div class="row g-4">

                {{-- ── Identity ─────────────────────────────────────────────── --}}
                <div class="col-md-5">
                    <dl class="mb-0" style="display:grid; grid-template-columns:auto 1fr; gap:5px 16px;
                                            font-size:var(--font-size-sm); align-items:baseline;">

                        <dt class="text-muted fw-normal">{{ __('event.field_type') }}</dt>
                        <dd class="mb-0">{{ $typeLabel ?? $event->TE_CODE ?? '—' }}</dd>

                        <dt class="text-muted fw-normal">{{ __('event.field_lieu') }}</dt>
                        <dd class="mb-0">{{ $event->E_LIEU ?: '—' }}</dd>

                        @feature('multi_site')
                        <dt class="text-muted fw-normal">{{ __('event.field_section') }}</dt>
                        <dd class="mb-0">{{ $event->section?->S_DESCRIPTION ?? '—' }}</dd>
                        @endfeature

                        @if($event->chef)
                            <dt class="text-muted fw-normal">{{ __('event.field_responsable') }}</dt>
                            <dd class="mb-0">
                                <a href="{{ route('personnel.show', $event->chef->P_ID) }}"
                                   class="text-decoration-none">
                                    {{ $event->chef->P_PRENOM }} {{ strtoupper($event->chef->P_NOM) }}
                                </a>
                                @if($event->E_TEL)
                                    <span class="text-muted ms-1">— {{ $event->E_TEL }}</span>
                                @endif
                            </dd>
                        @endif

                        @if($event->E_NB)
                            <dt class="text-muted fw-normal">{{ __('event.field_effectif') }}</dt>
                            <dd class="mb-0">{{ $event->E_NB }}</dd>
                        @endif

                        @if($event->E_ADDRESS)
                            <dt class="text-muted fw-normal">{{ __('event.field_adresse') }}</dt>
                            <dd class="mb-0">{{ $event->E_ADDRESS }}</dd>
                        @endif

                        @if($event->E_LIEU_RDV || $event->E_HEURE_RDV)
                            <dt class="text-muted fw-normal">{{ __('event.field_rdv') }}</dt>
                            <dd class="mb-0">
                                {{ $event->E_LIEU_RDV ?: '' }}
                                @if($event->E_HEURE_RDV)
                                    <span class="text-muted">{{ __('event.at_time', ['time' => substr($event->E_HEURE_RDV, 0, 5)]) }}</span>
                                @endif
                            </dd>
                        @endif

                        @if($event->E_ALLOW_REINFORCEMENT)
                            <dt class="text-muted fw-normal">{{ __('event.field_renforts') }}</dt>
                            <dd class="mb-0"><span class="ob-badge ob-badge-actif">{{ __('event.renforts_actives') }}</span></dd>
                        @endif

                        @if($event->E_CONTACT_LOCAL || $event->E_CONTACT_TEL)
                            <dt class="text-muted fw-normal">{{ __('event.field_contact') }}</dt>
                            <dd class="mb-0">
                                {{ $event->E_CONTACT_LOCAL ?: '' }}
                                @if($event->E_CONTACT_TEL)
                                    <span class="text-muted">— {{ $event->E_CONTACT_TEL }}</span>
                                @endif
                            </dd>
                        @endif

                        @if($event->E_WEBEX_URL)
                            <dt class="text-muted fw-normal">{{ __('event.field_conference') }}</dt>
                            <dd class="mb-0">
                                <a href="{{ $event->E_WEBEX_URL }}" target="_blank" class="text-decoration-none">
                                    <i class="fas fa-video me-1"></i>{{ __('event.conf_join') }}
                                </a>
                                @if($event->E_WEBEX_PIN)
                                    <span class="text-muted ms-1">{{ __('event.conf_code') }} {{ $event->E_WEBEX_PIN }}</span>
                                @endif
                                @if($event->E_WEBEX_START)
                                    <span class="text-muted ms-1">{{ __('event.at_time', ['time' => substr($event->E_WEBEX_START, 0, 5)]) }}</span>
                                @endif
                            </dd>
                        @endif

                    </dl>

                    @if($event->E_CONSIGNES)
                        <div class="mt-2 p-2 rounded" style="background:var(--bs-warning-bg-subtle); font-size:var(--font-size-sm); border-left:3px solid var(--bs-warning);">
                            <strong><i class="fas fa-lock me-1"></i>{{ __('event.consignes_label') }}</strong>
                            {{ $event->E_CONSIGNES }}
                        </div>
                    @endif
                </div>

                {{-- ── Créneaux ─────────────────────────────────────────────── --}}
                <div class="col-md-7">
                    <div style="font-size:var(--font-size-xs); font-weight:600;
                                color:var(--text-muted-soft); text-transform:uppercase;
                                letter-spacing:.04em; margin-bottom:6px;">
                        {{ __('event.creneaux_heading') }}
                    </div>
                    @forelse($event->horaires as $h)
                        <div class="d-flex align-items-center gap-2 mb-1">
                            <span class="ob-badge ob-badge-archive">{{ $h->EH_ID }}</span>
                            <span style="font-size:var(--font-size-sm)">
                                {{ \Carbon\Carbon::parse($h->EH_DATE_DEBUT)->locale('fr')->isoFormat('ddd D MMM YYYY') }}
                                @if($h->EH_DATE_FIN && $h->EH_DATE_FIN->toDateString() !== $h->EH_DATE_DEBUT->toDateString())
                                    → {{ \Carbon\Carbon::parse($h->EH_DATE_FIN)->locale('fr')->isoFormat('D MMM') }}
                                @endif
                                @if($h->EH_DEBUT && substr($h->EH_DEBUT, 0, 5) !== '00:00')
                                    <span class="text-muted">
                                        {{ substr($h->EH_DEBUT, 0, 5) }}–{{ substr($h->EH_FIN, 0, 5) }}
                                    </span>
                                @endif
                            </span>
                        </div>
                    @empty
                        <span class="text-muted fst-italic" style="font-size:var(--font-size-sm)">{{ __('event.no_creneau') }}</span>
                    @endforelse

                    @if($event->E_COMMENT)
                        <p class="mb-0 mt-3 text-muted" style="font-size:var(--font-size-sm); white-space:pre-line;">
                            {{ $event->E_COMMENT }}
                        </p>
                    @endif
                </div>

            </div>
        </div>
    </div>

    {{-- ── Content sections ───────────────────────────────────────────────── --}}
    <div class="d-flex gap-3 align-items-start">

        {{-- Left sidebar nav — links are generated by ob-event-show.js from the
             [data-evt-section] blocks (their data-nav-* attributes), so the menu
             always matches the sections actually rendered and their order. --}}
        <div class="ob-pers-sidenav-wrap noprint">
            <div class="ob-widget-card">
                <div class="ob-widget-card-body p-0">
                    <nav id="evtSideNav"></nav>
                </div>
            </div>
        </div>

        {{-- Sections column --}}
        <div style="flex:1; min-width:0;">

    {{-- ── Section: Participants ───────────────────────────────────────────── --}}
    <div id="section-participants" data-evt-section data-nav-icon="fas fa-users" data-nav-label="Participants" data-nav-badge="{{ count($participants) ?: '' }}" class="ob-widget-card mb-3">
        <div class="ob-widget-card-header">
            <div class="ob-widget-card-title">
                <i class="fas fa-users"></i> {{ __('event.section_participants') }}
                @if(count($participants) > 0)
                    <span class="ob-badge ob-badge-archive ms-1">{{ count($participants) }}</span>
                @endif
            </div>
            @if(auth()->user()->hasPermission(10) && !$event->E_CLOSED && !$event->E_CANCELED)
                <button type="button" class="btn btn-sm btn-success"
                        data-bs-toggle="modal" data-bs-target="#addParticipantModal">
                    <i class="fas fa-user-plus me-1"></i> {{ __('event.btn_enroll') }}
                </button>
            @endif
        </div>
        <div class="ob-widget-card-body p-0">
            @if(count($participants) === 0)
                <p class="ob-widget-empty p-3">{{ __('event.participants_empty') }}</p>
            @else
                <table class="table table-sm table-hover mb-0 align-middle">
                    <thead style="background:var(--table-header-bg);color:var(--table-header-text)">
                        <tr>
                            <th style="width:36px"></th>
                            <th>{{ __('event.th_name') }}</th>
                            @feature('grades')<th>{{ __('event.th_grade') }}</th>@endfeature
                            <th>{{ __('event.th_function') }}</th>
                            <th>{{ __('event.th_team') }}</th>
                            @if($eventOptions->isNotEmpty())
                                <th style="width:40px"></th>
                            @endif
                            @if(auth()->user()->hasPermission(10))
                                <th style="width:72px"></th>
                            @endif
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($participants as $p)
                            <tr>
                                <td>
                                    <img src="{{ route('personnel.photo', $p->P_ID) }}"
                                         width="28" height="28"
                                         style="border-radius:6px;object-fit:cover;"
                                         onerror="this.src='{{ asset('images/autre.png') }}'">
                                </td>
                                <td style="font-size:var(--font-size-sm)">
                                    <a href="{{ route('personnel.show', $p->P_ID) }}"
                                       class="text-decoration-none fw-semibold">
                                        {{ $p->P_PRENOM }} {{ strtoupper($p->P_NOM) }}
                                    </a>
                                </td>
                                @feature('grades')
                                <td style="font-size:var(--font-size-xs);color:var(--text-muted-soft)">
                                    {{ $p->P_GRADE ?? '—' }}
                                </td>
                                @endfeature
                                <td style="font-size:var(--font-size-xs);color:var(--text-muted-soft)">
                                    {{ $p->TP_LIBELLE ?? '—' }}
                                </td>
                                @if(auth()->user()->hasPermission(10) && $equipes->count() > 0 && !$event->E_CLOSED && !$event->E_CANCELED)
                                    <td style="font-size:var(--font-size-xs);">
                                        <form method="POST"
                                              action="{{ route('event.participant.team', [$event->E_CODE, $p->P_ID]) }}"
                                              class="d-inline">
                                            @csrf @method('PATCH')
                                            <select name="EE_ID" onchange="this.form.submit()"
                                                    class="form-select form-select-sm"
                                                    style="font-size:var(--font-size-xs);padding:1px 20px 1px 4px;min-width:90px;">
                                                <option value="">{{ __('event.option_no_team') }}</option>
                                                @foreach($equipes as $eq)
                                                    <option value="{{ $eq->EE_ID }}"
                                                            {{ (int)$p->EE_ID === (int)$eq->EE_ID ? 'selected' : '' }}>
                                                        {{ $eq->EE_NAME }}
                                                    </option>
                                                @endforeach
                                            </select>
                                        </form>
                                    </td>
                                @else
                                    <td style="font-size:var(--font-size-xs);color:var(--text-muted-soft)">
                                        {{ $p->EE_NAME ?? '—' }}
                                    </td>
                                @endif
                                @php $pIsSelf = auth()->id() === (int)$p->P_ID; @endphp
                                @if($eventOptions->isNotEmpty() && (auth()->user()->hasPermission(15) || $pIsSelf))
                                    <td class="text-center">
                                        <button type="button"
                                                class="btn btn-xs btn-light py-0 px-1"
                                                data-bs-toggle="modal"
                                                data-bs-target="#choicesModal-{{ $p->P_ID }}"
                                                title="{{ __('event.section_options') }}">
                                            <i class="fas fa-sliders-h"></i>
                                        </button>
                                    </td>
                                @elseif($eventOptions->isNotEmpty())
                                    <td></td>
                                @endif
                                @if(auth()->user()->hasPermission(10))
                                    <td class="text-end pe-2">
                                        <button type="button"
                                                class="btn btn-xs btn-light py-0 px-1 me-1"
                                                onclick="openEditParticipant({{ json_encode([
                                                    'p_id'       => $p->P_ID,
                                                    'tp_id'      => $p->TP_ID ?? 0,
                                                    'ee_id'      => $p->EE_ID,
                                                    'ep_comment' => $p->EP_COMMENT ?? '',
                                                ]) }})">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <form method="POST"
                                              action="{{ route('event.participant.destroy', [$event->E_CODE, $p->P_ID]) }}"
                                              class="d-inline"
                                              onsubmit="return confirm('{{ addslashes(__('event.confirm_unsubscribe', ['name' => $p->P_PRENOM . ' ' . strtoupper($p->P_NOM)])) }}')">
                                            @csrf @method('DELETE')
                                            <button type="submit" class="btn btn-xs btn-light py-0 px-1 text-danger">
                                                <i class="fas fa-user-minus"></i>
                                            </button>
                                        </form>
                                    </td>
                                @endif
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>
    </div>

    {{-- ── Section: Équipes ───────────────────────────────────────────────── --}}
    <div id="section-equipes" data-evt-section data-nav-icon="fas fa-layer-group" data-nav-label="Équipes" data-nav-badge="{{ count($equipes) ?: '' }}" class="ob-widget-card mb-3">
        <div class="ob-widget-card-header">
            <div class="ob-widget-card-title">
                <i class="fas fa-layer-group"></i> {{ __('event.section_equipes') }}
                @if(count($equipes) > 0)
                    <span class="ob-badge ob-badge-archive ms-1">{{ count($equipes) }}</span>
                @endif
            </div>
            @if(auth()->user()->hasPermission(15) && !$event->E_CLOSED && !$event->E_CANCELED)
                <button type="button" class="btn btn-sm btn-success"
                        data-bs-toggle="modal" data-bs-target="#addEquipeModal">
                    <i class="fas fa-plus me-1"></i> {{ __('common.add') }}
                </button>
            @endif
        </div>
        <div class="ob-widget-card-body p-0">
            @if(count($equipes) === 0)
                <p class="ob-widget-empty p-3">{{ __('event.equipes_empty') }}</p>
            @else
                @php
                    $participantsByTeam = $participants->groupBy('EE_ID');
                    $materielsByTeam    = $materiels->groupBy('EE_ID');
                    $unassignedP = $participantsByTeam->get(null, collect())->merge($participantsByTeam->get(0, collect()));
                    $canManage   = auth()->user()->hasPermission(15) && !$event->E_CLOSED && !$event->E_CANCELED;
                    $canEnroll   = auth()->user()->hasPermission(10)  && !$event->E_CLOSED && !$event->E_CANCELED;
                @endphp
                <div class="p-3 d-flex flex-wrap gap-3 align-items-start">
                @foreach($equipes as $eq)
                    @php
                        $teamMembers   = $participantsByTeam->get($eq->EE_ID, collect());
                        $teamMateriels = $materielsByTeam->get($eq->EE_ID, collect());
                    @endphp
                    <div style="min-width:220px; flex:1; background:var(--sidebar-bg); border-radius:var(--radius-md);">
                        {{-- Team header --}}
                        <div class="d-flex align-items-center gap-2 px-2 py-1"
                             style="background:var(--card-subheader-bg); border-bottom:1px solid var(--card-subheader-border); border-radius:var(--radius-md) var(--radius-md) 0 0;">
                            <span class="fw-semibold" style="font-size:var(--font-size-sm); flex:1;">
                                {{ $eq->EE_NAME }}
                                @if($eq->EE_ID_RADIO)
                                    <span class="text-muted fw-normal ms-1" style="font-size:var(--font-size-xs)">
                                        <i class="fas fa-broadcast-tower"></i> {{ $eq->EE_ID_RADIO }}
                                    </span>
                                @endif
                            </span>
                            @if(auth()->user()->hasPermission(15))
                                <button type="button"
                                        class="btn btn-xs btn-light py-0 px-1"
                                        onclick="openEditEquipe({{ json_encode([
                                            'ee_id'    => $eq->EE_ID,
                                            'ee_name'  => $eq->EE_NAME,
                                            'ee_order' => $eq->EE_ORDER,
                                            'ee_desc'  => $eq->EE_DESCRIPTION ?? '',
                                            'ee_radio' => $eq->EE_ID_RADIO ?? '',
                                        ]) }})">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <form method="POST"
                                      action="{{ route('event.team.destroy', [$event->E_CODE, $eq->EE_ID]) }}"
                                      class="d-inline"
                                      onsubmit="return confirm('{{ addslashes(__('event.confirm_delete_team', ['name' => $eq->EE_NAME])) }}')">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="btn btn-xs btn-light py-0 px-1 text-danger">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </form>
                            @endif
                        </div>
                        @if($eq->EE_DESCRIPTION)
                            <div class="px-2 pt-1" style="font-size:var(--font-size-xs);color:var(--text-muted-soft);">
                                {{ $eq->EE_DESCRIPTION }}
                            </div>
                        @endif

                        {{-- Personnel --}}
                        <div class="px-2 pt-2 pb-1">
                            <div class="mb-1" style="font-size:var(--font-size-xs);font-weight:600;color:var(--text-muted-soft);text-transform:uppercase;letter-spacing:.04em;">
                                <i class="fas fa-users me-1"></i>{{ __('event.team_personnel_label') }} ({{ $teamMembers->count() }})
                            </div>
                            @foreach($teamMembers as $tm)
                                <div class="d-flex align-items-center gap-1 mb-1">
                                    <img src="{{ route('personnel.photo', $tm->P_ID) }}"
                                         width="20" height="20"
                                         style="border-radius:4px;object-fit:cover;flex-shrink:0;"
                                         onerror="this.src='{{ asset('images/autre.png') }}'">
                                    <span style="font-size:var(--font-size-xs);flex:1;min-width:0;">
                                        <a href="{{ route('personnel.show', $tm->P_ID) }}" class="text-decoration-none">
                                            {{ $tm->P_PRENOM }} {{ strtoupper($tm->P_NOM) }}
                                        </a>
                                        @if($tm->TP_LIBELLE)
                                            <span class="text-muted">— {{ $tm->TP_LIBELLE }}</span>
                                        @endif
                                    </span>
                                    @if($canEnroll)
                                        <form method="POST"
                                              action="{{ route('event.participant.team', [$event->E_CODE, $tm->P_ID]) }}"
                                              class="d-inline flex-shrink-0">
                                            @csrf @method('PATCH')
                                            <input type="hidden" name="EE_ID" value="">
                                            <button type="submit" class="btn btn-xs btn-light py-0 px-1 text-muted"
                                                    title="{{ __('event.team_remove_title') }}">
                                                <i class="fas fa-times"></i>
                                            </button>
                                        </form>
                                    @endif
                                </div>
                            @endforeach
                            @if($canEnroll && $unassignedP->count() > 0)
                                <form method="POST"
                                      action="{{ route('event.team.participant.add', [$event->E_CODE, $eq->EE_ID]) }}"
                                      class="d-flex gap-1 mt-1">
                                    @csrf
                                    <select name="P_ID" class="form-select form-select-sm"
                                            style="font-size:var(--font-size-xs);flex:1;">
                                        <option value="">{{ __('event.team_add_placeholder') }}</option>
                                        @foreach($unassignedP as $up)
                                            <option value="{{ $up->P_ID }}">
                                                {{ strtoupper($up->P_NOM) }} {{ $up->P_PRENOM }}
                                            </option>
                                        @endforeach
                                    </select>
                                    <button type="submit" class="btn btn-xs btn-success flex-shrink-0">
                                        <i class="fas fa-plus"></i>
                                    </button>
                                </form>
                            @endif
                        </div>

                        {{-- Matériel --}}
                        <div class="px-2 pt-1 pb-2">
                            <div class="mb-1" style="font-size:var(--font-size-xs);font-weight:600;color:var(--text-muted-soft);text-transform:uppercase;letter-spacing:.04em;">
                                <i class="fas fa-box me-1"></i>{{ __('event.team_materiel_label') }} ({{ $teamMateriels->count() }})
                            </div>
                            @foreach($teamMateriels as $tm)
                                <div class="d-flex align-items-center gap-1 mb-1">
                                    <span style="font-size:var(--font-size-xs);flex:1;">
                                        {{ $tm->EM_NB }}× {{ $tm->MA_MODELE }}
                                    </span>
                                    @if($canManage)
                                        <form method="POST"
                                              action="{{ route('event.equipment.detach', [$event->E_CODE, $tm->MA_ID]) }}"
                                              class="d-inline flex-shrink-0"
                                              onsubmit="return confirm('{{ addslashes(__('event.confirm_remove_materiel', ['name' => $tm->MA_MODELE])) }}')">
                                            @csrf @method('DELETE')
                                            <button type="submit" class="btn btn-xs btn-light py-0 px-1 text-muted"
                                                    title="{{ __('common.delete') }}">
                                                <i class="fas fa-times"></i>
                                            </button>
                                        </form>
                                    @endif
                                </div>
                            @endforeach
                            @if($canManage && $allMateriels->count() > 0)
                                <form method="POST"
                                      action="{{ route('event.team.equipment.add', [$event->E_CODE, $eq->EE_ID]) }}"
                                      class="d-flex gap-1 mt-1">
                                    @csrf
                                    <select name="MA_ID" class="form-select form-select-sm"
                                            style="font-size:var(--font-size-xs);flex:1;">
                                        <option value="">{{ __('event.team_add_placeholder') }}</option>
                                        @foreach($allMateriels as $am)
                                            <option value="{{ $am->MA_ID }}">
                                                {{ $am->MA_MODELE }}
                                            </option>
                                        @endforeach
                                    </select>
                                    <input type="number" name="EM_NB" value="1" min="1" max="9999"
                                           class="form-control form-control-sm flex-shrink-0"
                                           style="width:50px;font-size:var(--font-size-xs);">
                                    <button type="submit" class="btn btn-xs btn-success flex-shrink-0">
                                        <i class="fas fa-plus"></i>
                                    </button>
                                </form>
                            @endif
                        </div>
                    </div>
                @endforeach
                </div>
                @if($unassignedP->count() > 0)
                    <div class="px-3 pb-3">
                        <span style="font-size:var(--font-size-xs);color:var(--text-muted-soft);font-style:italic;">
                            {{ __('event.unassigned_count', ['count' => $unassignedP->count()]) }}
                        </span>
                    </div>
                @endif
            @endif
        </div>
    </div>

    {{-- ── Section: Véhicules ─────────────────────────────────────────────── --}}
    <div id="section-vehicules" data-evt-section data-nav-icon="fas fa-truck" data-nav-label="Véhicules" data-nav-badge="{{ count($vehicules) ?: '' }}" class="ob-widget-card mb-3">
        <div class="ob-widget-card-header">
            <div class="ob-widget-card-title"><i class="fas fa-truck"></i> {{ __('event.section_vehicules') }}</div>
            <div class="d-flex gap-2">
                @if(count($vehicules) > 0)
                    <a href="{{ route('event.export.vehicles', $event->E_CODE) }}"
                       class="btn btn-sm btn-outline-secondary" title="{{ __('event.btn_export_vehicles_title') }}">
                        <i class="fas fa-file-excel me-1"></i> {{ __('event.btn_export_xls') }}
                    </a>
                @endif
                @if(auth()->user()->hasPermission(15) && !$event->E_CLOSED && !$event->E_CANCELED)
                    <button type="button" class="btn btn-sm btn-success"
                            data-bs-toggle="modal" data-bs-target="#assignVehiculeModal">
                        <i class="fas fa-plus me-1"></i> {{ __('event.btn_assign_vehicle') }}
                    </button>
                @endif
            </div>
        </div>
        <div class="ob-widget-card-body p-0">
            @if(count($vehicules) === 0)
                <p class="ob-widget-empty p-3">{{ __('event.vehicules_empty') }}</p>
            @else
                <table class="table table-sm table-hover mb-0 align-middle">
                    <thead style="background:var(--table-header-bg);color:var(--table-header-text)">
                        <tr>
                            <th>{{ __('event.th_immat') }}</th>
                            <th>{{ __('event.th_indicatif') }}</th>
                            <th class="text-end">{{ __('event.th_km') }}</th>
                            @if(auth()->user()->hasPermission(15))
                                <th style="width:50px"></th>
                            @endif
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($vehicules as $v)
                            <tr>
                                <td class="fw-semibold" style="font-size:var(--font-size-sm)">
                                    <a href="{{ route('vehicle.show', $v->V_ID) }}" class="text-decoration-none">
                                        {{ $v->V_IMMATRICULATION ?: '—' }}
                                    </a>
                                </td>
                                <td style="font-size:var(--font-size-sm)">{{ $v->V_INDICATIF ?: '—' }}</td>
                                <td class="text-end" style="font-size:var(--font-size-xs);color:var(--text-muted-soft)">
                                    {{ $v->EV_KM ? number_format($v->EV_KM, 0, ',', "\u{202f}") . ' km' : '—' }}
                                </td>
                                @if(auth()->user()->hasPermission(15))
                                    <td class="text-end pe-2">
                                        <form method="POST"
                                              action="{{ route('event.vehicle.detach', [$event->E_CODE, $v->V_ID]) }}"
                                              class="d-inline"
                                              onsubmit="return confirm('{{ __('event.confirm_remove_vehicle') }}')">
                                            @csrf @method('DELETE')
                                            <button type="submit" class="btn btn-xs btn-light py-0 px-1 text-danger">
                                                <i class="fas fa-unlink"></i>
                                            </button>
                                        </form>
                                    </td>
                                @endif
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>
    </div>

    {{-- ── Section: Matériel ─────────────────────────────────────────────── --}}
    <div id="section-materiels" data-evt-section data-nav-icon="fas fa-box" data-nav-label="Matériel" data-nav-badge="{{ count($materiels) ?: '' }}" class="ob-widget-card mb-3">
        <div class="ob-widget-card-header">
            <div class="ob-widget-card-title">
                <i class="fas fa-box"></i> {{ __('event.section_materiels') }}
                @if(count($materiels) > 0)
                    <span class="ob-badge ob-badge-archive ms-1">{{ count($materiels) }}</span>
                @endif
            </div>
            @if(auth()->user()->hasPermission(15) && !$event->E_CLOSED && !$event->E_CANCELED)
                <button type="button" class="btn btn-sm btn-success"
                        data-bs-toggle="modal" data-bs-target="#assignMaterielModal">
                    <i class="fas fa-plus me-1"></i> {{ __('event.btn_assign_materiel') }}
                </button>
            @endif
        </div>
        <div class="ob-widget-card-body p-0">
            @if(count($materiels) === 0)
                <p class="ob-widget-empty p-3">{{ __('event.materiels_empty') }}</p>
            @else
                <table class="table table-sm table-hover mb-0 align-middle">
                    <thead style="background:var(--table-header-bg);color:var(--table-header-text)">
                        <tr>
                            <th>{{ __('event.th_designation') }}</th>
                            <th>{{ __('event.th_reference') }}</th>
                            <th class="text-center" style="width:80px">{{ __('event.th_qty') }}</th>
                            @if(auth()->user()->hasPermission(15))
                                <th style="width:50px"></th>
                            @endif
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($materiels as $m)
                            <tr>
                                <td class="fw-semibold" style="font-size:var(--font-size-sm)">
                                    {{ $m->MA_MODELE }}
                                </td>
                                <td style="font-size:var(--font-size-xs);color:var(--text-muted-soft)">
                                    {{ $m->MA_NUMERO_SERIE ?: '—' }}
                                </td>
                                <td class="text-center" style="font-size:var(--font-size-sm)">
                                    @if(auth()->user()->hasPermission(15) && !$event->E_CLOSED && !$event->E_CANCELED)
                                        <form method="POST"
                                              action="{{ route('event.equipment.qty', [$event->E_CODE, $m->MA_ID]) }}"
                                              class="d-inline">
                                            @csrf @method('PATCH')
                                            <input type="number" name="EM_NB" value="{{ $m->EM_NB }}"
                                                   min="1" max="9999"
                                                   class="form-control form-control-sm text-center d-inline-block"
                                                   style="width:60px;font-size:var(--font-size-xs);"
                                                   onchange="this.form.submit()">
                                        </form>
                                    @else
                                        {{ $m->EM_NB }}
                                    @endif
                                </td>
                                @if(auth()->user()->hasPermission(15))
                                    <td class="text-end pe-2">
                                        <form method="POST"
                                              action="{{ route('event.equipment.detach', [$event->E_CODE, $m->MA_ID]) }}"
                                              class="d-inline"
                                              onsubmit="return confirm('{{ addslashes(__('event.confirm_remove_materiel', ['name' => $m->MA_MODELE])) }}')">
                                            @csrf @method('DELETE')
                                            <button type="submit" class="btn btn-xs btn-light py-0 px-1 text-danger">
                                                <i class="fas fa-unlink"></i>
                                            </button>
                                        </form>
                                    </td>
                                @endif
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>
    </div>

    {{-- ── Section: Renforts ──────────────────────────────────────────────── --}}
    <div id="section-renforts" data-evt-section data-nav-icon="fas fa-plus-circle" data-nav-label="Renforts" data-nav-badge="{{ count($renforts) ?: '' }}" class="ob-widget-card mb-3">
        <div class="ob-widget-card-header">
            <div class="ob-widget-card-title">
                <i class="fas fa-plus-circle"></i> {{ __('event.section_renforts') }}
                @if(count($renforts) > 0)
                    <span class="ob-badge ob-badge-archive ms-1">{{ count($renforts) }}</span>
                @endif
            </div>
            @if(auth()->user()->hasPermission(15))
                <button type="button" class="btn btn-sm btn-success"
                        data-bs-toggle="modal" data-bs-target="#addRenfortModal">
                    <i class="fas fa-link me-1"></i> {{ __('event.btn_attach') }}
                </button>
            @endif
        </div>
        <div class="ob-widget-card-body p-0">
            @if(count($renforts) === 0)
                <p class="ob-widget-empty p-3">{{ __('event.renforts_empty') }}</p>
            @else
                <table class="table table-sm table-hover mb-0 align-middle">
                    <thead style="background:var(--table-header-bg);color:var(--table-header-text)">
                        <tr>
                            <th style="width:60px">{{ __('event.th_numero') }}</th>
                            <th>{{ __('event.th_activite') }}</th>
                            <th>{{ __('event.field_lieu') }}</th>
                            <th class="text-center" style="width:80px">{{ __('event.th_inscrits') }}</th>
                            @if(auth()->user()->hasPermission(15))
                                <th style="width:50px"></th>
                            @endif
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($renforts as $r)
                            <tr>
                                <td style="font-size:var(--font-size-xs);color:var(--text-muted-soft)">
                                    {{ $r->E_CODE }}
                                </td>
                                <td style="font-size:var(--font-size-sm)">
                                    <a href="{{ route('event.show', $r->E_CODE) }}"
                                       class="text-decoration-none fw-semibold">
                                        {{ $r->E_LIBELLE ?? $r->E_CODE }}
                                    </a>
                                    @if($r->E_CANCELED)
                                        <span class="ob-badge ob-badge-bloqued ms-1">{{ __('event.renfort_canceled') }}</span>
                                    @endif
                                </td>
                                <td style="font-size:var(--font-size-xs);color:var(--text-muted-soft)">
                                    {{ $r->E_LIEU ?: '—' }}
                                </td>
                                <td class="text-center" style="font-size:var(--font-size-sm)">
                                    {{ $r->participant_count ?? 0 }}
                                </td>
                                @if(auth()->user()->hasPermission(15))
                                    <td class="text-end pe-2">
                                        <form method="POST"
                                              action="{{ route('event.reinforcement.detach', [$event->E_CODE, $r->E_CODE]) }}"
                                              class="d-inline"
                                              onsubmit="return confirm('{{ __('event.confirm_detach_renfort') }}')">
                                            @csrf @method('DELETE')
                                            <button type="submit" class="btn btn-xs btn-light py-0 px-1 text-danger"
                                                    title="{{ __('event.btn_detach_title') }}">
                                                <i class="fas fa-unlink"></i>
                                            </button>
                                        </form>
                                    </td>
                                @endif
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>
    </div>

    {{-- ── Section: Demande de renfort ──────────────────────────────────── --}}
    @php
        $hasRenfort = ($renfortRequest && ($renfortRequest->NB_VEHICULES > 0 || $renfortRequest->POINT_REGROUPEMENT || $renfortRequest->DEMANDE_SPECIFIQUE))
                      || $renfortVehicleTypes->isNotEmpty()
                      || $renfortMaterials->isNotEmpty();
    @endphp
    @if($hasRenfort || auth()->user()->hasPermission(15))
    <div id="section-renfort-request" data-evt-section data-nav-icon="fas fa-ambulance" data-nav-label="Demande de renfort" class="ob-widget-card mb-3">
        <div class="ob-widget-card-header">
            <div class="ob-widget-card-title">
                <i class="fas fa-ambulance"></i> {{ __('event.section_renfort_request') }}
            </div>
            @if(auth()->user()->hasPermission(15) && !$event->E_CLOSED && !$event->E_CANCELED)
                <a href="{{ route('event.renfort-request', $event->E_CODE) }}" class="btn btn-sm btn-outline-primary noprint">
                    <i class="fas fa-edit me-1"></i> {{ __('event.btn_manage_renfort') }}
                </a>
            @endif
        </div>
        <div class="ob-widget-card-body">
            @if(!$hasRenfort)
                <p class="ob-widget-empty mb-0">{{ __('event.renfort_request_empty') }}</p>
            @else
                @if($renfortRequest && $renfortRequest->NB_VEHICULES > 0)
                    <div class="mb-1" style="font-size:var(--font-size-sm)">
                        <strong>{{ __('event.renfort_vehicles_label') }}</strong> {{ $renfortRequest->NB_VEHICULES }} {{ __('event.renfort_vehicles_total') }}
                        @foreach($renfortVehicleTypes as $vt)
                            · {{ $vt->TV_CODE }} ({{ $vt->NB_VEHICULES }})
                        @endforeach
                    </div>
                @endif
                @if($renfortMaterials->isNotEmpty())
                    <div class="mb-1" style="font-size:var(--font-size-sm)">
                        <strong>{{ __('event.renfort_material_label') }}</strong>
                        @foreach($renfortMaterials as $m)
                            <span class="badge bg-light text-dark border me-1">{{ $m->TM_CODE ?? $m->CAT_DESCRIPTION ?? $m->TYPE_MATERIEL }}</span>
                        @endforeach
                    </div>
                @endif
                @if($renfortRequest && $renfortRequest->POINT_REGROUPEMENT)
                    <div class="mb-1" style="font-size:var(--font-size-sm)">
                        <strong>{{ __('event.renfort_point_label') }}</strong> {{ $renfortRequest->POINT_REGROUPEMENT }}
                    </div>
                @endif
                @if($renfortRequest && $renfortRequest->DEMANDE_SPECIFIQUE)
                    <div style="font-size:var(--font-size-sm)">
                        <strong>{{ __('event.renfort_specific_label') }}</strong> {{ $renfortRequest->DEMANDE_SPECIFIQUE }}
                    </div>
                @endif
            @endif
        </div>
    </div>
    @endif

    {{-- ── Section: Postes requis ────────────────────────────────────────── --}}
    @if($requiredPositions->isNotEmpty() || auth()->user()->hasPermission(15))
    <div id="section-postes" data-evt-section data-nav-icon="fas fa-tasks" data-nav-label="Postes requis" data-nav-badge="{{ $requiredPositions->count() ?: '' }}" class="ob-widget-card mb-3">
        <div class="ob-widget-card-header">
            <div class="ob-widget-card-title">
                <i class="fas fa-tasks"></i> {{ __('event.section_postes') }}
                @if($requiredPositions->isNotEmpty())
                    <span class="ob-badge ob-badge-archive ms-1">{{ $requiredPositions->count() }}</span>
                @endif
            </div>
            @if(auth()->user()->hasPermission(15) && !$event->E_CLOSED && !$event->E_CANCELED)
                <button type="button" class="btn btn-sm btn-success"
                        data-bs-toggle="modal" data-bs-target="#addPosteModal">
                    <i class="fas fa-plus me-1"></i> {{ __('common.add') }}
                </button>
            @endif
        </div>
        <div class="ob-widget-card-body p-0">
            @if($requiredPositions->isEmpty())
                <p class="ob-widget-empty p-3">{{ __('event.postes_empty') }}</p>
            @else
                <table class="table table-sm table-hover mb-0 align-middle">
                    <thead style="background:var(--table-header-bg);color:var(--table-header-text)">
                        <tr>
                            <th>{{ __('event.th_poste') }}</th>
                            <th class="text-center" style="width:80px">{{ __('event.th_inscrits_short') }}</th>
                            <th class="text-center" style="width:80px">{{ __('event.th_requis') }}</th>
                            <th class="text-center" style="width:50px">{{ __('event.th_statut') }}</th>
                            @if(auth()->user()->hasPermission(15))<th style="width:50px"></th>@endif
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($requiredPositions as $rp)
                        @php
                            $enough = $rp->NB == 0 || $rp->actual >= $rp->NB;
                            $excess = $rp->NB > 0 && $rp->actual > $rp->NB;
                        @endphp
                        <tr>
                            <td style="font-size:var(--font-size-sm)">
                                {{ $rp->label }}
                            </td>
                            <td class="text-center fw-semibold">{{ $rp->actual }}</td>
                            <td class="text-center">
                                @if(auth()->user()->hasPermission(15) && !$event->E_CLOSED && !$event->E_CANCELED)
                                    <form method="POST" action="{{ route('event.required-position.update', [$event->E_CODE, $rp->PS_ID]) }}" class="d-inline">
                                        @csrf @method('PATCH')
                                        <input type="number" name="nb" value="{{ $rp->NB }}" min="0" max="9999"
                                               style="width:60px;text-align:center" class="form-control form-control-sm d-inline"
                                               onchange="this.form.submit()" title="{{ __('event.hint_zero_remove') }}">
                                    </form>
                                @else
                                    {{ $rp->NB ?: '∞' }}
                                @endif
                            </td>
                            <td class="text-center">
                                @if($rp->NB == 0)
                                    <i class="fas fa-check-circle text-success" title="{{ __('event.title_no_limit') }}"></i>
                                @elseif($excess)
                                    <i class="fas fa-check-circle text-primary" title="{{ __('event.title_exceeds') }}"></i>
                                @elseif($enough)
                                    <i class="fas fa-check-circle text-success" title="{{ __('event.title_ok') }}"></i>
                                @else
                                    <i class="fas fa-exclamation-circle text-danger" title="{{ __('event.title_insufficient') }}"></i>
                                @endif
                            </td>
                            @if(auth()->user()->hasPermission(15))
                            <td class="text-center">
                                @if(!$event->E_CLOSED && !$event->E_CANCELED)
                                <form method="POST" action="{{ route('event.required-position.destroy', [$event->E_CODE, $rp->PS_ID]) }}"
                                      onsubmit="return confirm('{{ __('event.confirm_delete_poste') }}')">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="btn btn-xs btn-light py-0 px-1 text-danger" title="{{ __('common.delete') }}">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </form>
                                @endif
                            </td>
                            @endif
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>
    </div>
    @endif

    {{-- ── Section: Options d'inscription ──────────────────────────────── --}}
    @if($eventOptions->isNotEmpty() || auth()->user()->hasPermission(15))
    <div id="section-options" data-evt-section data-nav-icon="fas fa-sliders-h" data-nav-label="Options" class="ob-widget-card mb-3">
        <div class="ob-widget-card-header">
            <div class="ob-widget-card-title">
                <i class="fas fa-sliders-h"></i> {{ __('event.section_options') }}
                @if($eventOptions->isNotEmpty())
                    <span class="ob-badge ob-badge-archive ms-1">{{ $eventOptions->count() }}</span>
                @endif
            </div>
            @if(auth()->user()->hasPermission(15) && !$event->E_CLOSED && !$event->E_CANCELED)
                <div class="d-flex gap-1">
                    <button type="button" class="btn btn-sm btn-outline-secondary"
                            data-bs-toggle="modal" data-bs-target="#addOptionGroupModal">
                        <i class="fas fa-layer-group me-1"></i> {{ __('event.btn_add_group') }}
                    </button>
                    <button type="button" class="btn btn-sm btn-success"
                            data-bs-toggle="modal" data-bs-target="#addOptionModal">
                        <i class="fas fa-plus me-1"></i> {{ __('event.btn_add_option') }}
                    </button>
                </div>
            @endif
        </div>
        <div class="ob-widget-card-body p-0">
            @if($eventOptions->isEmpty())
                <p class="ob-widget-empty p-3">{{ __('event.options_empty') }}</p>
            @else
                <table class="table table-sm table-hover mb-0 align-middle">
                    <thead style="background:var(--table-header-bg);color:var(--table-header-text)">
                        <tr>
                            <th>{{ __('event.th_nom') }}</th>
                            <th>{{ __('event.th_groupe') }}</th>
                            <th>{{ __('event.th_type') }}</th>
                            <th class="text-center" style="width:70px">{{ __('event.th_reponses') }}</th>
                            @if(auth()->user()->hasPermission(15))<th style="width:80px"></th>@endif
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($eventOptions as $opt)
                        <tr>
                            <td style="font-size:var(--font-size-sm)">
                                <strong>{{ $opt->EO_TITLE }}</strong>
                                @if($opt->EO_COMMENT)
                                    <br><span class="text-muted" style="font-size:0.8em">{{ $opt->EO_COMMENT }}</span>
                                @endif
                                @if($opt->EO_TYPE === 'dropdown' && $opt->dropdown_choices->isNotEmpty())
                                    <br>
                                    @foreach($opt->dropdown_choices->skip(1) as $dc)
                                        <span class="ob-badge ob-badge-info me-1" style="font-size:0.75em">{{ $dc->EOD_TEXTE }}</span>
                                    @endforeach
                                @endif
                            </td>
                            <td style="font-size:var(--font-size-sm)">{{ $opt->EOG_TITLE ?? '—' }}</td>
                            <td style="font-size:var(--font-size-sm)">
                                @php $typeLabels = ['checkbox'=>__('event.opt_type_checkbox'),'text'=>__('event.opt_type_text'),'textnum'=>__('event.opt_type_textnum'),'dropdown'=>__('event.opt_type_dropdown'),'date'=>__('event.opt_type_date'),'hour'=>__('event.opt_type_hour')]; @endphp
                                <span class="ob-badge ob-badge-secondary">{{ $typeLabels[$opt->EO_TYPE] ?? $opt->EO_TYPE }}</span>
                            </td>
                            <td class="text-center">
                                <span class="ob-badge ob-badge-archive">{{ $opt->response_count }}</span>
                            </td>
                            @if(auth()->user()->hasPermission(15))
                            <td class="text-center">
                                <div class="d-flex gap-1 justify-content-center">
                                    <button type="button" class="btn btn-xs btn-light"
                                            data-bs-toggle="modal"
                                            data-bs-target="#editOptionModal-{{ $opt->EO_ID }}"
                                            title="{{ __('common.edit') }}">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    @if(!$event->E_CLOSED && !$event->E_CANCELED)
                                    <form method="POST"
                                          action="{{ route('event.option.destroy', [$event->E_CODE, $opt->EO_ID]) }}"
                                          onsubmit="return confirm('{{ __('event.confirm_delete_option') }}')">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="btn btn-xs btn-light text-danger" title="{{ __('common.delete') }}">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </form>
                                    @endif
                                </div>
                            </td>
                            @endif
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>
    </div>
    @endif

    {{-- ── Section: Groupes d'options ─────────────────────────────────── --}}
    @if($optionGroups->isNotEmpty() && auth()->user()->hasPermission(15))
    <div id="section-option-groups" data-evt-section data-nav-icon="fas fa-layer-group" data-nav-label="Groupes d'options" class="ob-widget-card mb-3">
        <div class="ob-widget-card-header">
            <div class="ob-widget-card-title">
                <i class="fas fa-layer-group"></i> {{ __('event.section_option_groups') }}
                <span class="ob-badge ob-badge-archive ms-1">{{ $optionGroups->count() }}</span>
            </div>
        </div>
        <div class="ob-widget-card-body p-0">
            <table class="table table-sm table-hover mb-0 align-middle">
                <thead style="background:var(--table-header-bg);color:var(--table-header-text)">
                    <tr>
                        <th>{{ __('event.th_group_name') }}</th>
                        <th class="text-center" style="width:70px">{{ __('event.th_order') }}</th>
                        <th style="width:100px"></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($optionGroups as $grp)
                    <tr>
                        <td style="font-size:var(--font-size-sm)">{{ $grp->EOG_TITLE }}</td>
                        <td class="text-center" style="font-size:var(--font-size-sm)">{{ $grp->EOG_ORDER }}</td>
                        <td class="text-center">
                            <div class="d-flex gap-1 justify-content-center">
                                <button type="button" class="btn btn-xs btn-light"
                                        data-bs-toggle="modal"
                                        data-bs-target="#editGroupModal-{{ $grp->EOG_ID }}"
                                        title="{{ __('common.edit') }}">
                                    <i class="fas fa-edit"></i>
                                </button>
                                @if(!$event->E_CLOSED && !$event->E_CANCELED)
                                <form method="POST"
                                      action="{{ route('event.option-group.destroy', [$event->E_CODE, $grp->EOG_ID]) }}"
                                      onsubmit="return confirm('{{ __('event.confirm_delete_group') }}')">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="btn btn-xs btn-light text-danger" title="{{ __('common.delete') }}">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </form>
                                @endif
                            </div>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
    @endif

    {{-- ── Section: Main courante ──────────────────────────────────────────── --}}
    @if($eventLog->isNotEmpty() || auth()->user()->hasPermission(15))
    <div id="section-log" data-evt-section data-nav-icon="fas fa-clipboard-list" data-nav-label="Main courante" class="ob-widget-card mb-3">
        <div class="ob-widget-card-header">
            <div class="ob-widget-card-title">
                <i class="fas fa-clipboard-list"></i> {{ __('event.section_log') }}
                @if($eventLog->isNotEmpty())
                    <span class="badge bg-secondary ms-1">{{ $eventLog->count() }}</span>
                @endif
            </div>
            @if(auth()->user()->hasPermission(15))
            <button type="button" class="btn btn-sm btn-outline-secondary"
                    data-bs-toggle="modal" data-bs-target="#addLogModal">
                <i class="fas fa-plus me-1"></i> {{ __('common.add') }}
            </button>
            @endif
        </div>
        <div class="ob-widget-card-body p-0">
            @if($eventLog->isEmpty())
                <p class="text-muted px-3 py-2 mb-0" style="font-size:var(--font-size-sm)">{{ __('event.log_empty') }}</p>
            @else
            <table class="table table-sm table-hover mb-0">
                <thead>
                    <tr>
                        <th style="width:120px">{{ __('event.th_debut') }}</th>
                        <th style="width:130px">{{ __('event.th_type') }}</th>
                        <th>{{ __('event.th_titre') }}</th>
                        <th style="width:130px">{{ __('event.th_auteur') }}</th>
                        @if(auth()->user()->hasPermission(15))
                        <th style="width:60px"></th>
                        @endif
                    </tr>
                </thead>
                <tbody>
                    @foreach($eventLog as $log)
                    <tr @if($log->EL_IMPORTANT) class="table-warning" @endif>
                        <td style="font-size:var(--font-size-sm);white-space:nowrap">
                            {{ $log->EL_DEBUT ? \Carbon\Carbon::parse($log->EL_DEBUT)->format('d/m H:i') : '—' }}
                            @if($log->EL_IMPORTANT)
                                <i class="fas fa-exclamation-circle text-danger ms-1" title="{{ __('event.log_important_title') }}"></i>
                            @endif
                        </td>
                        <td style="font-size:var(--font-size-sm)">
                            {{ $log->TEL_DESCRIPTION ?? $log->TEL_CODE ?? '—' }}
                        </td>
                        <td style="font-size:var(--font-size-sm)">
                            @if($log->EL_TITLE)
                                <span class="fw-semibold">{{ $log->EL_TITLE }}</span>
                            @endif
                            @if($log->EL_COMMENTAIRE)
                                @if($log->EL_TITLE)<br>@endif
                                <span class="text-muted">{{ $log->EL_COMMENTAIRE }}</span>
                            @endif
                        </td>
                        <td style="font-size:var(--font-size-sm)">
                            {{ $log->P_PRENOM ? $log->P_PRENOM.' '.$log->P_NOM : '—' }}
                        </td>
                        @if(auth()->user()->hasPermission(15))
                        <td>
                            <button type="button" class="btn btn-xs btn-outline-secondary"
                                    onclick="openEditLogModal({{ json_encode($log) }})"
                                    title="{{ __('common.edit') }}"><i class="fas fa-edit"></i></button>
                            <form method="POST"
                                  action="{{ route('event.log.destroy', [$event->E_CODE, $log->EL_ID]) }}"
                                  class="d-inline"
                                  onsubmit="return confirm('{{ __('event.confirm_delete_log') }}')">
                                @csrf @method('DELETE')
                                <button type="submit" class="btn btn-xs btn-outline-danger" title="{{ __('common.delete') }}">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </form>
                        </td>
                        @endif
                    </tr>
                    @endforeach
                </tbody>
            </table>
            @endif
        </div>
    </div>
    @endif

        </div>  {{-- close sections column --}}
    </div>  {{-- close content sections (flex) --}}

</div>  {{-- close mx-3 mt-3 --}}

{{-- ══════════════════════════════════════════════════════════════════════════
     MODALS
════════════════════════════════════════════════════════════════════════════ --}}

{{-- Main courante modals --}}
@if(auth()->user()->hasPermission(15))

{{-- Add log entry --}}
<div class="modal fade" id="addLogModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" style="font-size:var(--font-size-base)">{{ __('event.modal_add_log_title') }}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="{{ route('event.log.store', $event->E_CODE) }}">
                @csrf
                <div class="modal-body">
                    <div class="mb-2">
                        <label class="form-label" style="font-size:var(--font-size-sm)">{{ __('event.log_type_label') }} <span class="text-danger">*</span></label>
                        <select name="TEL_CODE" class="form-select form-select-sm" required>
                            <option value="">{{ __('event.form_choose') }}</option>
                            @foreach($logTypes as $lt)
                                <option value="{{ $lt->TEL_CODE }}">{{ $lt->TEL_DESCRIPTION }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mb-2">
                        <label class="form-label" style="font-size:var(--font-size-sm)">{{ __('event.log_debut_label') }} <span class="text-danger">*</span></label>
                        <input type="datetime-local" name="EL_DEBUT" class="form-control form-control-sm" required>
                    </div>
                    <div class="row g-2 mb-2">
                        <div class="col">
                            <label class="form-label" style="font-size:var(--font-size-sm)">{{ __('event.log_fin_label') }}</label>
                            <input type="datetime-local" name="EL_FIN" class="form-control form-control-sm">
                        </div>
                        <div class="col">
                            <label class="form-label" style="font-size:var(--font-size-sm)">{{ __('event.log_sll_label') }}</label>
                            <input type="datetime-local" name="EL_SLL" class="form-control form-control-sm">
                        </div>
                    </div>
                    <div class="mb-2">
                        <label class="form-label" style="font-size:var(--font-size-sm)">{{ __('event.log_title_label') }}</label>
                        <input type="text" name="EL_TITLE" class="form-control form-control-sm" maxlength="255">
                    </div>
                    <div class="mb-2">
                        <label class="form-label" style="font-size:var(--font-size-sm)">{{ __('event.log_comment_label') }}</label>
                        <textarea name="EL_COMMENTAIRE" class="form-control form-control-sm" rows="3" maxlength="2000"></textarea>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="EL_IMPORTANT" value="1" id="addLogImportant">
                        <label class="form-check-label" for="addLogImportant" style="font-size:var(--font-size-sm)">{{ __('event.log_important_label') }}</label>
                    </div>
                </div>
                <div class="modal-footer py-2">
                    <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">{{ __('common.cancel') }}</button>
                    <button type="submit" class="btn btn-sm btn-primary">{{ __('event.btn_add_log') }}</button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- Edit log entry --}}
<div class="modal fade" id="editLogModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" style="font-size:var(--font-size-base)">{{ __('event.modal_edit_log_title') }}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" id="editLogForm">
                @csrf @method('PATCH')
                <div class="modal-body">
                    <div class="mb-2">
                        <label class="form-label" style="font-size:var(--font-size-sm)">{{ __('event.log_type_label') }} <span class="text-danger">*</span></label>
                        <select name="TEL_CODE" id="editLogTelCode" class="form-select form-select-sm" required>
                            <option value="">{{ __('event.form_choose') }}</option>
                            @foreach($logTypes as $lt)
                                <option value="{{ $lt->TEL_CODE }}">{{ $lt->TEL_DESCRIPTION }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mb-2">
                        <label class="form-label" style="font-size:var(--font-size-sm)">{{ __('event.log_debut_label') }} <span class="text-danger">*</span></label>
                        <input type="datetime-local" name="EL_DEBUT" id="editLogDebut" class="form-control form-control-sm" required>
                    </div>
                    <div class="row g-2 mb-2">
                        <div class="col">
                            <label class="form-label" style="font-size:var(--font-size-sm)">{{ __('event.log_fin_label') }}</label>
                            <input type="datetime-local" name="EL_FIN" id="editLogFin" class="form-control form-control-sm">
                        </div>
                        <div class="col">
                            <label class="form-label" style="font-size:var(--font-size-sm)">{{ __('event.log_sll_label') }}</label>
                            <input type="datetime-local" name="EL_SLL" id="editLogSll" class="form-control form-control-sm">
                        </div>
                    </div>
                    <div class="mb-2">
                        <label class="form-label" style="font-size:var(--font-size-sm)">{{ __('event.log_title_label') }}</label>
                        <input type="text" name="EL_TITLE" id="editLogTitle" class="form-control form-control-sm" maxlength="255">
                    </div>
                    <div class="mb-2">
                        <label class="form-label" style="font-size:var(--font-size-sm)">{{ __('event.log_comment_label') }}</label>
                        <textarea name="EL_COMMENTAIRE" id="editLogComment" class="form-control form-control-sm" rows="3" maxlength="2000"></textarea>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="EL_IMPORTANT" value="1" id="editLogImportant">
                        <label class="form-check-label" for="editLogImportant" style="font-size:var(--font-size-sm)">{{ __('event.log_important_label') }}</label>
                    </div>
                </div>
                <div class="modal-footer py-2">
                    <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">{{ __('common.cancel') }}</button>
                    <button type="submit" class="btn btn-sm btn-primary">{{ __('common.save') }}</button>
                </div>
            </form>
        </div>
    </div>
</div>

@endif {{-- permission 15 for log modals --}}

{{-- Assign matériel --}}
@if(auth()->user()->hasPermission(15))
<div class="modal fade" id="assignMaterielModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" style="font-size:var(--font-size-base)">{{ __('event.modal_assign_materiel_title') }}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="{{ route('event.equipment.attach', $event->E_CODE) }}">
                @csrf
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label" style="font-size:var(--font-size-sm)">
                            {{ __('event.materiel_label') }} <span class="text-danger">*</span>
                        </label>
                        <select name="MA_ID" class="form-select form-select-sm" required>
                            <option value="">{{ __('event.form_choose') }}</option>
                            @foreach($allMateriels as $m)
                                <option value="{{ $m->MA_ID }}">
                                    {{ $m->MA_MODELE }}{{ $m->MA_NUMERO_SERIE ? ' (' . $m->MA_NUMERO_SERIE . ')' : '' }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" style="font-size:var(--font-size-sm)">{{ __('event.qty_label') }}</label>
                        <input name="EM_NB" type="number" min="1" max="9999" value="1"
                               class="form-control form-control-sm" style="width:80px;">
                    </div>
                    @if($equipes->count() > 0)
                        <div>
                            <label class="form-label" style="font-size:var(--font-size-sm)">{{ __('event.team_label') }}</label>
                            <select name="EE_ID" class="form-select form-select-sm">
                                <option value="">{{ __('event.option_all_teams') }}</option>
                                @foreach($equipes as $eq)
                                    <option value="{{ $eq->EE_ID }}">{{ $eq->EE_NAME }}</option>
                                @endforeach
                            </select>
                        </div>
                    @endif
                </div>
                <div class="modal-footer py-2">
                    <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">{{ __('common.cancel') }}</button>
                    <button type="submit" class="btn btn-sm btn-primary">{{ __('event.btn_assign') }}</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endif

{{-- Assign vehicle --}}
@if(auth()->user()->hasPermission(15))
<div class="modal fade" id="assignVehiculeModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" style="font-size:var(--font-size-base)">{{ __('event.modal_assign_vehicle_title') }}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="{{ route('event.vehicle.attach', $event->E_CODE) }}">
                @csrf
                <div class="modal-body">
                    <label class="form-label" style="font-size:var(--font-size-sm)">
                        {{ __('event.vehicle_label') }} <span class="text-danger">*</span>
                    </label>
                    <select name="V_ID" class="form-select form-select-sm" required>
                        <option value="">{{ __('event.form_choose') }}</option>
                        @php
                            $assignedIds = collect($vehicules)->pluck('V_ID')->all();
                        @endphp
                        @foreach($allVehicles as $v)
                            @if(!in_array($v->V_ID, $assignedIds))
                                <option value="{{ $v->V_ID }}">
                                    {{ $v->V_IMMATRICULATION ?: $v->V_INDICATIF }}
                                </option>
                            @endif
                        @endforeach
                    </select>
                </div>
                <div class="modal-footer py-2">
                    <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">{{ __('common.cancel') }}</button>
                    <button type="submit" class="btn btn-sm btn-primary">{{ __('event.btn_assign') }}</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endif

{{-- Add participant --}}
@if(auth()->user()->hasPermission(10))
<div class="modal fade" id="addParticipantModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" style="font-size:var(--font-size-base)">{{ __('event.modal_add_participant_title') }}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="{{ route('event.participant.store', $event->E_CODE) }}">
                @csrf
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label" style="font-size:var(--font-size-sm)">
                            {{ __('event.member_label') }} <span class="text-danger">*</span>
                        </label>
                        <select name="P_ID" class="form-select form-select-sm" required>
                            <option value="">{{ __('event.form_choose') }}</option>
                            @foreach($candidates as $c)
                                <option value="{{ $c->P_ID }}">
                                    {{ strtoupper($c->P_NOM) }} {{ $c->P_PRENOM }}
                                    @feature('grades')@if($c->P_GRADE) — {{ $c->P_GRADE }} @endif @endfeature
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" style="font-size:var(--font-size-sm)">
                            {{ __('event.creneau_label') }} <span class="text-danger">*</span>
                        </label>
                        <select name="EH_ID" class="form-select form-select-sm" required>
                            @foreach($event->horaires as $h)
                                <option value="{{ $h->EH_ID }}">
                                    {{ __('event.form_partie_label') }} {{ $h->EH_ID }}
                                    — {{ \Carbon\Carbon::parse($h->EH_DATE_DEBUT)->format('d/m/Y') }}
                                    @if($h->EH_DEBUT) {{ substr($h->EH_DEBUT, 0, 5) }} @endif
                                </option>
                            @endforeach
                        </select>
                    </div>
                    @if($functions->count() > 0)
                        <div class="mb-3">
                            <label class="form-label" style="font-size:var(--font-size-sm)">{{ __('event.function_label') }}</label>
                            <select name="TP_ID" class="form-select form-select-sm">
                                <option value="">{{ __('event.option_no_team') }}</option>
                                @foreach($functions as $f)
                                    <option value="{{ $f->TP_ID }}">{{ $f->TP_LIBELLE }}</option>
                                @endforeach
                            </select>
                        </div>
                    @endif
                    @if($equipes->count() > 0)
                        <div class="mb-3">
                            <label class="form-label" style="font-size:var(--font-size-sm)">{{ __('event.team_label') }}</label>
                            <select name="EE_ID" class="form-select form-select-sm">
                                <option value="">{{ __('event.option_no_team') }}</option>
                                @foreach($equipes as $eq)
                                    <option value="{{ $eq->EE_ID }}">{{ $eq->EE_NAME }}</option>
                                @endforeach
                            </select>
                        </div>
                    @endif
                    <div>
                        <label class="form-label" style="font-size:var(--font-size-sm)">{{ __('event.comment_label') }}</label>
                        <input name="EP_COMMENT" type="text" class="form-control form-control-sm" maxlength="150">
                    </div>
                </div>
                <div class="modal-footer py-2">
                    <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">{{ __('common.cancel') }}</button>
                    <button type="submit" class="btn btn-sm btn-primary">{{ __('event.btn_enroll_submit') }}</button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- Edit participant --}}
<div class="modal fade" id="editParticipantModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" style="font-size:var(--font-size-base)">{{ __('event.modal_edit_participant_title') }}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="editParticipantForm" method="POST">
                @csrf @method('PATCH')
                <div class="modal-body">
                    @if($functions->count() > 0)
                        <div class="mb-3">
                            <label class="form-label" style="font-size:var(--font-size-sm)">{{ __('event.function_label') }}</label>
                            <select id="editTpId" name="TP_ID" class="form-select form-select-sm">
                                <option value="">{{ __('event.option_no_team') }}</option>
                                @foreach($functions as $f)
                                    <option value="{{ $f->TP_ID }}">{{ $f->TP_LIBELLE }}</option>
                                @endforeach
                            </select>
                        </div>
                    @endif
                    @if($equipes->count() > 0)
                        <div class="mb-3">
                            <label class="form-label" style="font-size:var(--font-size-sm)">{{ __('event.team_label') }}</label>
                            <select id="editEeId" name="EE_ID" class="form-select form-select-sm">
                                <option value="">{{ __('event.option_no_team') }}</option>
                                @foreach($equipes as $eq)
                                    <option value="{{ $eq->EE_ID }}">{{ $eq->EE_NAME }}</option>
                                @endforeach
                            </select>
                        </div>
                    @endif
                    <div>
                        <label class="form-label" style="font-size:var(--font-size-sm)">{{ __('event.comment_label') }}</label>
                        <input id="editComment" name="EP_COMMENT" type="text"
                               class="form-control form-control-sm" maxlength="150">
                    </div>
                </div>
                <div class="modal-footer py-2">
                    <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">{{ __('common.cancel') }}</button>
                    <button type="submit" class="btn btn-sm btn-primary">{{ __('common.save') }}</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endif

{{-- Add équipe --}}
@if(auth()->user()->hasPermission(15))
<div class="modal fade" id="addEquipeModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" style="font-size:var(--font-size-base)">{{ __('event.modal_add_equipe_title') }}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="{{ route('event.team.store', $event->E_CODE) }}">
                @csrf
                <div class="modal-body">
                    <div class="mb-2">
                        <label class="form-label" style="font-size:var(--font-size-sm)">{{ __('event.equipe_nom_label') }} <span class="text-danger">*</span></label>
                        <input name="EE_NAME" type="text" class="form-control form-control-sm" maxlength="30" required>
                    </div>
                    <div class="mb-2">
                        <label class="form-label" style="font-size:var(--font-size-sm)">{{ __('event.equipe_order_label') }}</label>
                        <input name="EE_ORDER" type="number" class="form-control form-control-sm" min="1" max="50" value="1">
                    </div>
                    <div class="mb-2">
                        <label class="form-label" style="font-size:var(--font-size-sm)">{{ __('event.equipe_radio_label') }}</label>
                        <input name="EE_ID_RADIO" type="text" class="form-control form-control-sm" maxlength="12">
                    </div>
                    <div>
                        <label class="form-label" style="font-size:var(--font-size-sm)">{{ __('event.equipe_desc_label') }}</label>
                        <textarea name="EE_DESCRIPTION" class="form-control form-control-sm"
                                  rows="2" maxlength="300"></textarea>
                    </div>
                </div>
                <div class="modal-footer py-2">
                    <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">{{ __('common.cancel') }}</button>
                    <button type="submit" class="btn btn-sm btn-primary">{{ __('common.create') }}</button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- Edit équipe --}}
<div class="modal fade" id="editEquipeModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" style="font-size:var(--font-size-base)">{{ __('event.modal_edit_equipe_title') }}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="editEquipeForm" method="POST">
                @csrf @method('PUT')
                <div class="modal-body">
                    <div class="mb-2">
                        <label class="form-label" style="font-size:var(--font-size-sm)">{{ __('event.equipe_nom_label') }} <span class="text-danger">*</span></label>
                        <input id="editEeName" name="EE_NAME" type="text" class="form-control form-control-sm" maxlength="30" required>
                    </div>
                    <div class="mb-2">
                        <label class="form-label" style="font-size:var(--font-size-sm)">{{ __('event.equipe_order_label') }}</label>
                        <input id="editEeOrder" name="EE_ORDER" type="number" class="form-control form-control-sm" min="1" max="50">
                    </div>
                    <div class="mb-2">
                        <label class="form-label" style="font-size:var(--font-size-sm)">{{ __('event.equipe_radio_label') }}</label>
                        <input id="editEeRadio" name="EE_ID_RADIO" type="text" class="form-control form-control-sm" maxlength="12">
                    </div>
                    <div>
                        <label class="form-label" style="font-size:var(--font-size-sm)">{{ __('event.equipe_desc_label') }}</label>
                        <textarea id="editEeDesc" name="EE_DESCRIPTION" class="form-control form-control-sm"
                                  rows="2" maxlength="300"></textarea>
                    </div>
                </div>
                <div class="modal-footer py-2">
                    <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">{{ __('common.cancel') }}</button>
                    <button type="submit" class="btn btn-sm btn-primary">{{ __('common.save') }}</button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- Add renfort --}}
<div class="modal fade" id="addRenfortModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" style="font-size:var(--font-size-base)">{{ __('event.modal_add_renfort_title') }}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="{{ route('event.reinforcement.attach', $event->E_CODE) }}">
                @csrf
                <div class="modal-body">
                    <label class="form-label" style="font-size:var(--font-size-sm)">
                        {{ __('event.renfort_number_label') }} <span class="text-danger">*</span>
                    </label>
                    @php $hasCandidates = $renfortCandidates['renforts']->isNotEmpty() || $renfortCandidates['others']->isNotEmpty(); @endphp
                    <select name="renfort" class="form-select form-select-sm" required @disabled(! $hasCandidates)>
                        <option value="">{{ __('event.renfort_select_placeholder') }}</option>
                        @if($renfortCandidates['renforts']->isNotEmpty())
                            <optgroup label="{{ __('event.renfort_select_group_renforts') }}">
                                @foreach($renfortCandidates['renforts'] as $c)
                                    <option value="{{ $c->E_CODE }}">{{ $c->label }}</option>
                                @endforeach
                            </optgroup>
                        @endif
                        @if($renfortCandidates['others']->isNotEmpty())
                            <optgroup label="{{ __('event.renfort_select_group_others') }}">
                                @foreach($renfortCandidates['others'] as $c)
                                    <option value="{{ $c->E_CODE }}">{{ $c->label }}</option>
                                @endforeach
                            </optgroup>
                        @endif
                    </select>
                    <div class="form-text mt-1">
                        {{ $hasCandidates ? __('event.renfort_number_help') : __('event.renfort_select_empty') }}
                    </div>
                </div>
                <div class="modal-footer py-2">
                    <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">{{ __('common.cancel') }}</button>
                    <button type="submit" class="btn btn-sm btn-primary" @disabled(! $hasCandidates)>{{ __('event.btn_attach_renfort') }}</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endif

{{-- ── Duplicate modal ─────────────────────────────────────────────────────── --}}
@if(auth()->user()->hasPermission(15))
<div class="modal fade" id="duplicateModal" tabindex="-1" aria-labelledby="duplicateModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="POST" action="{{ route('event.duplicate', $event->E_CODE) }}">
                @csrf
                <div class="modal-header py-2">
                    <h6 class="modal-title" id="duplicateModalLabel">
                        <i class="fas fa-copy me-1"></i> {{ __('event.modal_duplicate_title') }}
                    </h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p class="text-muted mb-3" style="font-size:var(--font-size-sm)">
                        {!! __('event.duplicate_intro', ['name' => '<strong>' . e($event->E_LIBELLE ?? $event->E_CODE) . '</strong>']) !!}
                    </p>
                    <div class="mb-3">
                        <label for="dup_new_date" class="form-label">
                            {{ __('event.duplicate_date_label') }} <span class="text-danger">*</span>
                        </label>
                        <input id="dup_new_date" type="date" name="new_date"
                               class="form-control form-control-sm"
                               value="{{ now()->addWeek()->toDateString() }}"
                               required>
                        <div class="form-text">{{ __('event.duplicate_date_help') }}</div>
                    </div>
                    <div class="mb-2 form-check">
                        <input class="form-check-input" type="checkbox" name="copy_participants"
                               id="dup_participants" value="1">
                        <label class="form-check-label" for="dup_participants"
                               style="font-size:var(--font-size-sm)">
                            {{ __('event.duplicate_copy_people') }}
                        </label>
                    </div>
                    <div class="mb-0 form-check">
                        <input class="form-check-input" type="checkbox" name="copy_vehicles"
                               id="dup_vehicles" value="1">
                        <label class="form-check-label" for="dup_vehicles"
                               style="font-size:var(--font-size-sm)">
                            {{ __('event.duplicate_copy_vehicles') }}
                        </label>
                    </div>
                </div>
                <div class="modal-footer py-2">
                    <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">{{ __('common.cancel') }}</button>
                    <button type="submit" class="btn btn-sm btn-primary">
                        <i class="fas fa-copy me-1"></i> {{ __('event.btn_duplicate') }}
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endif

{{-- Add required position modal --}}
@if(auth()->user()->hasPermission(15))
<div class="modal fade" id="addPosteModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" style="font-size:var(--font-size-base)">{{ __('event.modal_add_poste_title') }}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="{{ route('event.required-position.store', $event->E_CODE) }}">
                @csrf
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label" style="font-size:var(--font-size-sm)">
                            {{ __('event.poste_label') }} <span class="text-danger">*</span>
                        </label>
                        <select name="ps_id" class="form-select form-select-sm" required>
                            <option value="">{{ __('event.form_choose') }}</option>
                            <option value="0" @if($requiredPositions->where('PS_ID', 0)->isNotEmpty()) disabled @endif>
                                {{ __('event.poste_global_option') }}
                            </option>
                            @foreach($availablePositions as $pos)
                                <option value="{{ $pos->PS_ID }}">{{ $pos->TYPE }} – {{ $pos->DESCRIPTION }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mb-0">
                        <label class="form-label" style="font-size:var(--font-size-sm)">
                            {{ __('event.nb_requis_label') }} <span class="text-danger">*</span>
                        </label>
                        <input type="number" name="nb" class="form-control form-control-sm"
                               min="1" max="9999" value="1" required>
                    </div>
                </div>
                <div class="modal-footer py-2">
                    <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">{{ __('common.cancel') }}</button>
                    <button type="submit" class="btn btn-sm btn-success">
                        <i class="fas fa-plus me-1"></i> {{ __('event.btn_add_poste') }}
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endif

{{-- ══ Event options modals ══════════════════════════════════════════════════ --}}
@if(auth()->user()->hasPermission(15))

{{-- Add option group --}}
<div class="modal fade" id="addOptionGroupModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" style="font-size:var(--font-size-base)">{{ __('event.modal_add_group_title') }}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="{{ route('event.option-group.store', $event->E_CODE) }}">
                @csrf
                <div class="modal-body">
                    <div class="mb-2">
                        <label class="form-label" style="font-size:var(--font-size-sm)">{{ __('event.group_nom_label') }} <span class="text-danger">*</span></label>
                        <input name="EOG_TITLE" type="text" class="form-control form-control-sm" maxlength="80" required>
                    </div>
                    <div>
                        <label class="form-label" style="font-size:var(--font-size-sm)">{{ __('event.group_order_label') }}</label>
                        <input name="EOG_ORDER" type="number" class="form-control form-control-sm" min="1" max="99" value="1">
                    </div>
                </div>
                <div class="modal-footer py-2">
                    <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">{{ __('common.cancel') }}</button>
                    <button type="submit" class="btn btn-sm btn-primary">{{ __('common.create') }}</button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- Edit option group (one modal per group) --}}
@foreach($optionGroups as $grp)
<div class="modal fade" id="editGroupModal-{{ $grp->EOG_ID }}" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" style="font-size:var(--font-size-base)">{{ __('event.modal_edit_group_title') }}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="{{ route('event.option-group.update', [$event->E_CODE, $grp->EOG_ID]) }}">
                @csrf @method('PATCH')
                <div class="modal-body">
                    <div class="mb-2">
                        <label class="form-label" style="font-size:var(--font-size-sm)">{{ __('event.group_nom_label') }} <span class="text-danger">*</span></label>
                        <input name="EOG_TITLE" type="text" class="form-control form-control-sm" maxlength="80" value="{{ $grp->EOG_TITLE }}" required>
                    </div>
                    <div>
                        <label class="form-label" style="font-size:var(--font-size-sm)">{{ __('event.group_order_label') }}</label>
                        <input name="EOG_ORDER" type="number" class="form-control form-control-sm" min="1" max="99" value="{{ $grp->EOG_ORDER }}">
                    </div>
                </div>
                <div class="modal-footer py-2">
                    <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">{{ __('common.cancel') }}</button>
                    <button type="submit" class="btn btn-sm btn-primary">{{ __('common.save') }}</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endforeach

{{-- Add option --}}
<div class="modal fade" id="addOptionModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" style="font-size:var(--font-size-base)">{{ __('event.modal_add_option_title') }}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="{{ route('event.option.store', $event->E_CODE) }}">
                @csrf
                <div class="modal-body">
                    <div class="mb-2">
                        <label class="form-label" style="font-size:var(--font-size-sm)">{{ __('event.option_nom_label') }} <span class="text-danger">*</span></label>
                        <input name="EO_TITLE" type="text" class="form-control form-control-sm" maxlength="80" required>
                    </div>
                    <div class="mb-2">
                        <label class="form-label" style="font-size:var(--font-size-sm)">{{ __('event.option_type_label') }} <span class="text-danger">*</span></label>
                        <select name="EO_TYPE" class="form-select form-select-sm" required>
                            <option value="checkbox">{{ __('event.opt_type_checkbox_long') }}</option>
                            <option value="text">{{ __('event.opt_type_text_long') }}</option>
                            <option value="textnum">{{ __('event.opt_type_textnum_long') }}</option>
                            <option value="dropdown">{{ __('event.opt_type_dropdown_long') }}</option>
                            <option value="date">{{ __('event.opt_type_date_long') }}</option>
                            <option value="hour">{{ __('event.opt_type_hour_long') }}</option>
                        </select>
                    </div>
                    @if($optionGroups->isNotEmpty())
                    <div class="mb-2">
                        <label class="form-label" style="font-size:var(--font-size-sm)">{{ __('event.option_group_label') }}</label>
                        <select name="EOG_ID" class="form-select form-select-sm">
                            <option value="">{{ __('event.option_no_group') }}</option>
                            @foreach($optionGroups as $grp)
                                <option value="{{ $grp->EOG_ID }}">{{ $grp->EOG_TITLE }}</option>
                            @endforeach
                        </select>
                    </div>
                    @endif
                    <div class="mb-2">
                        <label class="form-label" style="font-size:var(--font-size-sm)">{{ __('event.option_order_label') }}</label>
                        <input name="EO_ORDER" type="number" class="form-control form-control-sm" min="1" max="99" value="1">
                    </div>
                    <div>
                        <label class="form-label" style="font-size:var(--font-size-sm)">{{ __('event.option_desc_label') }}</label>
                        <textarea name="EO_COMMENT" class="form-control form-control-sm" rows="2" maxlength="255"></textarea>
                    </div>
                </div>
                <div class="modal-footer py-2">
                    <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">{{ __('common.cancel') }}</button>
                    <button type="submit" class="btn btn-sm btn-success"><i class="fas fa-plus me-1"></i> {{ __('common.create') }}</button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- Edit option (one modal per option) --}}
@foreach($eventOptions as $opt)
<div class="modal fade" id="editOptionModal-{{ $opt->EO_ID }}" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" style="font-size:var(--font-size-base)">{{ __('event.modal_edit_option_title') }}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="{{ route('event.option.update', [$event->E_CODE, $opt->EO_ID]) }}">
                @csrf @method('PATCH')
                <div class="modal-body">
                    <div class="mb-2">
                        <label class="form-label" style="font-size:var(--font-size-sm)">{{ __('event.option_nom_label') }} <span class="text-danger">*</span></label>
                        <input name="EO_TITLE" type="text" class="form-control form-control-sm" maxlength="80" value="{{ $opt->EO_TITLE }}" required>
                    </div>
                    <div class="mb-2">
                        <label class="form-label" style="font-size:var(--font-size-sm)">{{ __('event.option_type_label') }} <span class="text-danger">*</span></label>
                        <select name="EO_TYPE" class="form-select form-select-sm" required>
                            @foreach(['checkbox'=>__('event.opt_type_checkbox_long'),'text'=>__('event.opt_type_text_long'),'textnum'=>__('event.opt_type_textnum_long'),'dropdown'=>__('event.opt_type_dropdown_long'),'date'=>__('event.opt_type_date_long'),'hour'=>__('event.opt_type_hour_long')] as $v => $lbl)
                                <option value="{{ $v }}" @selected($opt->EO_TYPE === $v)>{{ $lbl }}</option>
                            @endforeach
                        </select>
                    </div>
                    @if($optionGroups->isNotEmpty())
                    <div class="mb-2">
                        <label class="form-label" style="font-size:var(--font-size-sm)">{{ __('event.option_group_label') }}</label>
                        <select name="EOG_ID" class="form-select form-select-sm">
                            <option value="">{{ __('event.option_no_group') }}</option>
                            @foreach($optionGroups as $grp)
                                <option value="{{ $grp->EOG_ID }}" @selected($opt->EOG_ID == $grp->EOG_ID)>{{ $grp->EOG_TITLE }}</option>
                            @endforeach
                        </select>
                    </div>
                    @endif
                    <div class="mb-2">
                        <label class="form-label" style="font-size:var(--font-size-sm)">{{ __('event.option_order_label_short') }}</label>
                        <input name="EO_ORDER" type="number" class="form-control form-control-sm" min="1" max="99" value="{{ $opt->EO_ORDER }}">
                    </div>
                    <div class="mb-3">
                        <label class="form-label" style="font-size:var(--font-size-sm)">{{ __('event.option_desc_label') }}</label>
                        <textarea name="EO_COMMENT" class="form-control form-control-sm" rows="2" maxlength="255">{{ $opt->EO_COMMENT }}</textarea>
                    </div>
                    {{-- Dropdown choices management --}}
                    @if($opt->EO_TYPE === 'dropdown')
                    <hr>
                    <p class="mb-2" style="font-size:var(--font-size-sm)"><strong>{{ __('event.dropdown_choices_heading') }}</strong></p>
                    @foreach($opt->dropdown_choices->skip(1) as $dc)
                    <div class="d-flex gap-1 align-items-center mb-1">
                        <span class="flex-grow-1" style="font-size:var(--font-size-sm)">{{ $dc->EOD_TEXTE }}</span>
                        <form method="POST"
                              action="{{ route('event.option.choice.destroy', [$event->E_CODE, $opt->EO_ID, $dc->EOD_ID]) }}"
                              onsubmit="return confirm('{{ __('event.confirm_delete_choice') }}')">
                            @csrf @method('DELETE')
                            <button type="submit" class="btn btn-xs btn-light text-danger" title="{{ __('common.delete') }}">
                                <i class="fas fa-times"></i>
                            </button>
                        </form>
                    </div>
                    @endforeach
                    <form method="POST"
                          action="{{ route('event.option.choice.store', [$event->E_CODE, $opt->EO_ID]) }}"
                          class="d-flex gap-1 mt-2">
                        @csrf
                        <input name="EOD_TEXTE" type="text" class="form-control form-control-sm" maxlength="80" placeholder="{{ __('event.choice_placeholder') }}" required>
                        <input name="EOD_ORDER" type="number" class="form-control form-control-sm" min="1" max="99" value="1" style="width:60px" title="{{ __('event.th_order') }}">
                        <button type="submit" class="btn btn-sm btn-success"><i class="fas fa-plus"></i></button>
                    </form>
                    @endif
                </div>
                <div class="modal-footer py-2">
                    <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">{{ __('common.cancel') }}</button>
                    <button type="submit" class="btn btn-sm btn-primary">{{ __('common.save') }}</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endforeach

@endif {{-- permission 15 --}}

{{-- Participant choices modals (perm 15 or self — shown for enrolled participants with options) --}}
@if($eventOptions->isNotEmpty())
@foreach($participants as $p)
@php $isSelf = auth()->id() === (int)$p->P_ID; @endphp
@if(auth()->user()->hasPermission(15) || $isSelf)
<div class="modal fade" id="choicesModal-{{ $p->P_ID }}" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" style="font-size:var(--font-size-base)">
                    {{ __('event.choices_modal_heading') }} — {{ $p->P_PRENOM }} {{ $p->P_NOM }}
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="{{ route('event.participant-choices.save', [$event->E_CODE, $p->P_ID]) }}">
                @csrf
                <div class="modal-body">
                    @php
                        $savedChoices = \Illuminate\Support\Facades\DB::table('evenement_option_choix')
                            ->where('E_CODE', $event->E_CODE)->where('P_ID', $p->P_ID)
                            ->pluck('EOC_VALUE', 'EO_ID');
                    @endphp
                    @foreach($eventOptions as $opt)
                    <div class="mb-3">
                        <label class="form-label" style="font-size:var(--font-size-sm)">
                            <strong>{{ $opt->EO_TITLE }}</strong>
                            @if($opt->EOG_TITLE) <span class="text-muted">({{ $opt->EOG_TITLE }})</span> @endif
                        </label>
                        @if($opt->EO_COMMENT)
                            <div class="form-text mb-1">{{ $opt->EO_COMMENT }}</div>
                        @endif
                        @php $saved = $savedChoices[$opt->EO_ID] ?? null; @endphp
                        @if($opt->EO_TYPE === 'checkbox')
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="O{{ $opt->EO_ID }}" value="1" id="opt-{{ $p->P_ID }}-{{ $opt->EO_ID }}" @checked($saved == '1')>
                                <label class="form-check-label" for="opt-{{ $p->P_ID }}-{{ $opt->EO_ID }}">{{ __('common.yes') }}</label>
                            </div>
                        @elseif($opt->EO_TYPE === 'text')
                            <input type="text" name="O{{ $opt->EO_ID }}" class="form-control form-control-sm" maxlength="255" value="{{ $saved }}">
                        @elseif($opt->EO_TYPE === 'textnum')
                            <input type="number" name="O{{ $opt->EO_ID }}" class="form-control form-control-sm" style="width:100px" value="{{ $saved }}">
                        @elseif($opt->EO_TYPE === 'date')
                            <input type="text" name="O{{ $opt->EO_ID }}" class="form-control form-control-sm" style="width:140px" placeholder="JJ-MM-AAAA" value="{{ $saved }}"> {{-- i18n-ignore: date format pattern, not prose --}}
                        @elseif($opt->EO_TYPE === 'hour')
                            <input type="text" name="O{{ $opt->EO_ID }}" class="form-control form-control-sm" style="width:90px" placeholder="HH:mm" value="{{ $saved }}"> {{-- i18n-ignore: time format pattern, not prose --}}
                        @elseif($opt->EO_TYPE === 'dropdown')
                            <select name="O{{ $opt->EO_ID }}" class="form-select form-select-sm" style="max-width:250px">
                                @foreach($opt->dropdown_choices as $dc)
                                    <option value="{{ $dc->EOD_ID }}" @selected((string)$saved === (string)$dc->EOD_ID)>{{ $dc->EOD_TEXTE }}</option>
                                @endforeach
                            </select>
                        @endif
                    </div>
                    @endforeach
                </div>
                <div class="modal-footer py-2">
                    <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">{{ __('common.cancel') }}</button>
                    <button type="submit" class="btn btn-sm btn-success">{{ __('common.save') }}</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endif
@endforeach
@endif

@endsection

@push('scripts')
<script>window.EVT_SHOW_CONFIG = { participantsUrl: '{{ url('/evenements/' . $event->E_CODE . '/participants') }}', equipesUrl: '{{ url('/evenements/' . $event->E_CODE . '/equipes') }}' };</script>
@vite('resources/js/ob-event-show.js')
@if(auth()->user()->hasPermission(15))
<script>
function openEditLogModal(log) {
    const fmt = (dt) => dt ? dt.replace(' ', 'T').substring(0, 16) : '';
    document.getElementById('editLogTelCode').value  = log.TEL_CODE   ?? '';
    document.getElementById('editLogDebut').value    = fmt(log.EL_DEBUT);
    document.getElementById('editLogFin').value      = fmt(log.EL_FIN);
    document.getElementById('editLogSll').value      = fmt(log.EL_SLL);
    document.getElementById('editLogTitle').value    = log.EL_TITLE   ?? '';
    document.getElementById('editLogComment').value  = log.EL_COMMENTAIRE ?? '';
    document.getElementById('editLogImportant').checked = !!parseInt(log.EL_IMPORTANT);
    document.getElementById('editLogForm').action =
        '{{ url('/events') }}/' + {{ json_encode($event->E_CODE) }} + '/log/' + log.EL_ID;
    new bootstrap.Modal(document.getElementById('editLogModal')).show();
}
</script>
@endif
@endpush
