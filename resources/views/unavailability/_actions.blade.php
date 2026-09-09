@php
    $status = $i->I_STATUS ?? 'ATT';
    $isPending = $status === 'ATT';
    $isActive = in_array($status, ['ATT', 'VAL'], true);
@endphp
<div class="d-flex gap-1 justify-content-end">
    @if($canManage && $isPending)
        <form method="POST" action="{{ route('unavailability.decide', $i->I_CODE) }}">
            @csrf
            <button type="submit" name="decision" value="accept" class="btn btn-xs btn-success" title="{{ __('unavailability.accept') }}">
                <i class="fas fa-check"></i>
            </button>
        </form>
        <form method="POST" action="{{ route('unavailability.decide', $i->I_CODE) }}">
            @csrf
            <button type="submit" name="decision" value="reject" class="btn btn-xs btn-outline-danger" title="{{ __('unavailability.reject') }}">
                <i class="fas fa-times"></i>
            </button>
        </form>
    @endif
    @if(($canManage || $isSelf) && $isActive)
        <form method="POST" action="{{ route('unavailability.cancel', $i->I_CODE) }}"
              onsubmit="return confirm('{{ __('unavailability.confirm_cancel') }}');">
            @csrf
            <button type="submit" class="btn btn-xs btn-outline-secondary" title="{{ __('unavailability.cancel_absence') }}">
                <i class="fas fa-ban"></i>
            </button>
        </form>
    @endif
</div>
