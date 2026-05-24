@extends('layouts.client')

@section('title', 'Thanh toán')

@php
$money = fn ($amount) => number_format((float) $amount, 0, ',', '.').'đ';
$payment = $payment ?? [];
$customerName = trim((string) ($payment['customer_name'] ?? ''));
$customerPhone = trim((string) ($payment['customer_phone'] ?? ''));
$customerEmail = trim((string) ($payment['customer_email'] ?? ''));
$paymentConfig = [
    'checkStatusUrl' => $payment['check_status_url'] ?? null,
    'applyCouponUrl' => $payment['apply_coupon_url'] ?? null,
    'historyUrl' => $payment['history_url'] ?? route('profile.history-booking.index'),
    'currentStatus' => $payment['order_status'] ?? '',
    'serverGrandTotal' => (float) ($payment['server_grand_total'] ?? $payment['grand_total'] ?? 0),
    'csrfToken' => csrf_token(),
];
@endphp

@section('content')

<section class="payment-page">
  <div class="payment-container">

    <h1 class="payment-title">Thanh toán</h1>

    @if (session('status'))
    <div class="alert alert-success">
      {{ session('status') }}
    </div>
    @endif

    @if ($errors->any())
    <div class="alert alert-danger">
      {{ $errors->first() }}
    </div>
    @endif

    <form action="{{ $payment['process_url'] ?? '#' }}" method="POST" class="payment-layout" data-payment-form>
      @csrf

      <div class="payment-left">
        <div class="payment-card">
          <h2>Thông tin liên hệ</h2>

          <div class="payment-form-group">
            <label>Họ và tên</label>
            <input
              type="text"
              name="customer_name"
              value="{{ old('customer_name', $customerName) }}"
              placeholder="Nhập họ và tên"
              @if ($customerName !== '') readonly @endif
            >
          </div>

          <div class="payment-form-group">
            <label>Số điện thoại</label>
            <input
              type="text"
              name="customer_phone"
              value="{{ old('customer_phone', $customerPhone) }}"
              placeholder="Nhập số điện thoại"
              @if ($customerPhone !== '') readonly @endif
            >
          </div>

          <div class="payment-form-group">
            <label>Email</label>
            <input
              type="email"
              name="customer_email"
              value="{{ old('customer_email', $customerEmail) }}"
              placeholder="Nhập email"
              @if ($customerEmail !== '') readonly @endif
            >
          </div>
        </div>

        <div class="payment-card">
          <h2>Phương thức thanh toán</h2>

          <label class="payment-method active">
            <input type="radio" name="payment_method" value="cod" checked>

            <span class="method-dot"></span>

            <span class="method-icon green">
              <i class="fa-solid fa-money-bill-wave"></i>
            </span>

            <span class="method-content">
              <strong>Tiền mặt khi nhận phòng (COD)</strong>
              <small>Thanh toán trực tiếp tại chi nhánh</small>
            </span>
          </label>

          <label class="payment-method">
            <input type="radio" name="payment_method" value="wallet">

            <span class="method-dot"></span>

            <span class="method-icon blue">
              <i class="fa-regular fa-wallet"></i>
            </span>

            <span class="method-content">
              <strong>Ví điện tử (MoMo, ZaloPay)</strong>
              <small>Ghi nhận phương thức, tích hợp cổng thanh toán sau</small>
            </span>
          </label>

          <label class="payment-method">
            <input type="radio" name="payment_method" value="bank">

            <span class="method-dot"></span>

            <span class="method-icon purple">
              <i class="fa-regular fa-credit-card"></i>
            </span>

            <span class="method-content">
              <strong>Chuyển khoản ngân hàng</strong>
              <small>Ghi nhận phương thức, nhân viên sẽ xác nhận sau</small>
            </span>
          </label>

        </div>
      </div>

      <aside class="payment-right">
        <div class="order-card">
          <h2>Chi tiết đơn hàng</h2>

          <div class="order-info">
            <span>Mã booking</span>
            <strong>#{{ $payment['booking_id'] ?? '' }}</strong>
          </div>

          <div class="order-info">
            <span>Chi nhánh</span>
            <strong>{{ $payment['branch_name'] ?? 'Chi nhánh đang cập nhật' }}</strong>
          </div>

          <div class="order-info">
            <span>Loại phòng</span>
            <strong>{{ $payment['room_names'] ?? 'Phòng đang cập nhật' }}</strong>
          </div>

          <div class="order-date-row">
            <div class="order-info">
              <span>Check-in</span>
              <strong>{{ $payment['checkin'] ?? 'Đang cập nhật' }}</strong>
            </div>

            <div class="order-info">
              <span>Check-out</span>
              <strong>{{ $payment['checkout'] ?? 'Đang cập nhật' }}</strong>
            </div>
          </div>

          <div class="order-divider"></div>

          @forelse (($payment['details'] ?? []) as $detail)
          <div class="order-price-row">
            <span>{{ $detail['title'] }} x{{ $detail['quantity'] }}</span>
            <strong>{{ $money($detail['line_total']) }}</strong>
          </div>
          @empty
          <div class="order-info">
            <span>Chi tiết</span>
            <strong>Đang cập nhật</strong>
          </div>
          @endforelse

          <div class="order-divider"></div>

          <div class="order-price-row">
            <span>Tiền phòng</span>
            <strong>{{ $money($payment['room_total'] ?? 0) }}</strong>
          </div>

          <div class="order-price-row">
            <span>Dịch vụ</span>
            <strong>{{ $money($payment['service_total'] ?? 0) }}</strong>
          </div>

          <div class="discount-box">
            <label>
              <i class="fa-solid fa-gift"></i>
              Mã giảm giá
            </label>

            <div class="discount-form">
              <input type="text" id="couponCodeInput" name="coupon_code"
                value="{{ old('coupon_code', $payment['coupon_code'] ?? '') }}" placeholder="Nhập mã (VD: WELCOME50)"
                data-coupon-input>
              <button type="button" data-apply-coupon>Áp dụng</button>
            </div>

            <p class="discount-message" data-coupon-message hidden></p>
          </div>

          <div class="order-price-row discount-row">
            <span>Giảm giá</span>
            <strong data-discount-amount>-{{ $money($payment['discount_amount'] ?? 0) }}</strong>
          </div>

          <div class="order-divider"></div>

          <div class="order-total">
            <span>Tổng cộng</span>
            <strong data-grand-total>{{ $money($payment['grand_total'] ?? 0) }}</strong>
          </div>

          <button type="submit" class="confirm-order-btn">
            Xác nhận thanh toán
          </button>

          <p class="payment-note">
            Bằng việc đặt hàng, bạn đồng ý với các điều khoản và chính sách của Pet Hotel
          </p>
        </div>
      </aside>
    </form>

  </div>
</section>

@endsection

@push('scripts')
<script type="application/json" id="payment-page-config">
@json($paymentConfig)
</script>
<script src="{{ asset('assets/client/js/payment.js') }}"></script>
@endpush
