@extends('layout.app')

@section('title', 'Configuration — ' . config('app.name'))

@push('scripts')
<script>
(function () {
    var active = '{{ $activeTab }}';
    document.addEventListener('DOMContentLoaded', function () {
        if (active) {
            var btn = document.getElementById('tab-' + active + '-btn');
            if (btn) btn.click();
        }
        document.querySelectorAll('.ob-setting-toggle').forEach(function (el) {
            el.addEventListener('change', function () {
                this.closest('form').submit();
            });
        });
    });
})();
</script>
@endpush

@section('content')

<x-ob-breadcrumb :items="[
    ['label' => __('admin.administration')], {{-- i18n-ignore --}}
    ['label' => __('admin.settings.title')],
]"/>

<div class="mx-3 mt-3">
    <div class="ob-widget-card mb-3">
        <div class="ob-widget-card-header">
            <div class="ob-widget-card-title"><i class="fas fa-cog me-2"></i>{{ __('admin.settings.app_config') }}</div>
        </div>

        {{-- Tab nav --}}
        <ul class="nav nav-tabs px-3 pt-2" role="tablist">
            @foreach($tabs as $tabId => $tab)
                @if($grouped->has($tabId))
                    <li class="nav-item" role="presentation">
                        <button class="nav-link @if($loop->first) active @endif"
                                id="tab-{{ $tabId }}-btn"
                                data-bs-toggle="tab"
                                data-bs-target="#tab-{{ $tabId }}"
                                type="button" role="tab">
                            <i class="fas fa-{{ $tab['icon'] }} me-1"></i>{{ $tab['label'] }}
                        </button>
                    </li>
                @endif
            @endforeach
        </ul>

        <div class="tab-content p-3">
            @foreach($tabs as $tabId => $tab)
                @if($grouped->has($tabId))
                    <div class="tab-pane fade @if($loop->first) show active @endif"
                         id="tab-{{ $tabId }}" role="tabpanel">

                        @if($tabId === 4)
                            {{-- Organisation-type management + "configured elsewhere" guide --}}
                            <div class="ob-widget-card mb-3 p-3 d-flex flex-wrap align-items-center gap-3"
                                 style="background:var(--surface-2, transparent);">
                                <div class="rounded-circle d-flex align-items-center justify-content-center flex-shrink-0"
                                     style="width:44px;height:44px;background:var(--sidebar-bg);color:var(--sidebar-text);">
                                    <i class="fas fa-sitemap"></i>
                                </div>
                                <div class="flex-grow-1">
                                    <div class="fw-semibold">{{ __('admin.settings.org_card_title') }}</div>
                                    <div class="text-muted" style="font-size:var(--font-size-xs);">
                                        {{ __('admin.settings.org_card_current') }} <strong>{{ $orgTypeLabel }}</strong>
                                        — {{ __('admin.settings.org_card_manage_hint') }}
                                    </div>
                                </div>
                                <a href="{{ route('setup.org-type') }}" class="btn btn-sm btn-primary">
                                    <i class="fas fa-arrow-right me-1"></i>{{ __('admin.settings.org_card_manage') }}
                                </a>
                            </div>

                            <div class="alert alert-light border" style="font-size:var(--font-size-sm);">
                                <div class="fw-semibold mb-1">
                                    <i class="fas fa-info-circle me-1"></i>{{ __('admin.settings.elsewhere_title') }}
                                </div>
                                <ul class="mb-0 ps-3">
                                    <li>{{ __('admin.settings.elsewhere_app_name') }} — <em>{{ __('admin.settings.elsewhere_app_name_where') }}</em></li>
                                    <li>{{ __('admin.settings.elsewhere_app_url') }} — <em>{{ __('admin.settings.elsewhere_app_url_where') }}</em></li>
                                    <li>{{ __('admin.settings.elsewhere_favicon') }} — <em>{{ __('admin.settings.elsewhere_favicon_where') }}</em></li>
                                    <li>{{ __('admin.settings.elsewhere_org_type') }} — <em>{{ __('admin.settings.elsewhere_org_type_where') }}</em></li>
                                </ul>
                            </div>
                        @endif

                        <table class="table table-sm table-hover mb-0">
                            <tbody>
                                @foreach($grouped->get($tabId) as $row)
                                    @php($label = strip_tags($row->DISPLAY_NAME ?: $row->NAME))
                                    <tr>
                                        <td style="width:40%;vertical-align:middle;font-size:var(--font-size-sm);">
                                            <span title="{{ $row->DESCRIPTION ?? '' }}">{{ $label }}</span>
                                            @if(isset($annotations[$row->ID]))
                                                @php($ann = $annotations[$row->ID])
                                                <span class="ms-1 ob-badge {{ $ann['type'] === 'obsolete' ? 'ob-badge-archive' : 'ob-badge-ext' }}"
                                                      style="font-size:10px;" title="{{ $ann['note'] }}">
                                                    {{ $ann['type'] === 'obsolete' ? __('admin.obsolete') : __('admin.not_implemented') }}
                                                </span>
                                                <div style="font-size:var(--font-size-xs);color:var(--text-muted-soft);margin-top:2px;">
                                                    <i class="fas fa-info-circle me-1"></i>{{ $ann['note'] }}
                                                </div>
                                            @elseif($row->DESCRIPTION)
                                                <div class="text-muted" style="font-size:var(--font-size-xs);">
                                                    {{ Str::limit($row->DESCRIPTION, 120) }}
                                                </div>
                                            @endif
                                        </td>
                                        <td style="vertical-align:middle;">
                                            @if($row->ID == 1)
                                                {{-- Version: read-only --}}
                                                <span class="ob-badge ob-badge-int">{{ $row->VALUE }}</span>

                                            @elseif($row->IS_FILE)
                                                {{-- Image upload --}}
                                                @php($hasImg = $row->VALUE && str_starts_with($row->VALUE, 'theme/') && Storage::disk('public')->exists($row->VALUE))
                                                @php($imgUrl = $hasImg ? Storage::url($row->VALUE) : null)
                                                <div class="d-flex align-items-center gap-3 flex-wrap">
                                                    @if($imgUrl)
                                                        <img src="{{ $imgUrl }}" alt="{{ $label }}"
                                                             style="max-height:48px;max-width:120px;object-fit:contain;border:1px solid var(--border-color);border-radius:var(--radius-sm);padding:2px;">
                                                        <form method="POST" action="{{ route('admin.settings.delete-file', $row->ID) }}">
                                                            @csrf @method('DELETE')
                                                            <input type="hidden" name="_tab" value="{{ $tabId }}">
                                                            <button type="submit" class="btn btn-sm btn-outline-danger"
                                                                    onclick="return confirm('{{ __('admin.settings.delete_image_confirm') }}')">
                                                                <i class="fas fa-trash"></i>
                                                            </button>
                                                        </form>
                                                    @else
                                                        <span class="text-muted fst-italic" style="font-size:var(--font-size-xs);">{{ __('admin.settings.no_image') }}</span>
                                                    @endif
                                                    <form method="POST" action="{{ route('admin.settings.upload', $row->ID) }}"
                                                          enctype="multipart/form-data" class="d-flex align-items-center gap-2">
                                                        @csrf
                                                        <input type="hidden" name="_tab" value="{{ $tabId }}">
                                                        <input type="file" name="file" class="form-control form-control-sm"
                                                               accept="image/*" style="max-width:220px;">
                                                        <button type="submit" class="btn btn-sm btn-primary">
                                                            <i class="fas fa-upload"></i>
                                                        </button>
                                                    </form>
                                                </div>

                                            @elseif($row->YESNO)
                                                {{-- Toggle --}}
                                                <form method="POST" action="{{ route('admin.settings.save', $row->ID) }}">
                                                    @csrf @method('PATCH')
                                                    <input type="hidden" name="_tab" value="{{ $tabId }}">
                                                    <input type="hidden" name="toggle" value="1">
                                                    <div class="form-check form-switch">
                                                        <input class="form-check-input ob-setting-toggle"
                                                               type="checkbox"
                                                               name="VALUE"
                                                               value="1"
                                                               {{ $row->VALUE == '1' ? 'checked' : '' }}>
                                                    </div>
                                                </form>

                                            @elseif($row->ID == 76)
                                                {{-- Timezone: dropdown grouped by region. Inline form only — a block
                                                     php directive would pair with the earlier inline ones. --}}
                                                @php($tzByRegion = collect(timezone_identifiers_list())->groupBy(fn ($tz) => str_contains($tz, '/') ? strtok($tz, '/') : __('admin.settings.tz_other')))
                                                <form method="POST" action="{{ route('admin.settings.save', $row->ID) }}"
                                                      class="d-flex gap-2 align-items-center">
                                                    @csrf @method('PATCH')
                                                    <input type="hidden" name="_tab" value="{{ $tabId }}">
                                                    <select name="VALUE" class="form-select form-select-sm" style="max-width:280px;">
                                                        @foreach ($tzByRegion as $region => $zones)
                                                            <optgroup label="{{ $region }}">
                                                                @foreach ($zones as $tz)
                                                                    <option value="{{ $tz }}" @selected($row->VALUE === $tz)>{{ $tz }}</option>
                                                                @endforeach
                                                            </optgroup>
                                                        @endforeach
                                                    </select>
                                                    <button type="submit" class="btn btn-sm btn-primary">
                                                        <i class="fas fa-save"></i>
                                                    </button>
                                                </form>

                                            @elseif($row->ID == 54)
                                                {{-- Error reporting: special select --}}
                                                <form method="POST" action="{{ route('admin.settings.save', $row->ID) }}"
                                                      class="d-flex gap-2 align-items-center">
                                                    @csrf @method('PATCH')
                                                    <input type="hidden" name="_tab" value="{{ $tabId }}">
                                                    <select name="VALUE" class="form-select form-select-sm" style="max-width:220px;">
                                                        <option value="0" @selected($row->VALUE=='0')>{{ __('admin.settings.error_none') }}</option>
                                                        <option value="1" @selected($row->VALUE=='1')>{{ __('admin.settings.error_errors_only') }}</option>
                                                        <option value="2" @selected($row->VALUE=='2')>{{ __('admin.settings.error_errors_warn') }}</option>
                                                        <option value="3" @selected($row->VALUE=='3')>{{ __('admin.settings.error_all') }}</option>
                                                    </select>
                                                    <button type="submit" class="btn btn-sm btn-primary">
                                                        <i class="fas fa-save"></i>
                                                    </button>
                                                </form>

                                            @elseif($row->ID == 47)
                                                {{-- Availability periods per 24h --}}
                                                <form method="POST" action="{{ route('admin.settings.save', $row->ID) }}"
                                                      class="d-flex gap-2 align-items-center">
                                                    @csrf @method('PATCH')
                                                    <input type="hidden" name="_tab" value="{{ $tabId }}">
                                                    <select name="VALUE" class="form-select form-select-sm" style="max-width:320px;">
                                                        <option value="1" @selected($row->VALUE=='1')>{{ __('admin.settings.dispo_periodes_1') }}</option>
                                                        <option value="2" @selected($row->VALUE=='2')>{{ __('admin.settings.dispo_periodes_2') }}</option>
                                                        <option value="3" @selected($row->VALUE=='3')>{{ __('admin.settings.dispo_periodes_3') }}</option>
                                                        <option value="4" @selected($row->VALUE=='4')>{{ __('admin.settings.dispo_periodes_4') }}</option>
                                                    </select>
                                                    <button type="submit" class="btn btn-sm btn-primary">
                                                        <i class="fas fa-save"></i>
                                                    </button>
                                                </form>

                                            @elseif($row->ID == 44)
                                                {{-- Encryption method --}}
                                                <form method="POST" action="{{ route('admin.settings.save', $row->ID) }}"
                                                      class="d-flex gap-2 align-items-center">
                                                    @csrf @method('PATCH')
                                                    <input type="hidden" name="_tab" value="{{ $tabId }}">
                                                    <select name="VALUE" class="form-select form-select-sm" style="max-width:160px;">
                                                        <option value="md5"    @selected($row->VALUE=='md5')>MD5</option>
                                                        <option value="pbkdf2" @selected($row->VALUE=='pbkdf2')>PBKDF2</option>
                                                    </select>
                                                    <button type="submit" class="btn btn-sm btn-primary">
                                                        <i class="fas fa-save"></i>
                                                    </button>
                                                </form>

                                            @elseif($row->ID == 15)
                                                {{-- Password quality level --}}
                                                <form method="POST" action="{{ route('admin.settings.save', $row->ID) }}"
                                                      class="d-flex gap-2 align-items-center">
                                                    @csrf @method('PATCH')
                                                    <input type="hidden" name="_tab" value="{{ $tabId }}">
                                                    <select name="VALUE" class="form-select form-select-sm" style="max-width:200px;">
                                                        <option value="0" @selected($row->VALUE=='0')>{{ __('admin.settings.pwd_no_restriction') }}</option>
                                                        <option value="1" @selected($row->VALUE=='1')>{{ __('admin.settings.pwd_minimum') }}</option>
                                                        <option value="2" @selected($row->VALUE=='2')>{{ __('admin.settings.pwd_strong') }}</option>
                                                    </select>
                                                    <button type="submit" class="btn btn-sm btn-primary">
                                                        <i class="fas fa-save"></i>
                                                    </button>
                                                </form>

                                            @else
                                                {{-- Text / number input --}}
                                                <form method="POST" action="{{ route('admin.settings.save', $row->ID) }}"
                                                      class="d-flex gap-2 align-items-center">
                                                    @csrf @method('PATCH')
                                                    <input type="hidden" name="_tab" value="{{ $tabId }}">
                                                    <input type="text" name="VALUE"
                                                           value="{{ $row->VALUE }}"
                                                           class="form-control form-control-sm"
                                                           style="max-width:280px;">
                                                    <button type="submit" class="btn btn-sm btn-primary">
                                                        <i class="fas fa-save"></i>
                                                    </button>
                                                </form>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            @endforeach
        </div>
    </div>
</div>

@endsection
