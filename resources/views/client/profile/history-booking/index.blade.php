@extends('layouts.client')

@section('title', 'Lịch sử đặt phòng')

@push('styles')
    <link rel="stylesheet" href="{{ asset('assets/client/css/booking-history.css') }}">
@endpush

@section('content')

<section class="bh-page">
  <div class="bh-container">

    <div class="bh-header">
      <div>
        <h1>Lịch sử đặt phòng</h1>
        <p>{{ count($bookings ?? []) }} đơn booking trong hồ sơ của bạn</p>
      </div>
    </div>

    @if ($errors->any())
    <div class="bh-alert bh-alert-error">
      {{ $errors->first() }}
    </div>
    @endif

    @if (session('status'))
    <div class="bh-alert bh-alert-success">
      {{ session('status') }}
    </div>
    @endif

    <div class="bh-tabs" id="booking-tabs">
      <button type="button" class="bh-tab active" data-target="all">Tất cả</button>
      <button type="button" class="bh-tab" data-target="active">Đang đặt</button>
      <button type="button" class="bh-tab" data-target="done">Hoàn thành</button>
      <button type="button" class="bh-tab" data-target="cancelled">Đã hủy</button>
    </div>

    <div class="bh-list" id="booking-list">
      @forelse ($bookings as $booking)
        @include('client.profile.history-booking.item', ['booking' => $booking])
      @empty
        <div class="bh-empty">
          <i class="fa-regular fa-folder-open"></i>
          <h3>Chưa có đơn booking</h3>
          <p>Các đơn đặt phòng sau khi tạo sẽ được lưu tại đây.</p>
          <a href="{{ route('booking.select') }}" class="bh-btn bh-btn-pay">Đặt phòng ngay</a>
        </div>
      @endforelse
    </div>

    @if (! empty($bookings))
      <div class="bh-empty bh-filter-empty" id="booking-filter-empty" hidden>
        <i class="fa-regular fa-folder-open"></i>
        <h3>Không có booking phù hợp</h3>
        <p>Thử chọn một nhóm trạng thái khác.</p>
      </div>
    @endif

  </div>
</section>

@endsection

@push('scripts')
    <script src="{{ asset('assets/client/js/booking-history.js') }}"></script>
@endpush
