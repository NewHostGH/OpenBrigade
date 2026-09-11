@extends('layout.app')

@section('title', ($event->E_LIBELLE ?? $event->E_CODE) . ' — Demande de renfort — ' . config('app.name'))

@section('content')

<x-ob-breadcrumb :items="[
    ['label' => __('event.title'), 'url' => route('event.index')],
    ['label' => $event->E_LIBELLE ?? $event->E_CODE, 'url' => route('event.show', $event->E_CODE)],
    ['label' => __('event.renfort_req_heading')],
]"/>

<div class="mx-3 mt-3">
    <form method="POST" action="{{ route('event.renfort-request.update', $event->E_CODE) }}">
        @csrf

        <div class="ob-widget-card mb-3">
            <div class="ob-widget-card-header">
                <div class="ob-widget-card-title">
                    <i class="fas fa-ambulance me-1"></i>
                    {{ __('event.renfort_req_heading') }} — {{ $event->E_LIBELLE ?? $event->E_CODE }}
                </div>
                <a href="{{ route('event.show', $event->E_CODE) }}" class="btn btn-sm btn-light">
                    <i class="fas fa-arrow-left me-1"></i> {{ __('event.btn_back') }}
                </a>
            </div>

            {{-- Véhicules ──────────────────────────────────────────── --}}
            <div class="ob-widget-card-body border-bottom">
                <h6 class="fw-semibold mb-3" style="font-size:var(--font-size-sm)">
                    <i class="fas fa-truck me-1 text-muted"></i> {{ __('event.renfort_req_vehicles_heading') }}
                </h6>

                <div class="row g-3 mb-3">
                    <div class="col-sm-3">
                        <label class="form-label" style="font-size:var(--font-size-sm)">{{ __('event.renfort_req_nb_vehicles_label') }}</label>
                        <input type="number" name="nb_vehicules" class="form-control form-control-sm"
                               min="0" max="9999"
                               value="{{ old('nb_vehicules', $global?->NB_VEHICULES ?? 0) }}">
                    </div>
                    <div class="col-sm-9">
                        <label class="form-label" style="font-size:var(--font-size-sm)">{{ __('event.renfort_req_point_label') }}</label>
                        <input type="text" name="point_regroupement" class="form-control form-control-sm"
                               maxlength="250"
                               value="{{ old('point_regroupement', $global?->POINT_REGROUPEMENT ?? '') }}">
                    </div>
                </div>

                @if($vehicleTypes->isNotEmpty())
                <div class="mb-3">
                    <label class="form-label" style="font-size:var(--font-size-sm)">{{ __('event.renfort_req_detail_label') }}</label>
                    <div class="row g-2">
                        @php $prevUsage = ''; @endphp
                        @foreach($vehicleTypes as $vt)
                        @if($prevUsage !== $vt->TV_USAGE)
                            @if($loop->index > 0)</div>@endif
                            <div class="col-12 mt-1 mb-0">
                                <small class="text-muted text-uppercase" style="font-size:var(--font-size-xs);letter-spacing:.05em">{{ $vt->TV_USAGE }}</small>
                            </div>
                            @php $prevUsage = $vt->TV_USAGE; @endphp
                        @endif
                        <div class="col-6 col-sm-4 col-md-3">
                            <label class="form-label mb-0" style="font-size:var(--font-size-xs)">{{ $vt->TV_CODE }} — {{ $vt->TV_LIBELLE }}</label>
                            <input type="number" name="vehicle_types[{{ $vt->TV_CODE }}]"
                                   class="form-control form-control-sm"
                                   min="0" max="999"
                                   value="{{ old('vehicle_types.'.$vt->TV_CODE, $assignedVehicleCodes[$vt->TV_CODE] ?? 0) }}">
                        </div>
                        @endforeach
                    </div>
                </div>
                @endif
            </div>

            {{-- Matériel ──────────────────────────────────────────── --}}
            @if($materialCategories->isNotEmpty())
            <div class="ob-widget-card-body border-bottom">
                <h6 class="fw-semibold mb-3" style="font-size:var(--font-size-sm)">
                    <i class="fas fa-box me-1 text-muted"></i> {{ __('event.renfort_req_material_heading') }}
                </h6>
                <div class="row g-2">
                    @foreach($materialCategories as $cat)
                    <div class="col-6 col-sm-4 col-md-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox"
                                   name="categories[]"
                                   value="{{ $cat->TM_USAGE }}"
                                   id="cat_{{ $cat->TM_USAGE }}"
                                   @checked(in_array($cat->TM_USAGE, old('categories', $assignedCategories->toArray())))>
                            <label class="form-check-label" for="cat_{{ $cat->TM_USAGE }}"
                                   style="font-size:var(--font-size-sm)">
                                @if($cat->PICTURE)
                                    <i class="fas fa-{{ $cat->PICTURE }} me-1 text-muted"></i>
                                @endif
                                {{ $cat->TM_USAGE }}
                            </label>
                        </div>
                    </div>
                    @endforeach
                </div>
            </div>
            @endif

            {{-- Demande spécifique ────────────────────────────────── --}}
            <div class="ob-widget-card-body">
                <label class="form-label" style="font-size:var(--font-size-sm)">{{ __('event.renfort_req_specific_label') }}</label>
                <textarea name="demande_specifique" class="form-control form-control-sm"
                          rows="3" maxlength="600"
                          >{{ old('demande_specifique', $global?->DEMANDE_SPECIFIQUE ?? '') }}</textarea>
            </div>

            @if(auth()->user()->hasPermission(15))
            <div class="ob-widget-card-footer text-end">
                <button type="submit" class="btn btn-sm btn-success">
                    <i class="fas fa-save me-1"></i> {{ __('event.renfort_req_btn_save') }}
                </button>
            </div>
            @endif
        </div>

    </form>

    {{-- Transmission to other sections ─────────────────────────────── --}}
    {{-- success / error are rendered by the layout; only warning is ours --}}
    @if(session('warning'))
        <div class="alert alert-warning py-2 mb-3">{{ session('warning') }}</div>
    @endif

    <div class="ob-widget-card mb-3">
        <div class="ob-widget-card-header">
            <div class="ob-widget-card-title">
                <i class="fas fa-paper-plane me-1"></i> {{ __('event.renfort_tx_heading') }}
            </div>
        </div>

        @if(auth()->user()->hasPermission(15))
            <form method="POST" action="{{ route('event.renfort-request.transmit', $event->E_CODE) }}">
                @csrf
                <div class="ob-widget-card-body border-bottom">
                    <p class="text-muted mb-2" style="font-size:var(--font-size-sm)">{{ __('event.renfort_tx_hint') }}</p>

                    @if($targets->isEmpty())
                        <p class="ob-widget-empty mb-0">{{ __('event.renfort_tx_no_targets') }}</p>
                    @else
                        <div class="row g-2 mb-3">
                            @foreach($targets as $t)
                                <div class="col-md-6">
                                    <label for="tx_{{ $t->S_ID }}"
                                           class="d-flex align-items-center gap-3 border rounded px-3 py-2 h-100 mb-0 {{ $t->recipients->isEmpty() ? 'opacity-50' : '' }}"
                                           style="cursor:{{ $t->recipients->isEmpty() ? 'not-allowed' : 'pointer' }}; font-size:var(--font-size-sm)">
                                        <input class="form-check-input flex-shrink-0 m-0" type="checkbox" name="sections[]"
                                               value="{{ $t->S_ID }}" id="tx_{{ $t->S_ID }}"
                                               @checked(in_array($t->S_ID, old('sections', [])))
                                               @disabled($t->recipients->isEmpty())>
                                        <span class="d-block">
                                            <span class="fw-semibold">{{ $t->S_CODE }}</span>
                                            @if($t->S_DESCRIPTION)<span class="text-muted"> — {{ $t->S_DESCRIPTION }}</span>@endif
                                            <span class="d-block text-muted" style="font-size:var(--font-size-xs)">
                                                @if($t->recipients->isEmpty())
                                                    <i class="fas fa-exclamation-triangle me-1 text-warning"></i>{{ __('event.renfort_tx_no_recipient') }}
                                                @else
                                                    <i class="fas fa-user me-1"></i>
                                                    {{ $t->recipients->map(fn ($r) => strtoupper($r->P_NOM).' '.$r->P_PRENOM)->implode(', ') }}
                                                @endif
                                            </span>
                                        </span>
                                    </label>
                                </div>
                            @endforeach
                        </div>

                        <label class="form-label" style="font-size:var(--font-size-sm)">{{ __('event.renfort_tx_note_label') }}</label>
                        <textarea name="note" class="form-control form-control-sm" rows="2" maxlength="600">{{ old('note') }}</textarea>
                    @endif
                </div>
                @if($targets->isNotEmpty())
                    <div class="ob-widget-card-footer text-end">
                        <button type="submit" class="btn btn-sm btn-primary">
                            <i class="fas fa-paper-plane me-1"></i> {{ __('event.renfort_tx_btn') }}
                        </button>
                    </div>
                @endif
            </form>
        @endif

        {{-- History --}}
        <div class="ob-widget-card-body">
            <h6 class="fw-semibold mb-2" style="font-size:var(--font-size-sm)">
                <i class="fas fa-history me-1 text-muted"></i> {{ __('event.renfort_tx_history') }}
            </h6>
            @forelse($transmissions as $h)
                <div class="d-flex gap-2 py-1 border-bottom" style="font-size:var(--font-size-sm)">
                    <span class="text-muted" style="min-width:120px">{{ \Carbon\Carbon::parse($h->sent_at)->format('d/m/Y H:i') }}</span>
                    @php $channel = $h->channel ?? 'email'; @endphp
                    <span class="text-muted" title="{{ __('event.renfort_tx_channel_'.$channel) }}" style="min-width:18px">
                        <i class="fas {{ ['email' => 'fa-envelope', 'sms' => 'fa-sms', 'in_app' => 'fa-bell'][$channel] ?? 'fa-paper-plane' }}"></i>
                    </span>
                    <span>
                        {{ __('event.renfort_tx_history_line', [
                            'section' => $h->S_CODE ?? '—',
                            'sender' => trim(strtoupper((string) $h->P_NOM).' '.$h->P_PRENOM) ?: '—',
                            'count' => $h->recipients,
                        ]) }}
                        @if($h->message)<div class="text-muted fst-italic">« {{ $h->message }} »</div>@endif
                    </span>
                </div>
            @empty
                <p class="ob-widget-empty mb-0">{{ __('event.renfort_tx_history_empty') }}</p>
            @endforelse
        </div>
    </div>
</div>

@push('scripts')
<script>
(function () {
    const totalInput = document.querySelector('input[name="nb_vehicules"]');
    const typeInputs = Array.from(document.querySelectorAll('input[name^="vehicle_types["]'));
    if (!totalInput || typeInputs.length === 0) return;

    // The total may legitimately exceed the per-type breakdown (e.g. "5 vehicles,
    // any type"). We only auto-raise it to the sum so the total is never lower
    // than the detail; a manually-entered higher total is preserved.
    function syncTotal() {
        const sum = typeInputs.reduce((acc, el) => acc + (parseInt(el.value, 10) || 0), 0);
        if ((parseInt(totalInput.value, 10) || 0) < sum) {
            totalInput.value = sum;
        }
    }
    typeInputs.forEach(el => el.addEventListener('input', syncTotal));
})();
</script>
@endpush

@endsection
