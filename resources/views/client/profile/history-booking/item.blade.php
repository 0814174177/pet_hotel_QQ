@php
    $group = $booking['group'] ?? 'active';
    $cardClass = match ($group) {
        'cancelled' => 'is-cancelled',
        'done' => 'is-done',
        default => '',
    };
    $iconClass = match ($group) {
        'cancelled' => 'bh-icon-cancelled',
        'done' => 'bh-icon-done',
        default => 'bh-icon-active',
    };
    $iconName = match ($group) {
        'cancelled' => 'fa-calendar-xmark',
        'done' => 'fa-circle-check',
        default => 'fa-calendar-check',
    };
@endphp

<div class="bh-card {{ $cardClass }} js-booking-item" data-group="{{ $group }}">
    <div class="bh-card-icon {{ $iconClass }}">
        <i class="fa-regular {{ $iconName }}"></i>
    </div>

    <div class="bh-card-body">
        <div class="bh-card-title">
            <a href="{{ $booking['detail_url'] }}">{{ $booking['title'] }}</a>

            @if (($booking['pet_count'] ?? 0) > 0)
                <span class="bh-pet-tag">
                    <i class="fa-solid fa-paw"></i>
                    {{ $booking['pet_count'] }} thú cưng
                </span>
            @endif
        </div>

        <div class="bh-card-meta">
            <i class="fa-regular fa-calendar"></i>
            <span>{{ $booking['date_range'] }}</span>
            <span class="bh-dot">·</span>
            <span>{{ $booking['branch_name'] }}</span>
        </div>
    </div>

    <div class="bh-card-actions">
        @if ($group === 'cancelled')
            <span class="bh-btn bh-badge-cancelled">Đã hủy</span>
        @elseif ($group === 'done')
            <span class="bh-btn bh-badge-done">Hoàn thành</span>
        @else
            @if ($booking['show_payment'])
                <a href="{{ $booking['payment_url'] }}" class="bh-btn bh-btn-pay">Chưa thanh toán</a>
            @else
                <span class="bh-btn bh-badge-paid">Đã thanh toán</span>
            @endif
        @endif

        <a href="{{ $booking['detail_url'] }}" class="bh-btn bh-btn-detail">Xem chi tiết</a>
    </div>
</div>
