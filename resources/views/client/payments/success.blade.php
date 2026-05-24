@extends('layouts.client')

@section('title', 'Thanh toán thành công')

@php
$payment = $payment ?? [];
$money = fn ($amount) => number_format((float) $amount, 0, ',', '.').'đ';
$bookingUrl = $payment['booking_url'] ?? route('profile.history-booking.index');
$historyUrl = $payment['history_url'] ?? route('profile.history-booking.index');
$homeUrl = $payment['home_url'] ?? route('home');
$successTrackingData = [
'transaction_id' => (string) ($payment['order_id'] ?? 'N/A'),
'value' => (float) ($payment['grand_total'] ?? 0),
'currency' => 'VND',
'items' => [
[
'item_name' => $payment['room_names'] ?? 'Unknown Room',
'price' => (float) ($payment['grand_total'] ?? 0),
'quantity' => 1,
],
],
];
@endphp

@push('styles')
<link rel="stylesheet" href="{{ asset('assets/client/css/payment-success.css') }}">
@endpush

@section('content')
<div class="ps-wrapper">
  <div class="ps-hero">
    <div class="ps-icon-ring">
      <i class="fa-solid fa-check"></i>
      <div class="ps-paw-badge">🐾</div>
    </div>

    <h1 class="ps-hero-title">Thanh toán thành công!</h1>
    <p class="ps-hero-sub">
      Cảm ơn bạn đã sử dụng dịch vụ <strong>Pet Hotel</strong>. Chúng tôi sẽ chăm sóc người bạn nhỏ của bạn thật chu
      đáo.
    </p>

    @if (session('status'))
    <div class="ps-status-message">
      {{ session('status') }}
    </div>
    @endif

    <div class="ps-meta-row">
      <div class="ps-meta-tag ps-copy-tag" data-copy-target="booking-id" role="button" tabindex="0"
        title="Sao chép mã booking">
        Mã booking <span id="booking-id">#{{ $payment['booking_id'] ?? '' }}</span>
      </div>
      <div class="ps-meta-tag ps-copy-tag" data-copy-target="invoice-id" role="button" tabindex="0"
        title="Sao chép mã hóa đơn">
        Mã hóa đơn <span id="invoice-id">#{{ $payment['order_id'] ?? '' }}</span>
      </div>
    </div>
  </div>

  <div class="ps-card">
    <div class="ps-card-header">
      <div class="ps-card-header-icon">
        <i class="fa-solid fa-receipt"></i>
      </div>
      <h2 class="ps-card-header-title">Chi tiết đặt phòng</h2>
    </div>

    <div class="ps-card-body">
      <div class="ps-info-grid">
        <div class="ps-info-row">
          <span class="ps-info-label"><i class="fa-solid fa-location-dot"></i> Chi nhánh</span>
          <span class="ps-info-value">{{ $payment['branch_name'] ?? 'Đang cập nhật' }}</span>
        </div>

        <div class="ps-info-row">
          <span class="ps-info-label"><i class="fa-solid fa-bed"></i> Loại phòng</span>
          <span class="ps-info-value">{{ $payment['room_names'] ?? 'Đang cập nhật' }}</span>
        </div>

        <div class="ps-info-row">
          <span class="ps-info-label"><i class="fa-regular fa-calendar-check"></i> Ngày check-in</span>
          <span class="ps-info-value">{{ $payment['checkin'] ?? 'Đang cập nhật' }}</span>
        </div>

        <div class="ps-info-row">
          <span class="ps-info-label"><i class="fa-regular fa-calendar-xmark"></i> Ngày check-out</span>
          <span class="ps-info-value">{{ $payment['checkout'] ?? 'Đang cập nhật' }}</span>
        </div>

        <div class="ps-info-row">
          <span class="ps-info-label"><i class="fa-regular fa-moon"></i> Số đêm</span>
          <span class="ps-info-value">{{ $payment['nights'] ?? 0 }} đêm</span>
        </div>
      </div>

      <div class="ps-divider">
        <div class="ps-divider-line"></div>
        <div class="ps-divider-paw">🐾</div>
        <div class="ps-divider-line"></div>
      </div>

      <div class="ps-info-grid">
        <div class="ps-info-row">
          <span class="ps-info-label"><i class="fa-regular fa-user"></i> Họ tên</span>
          <span class="ps-info-value">{{ ($payment['customer_name'] ?? '') ?: 'Đang cập nhật' }}</span>
        </div>

        <div class="ps-info-row">
          <span class="ps-info-label"><i class="fa-solid fa-phone"></i> Số điện thoại</span>
          <span class="ps-info-value">{{ ($payment['customer_phone'] ?? '') ?: 'Đang cập nhật' }}</span>
        </div>

        <div class="ps-info-row">
          <span class="ps-info-label"><i class="fa-regular fa-envelope"></i> Email</span>
          <span class="ps-info-value">{{ ($payment['customer_email'] ?? '') ?: 'Đang cập nhật' }}</span>
        </div>

        <div class="ps-info-row">
          <span class="ps-info-label"><i class="fa-regular fa-credit-card"></i> Phương thức</span>
          <div class="ps-payment-method">
            <i class="fa-solid fa-money-bill-wave"></i>
            {{ $payment['payment_method'] ?? 'Tiền mặt khi nhận phòng' }}
          </div>
        </div>
      </div>

      <div class="ps-total-section">
        @if (! empty($payment['coupon_code']))
        <div class="ps-discount-row">
          <div class="ps-discount-left">
            <div class="ps-coupon-badge">
              <i class="fa-solid fa-tag"></i>
              {{ $payment['coupon_code'] }}
            </div>
            <span class="ps-discount-label">Giảm giá</span>
          </div>
          <span class="ps-discount-amount">- {{ $money($payment['discount_amount'] ?? 0) }}</span>
        </div>
        @endif

        <div class="ps-total-block">
          <span class="ps-total-label">Tổng thanh toán</span>
          <span class="ps-total-amount">{{ $money($payment['grand_total'] ?? 0) }}</span>
        </div>
      </div>
    </div>
  </div>

  <div class="ps-actions">
    <a href="{{ $bookingUrl }}" class="ps-btn ps-btn-primary">
      <i class="fa-regular fa-eye"></i> Xem chi tiết booking
    </a>
    <a href="{{ $historyUrl }}" class="ps-btn ps-btn-secondary">
      <i class="fa-solid fa-clock-rotate-left"></i> Về lịch sử đặt phòng
    </a>
    <a href="{{ $homeUrl }}" class="ps-btn ps-btn-ghost">
      <i class="fa-solid fa-house"></i> Về trang chủ
    </a>
  </div>
</div>
@endsection

@push('scripts')
<script type="application/json" id="payment-success-data">
@json($successTrackingData)
</script>
<script src="https://cdn.jsdelivr.net/npm/canvas-confetti@1.6.0/dist/confetti.browser.min.js"></script>
<script src="{{ asset('assets/client/js/payment-success.js') }}"></script>
@endpush