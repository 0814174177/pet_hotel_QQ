<?php

use App\Http\Controllers\Web\Customer\PaymentController;
use Illuminate\Support\Facades\Route;
/*
|--------------------------------------------------------------------------
| Customer Payment Routes
|--------------------------------------------------------------------------
|
| Nhóm route xử lý chức năng thanh toán của khách hàng:
| - Hiển thị trang thanh toán cho một booking cụ thể
| - Xử lý giao dịch thanh toán
| - Hiển thị kết quả thanh toán thành công hoặc thất bại
|
*/

Route::controller(PaymentController::class)->group(function () {
    // Hiển thị trang thanh toán cho đơn đặt phòng
    Route::get('/booking/{bookingId}', 'show')->name('show');

    // Xử lý thanh toán cho đơn đặt phòng
    Route::post('/booking/{bookingId}/coupon', 'applyCoupon')->name('apply_coupon');
    Route::post('/booking/{bookingId}', 'process')->name('process');

    // Trang thông báo thanh toán thành công
    Route::get('/success', 'success')->name('success');

    // Trang thông báo thanh toán thất bại
    Route::get('/failed', 'failed')->name('failed');
});
