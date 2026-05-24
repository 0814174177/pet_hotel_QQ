<?php

use App\Http\Controllers\Web\Customer\BookingController;
use App\Http\Controllers\Web\Customer\PaymentController;
use App\Http\Controllers\Web\Customer\Profile\AccountController;
use App\Http\Controllers\Web\Customer\Profile\PetController;
use App\Http\Controllers\Web\Default\RoomController;
use App\Http\Controllers\Web\ErrorController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| 1. Public Routes
|--------------------------------------------------------------------------
| Cac trang mac dinh ma moi actor deu co the xem truoc khi dang nhap.
*/
require __DIR__ . '/public/home.php';
require __DIR__ . '/public/service.php';
require __DIR__ . '/public/branches.php';
require __DIR__ . '/public/policies.php';
require __DIR__ . '/public/hotel.php';

/*
|--------------------------------------------------------------------------
| 2. Authentication Routes
|--------------------------------------------------------------------------
| Web.php chi khai bao alias URL ngan. Logic chi tiet nam trong authentication.
*/
Route::redirect('/login', '/authentication/login')->name('login');
Route::redirect('/login-page', '/authentication/login')->name('login-page');
Route::redirect('/register', '/authentication/register')->name('register');
Route::redirect('/forgot-password', '/authentication/forgot-password')->name('forgot-password');
Route::redirect('/reset-password', '/authentication/reset-password')->name('reset-password');

require __DIR__ . '/authentication/index.php';

/*
|--------------------------------------------------------------------------
| 2.1 Client UI Routes
|--------------------------------------------------------------------------
| Cac URL nay khop voi link trong giao dien client da dung san.
*/
Route::controller(RoomController::class)->prefix('rooms')->name('rooms.')->group(function () {
    Route::get('/dog', 'dog')->name('dog');
    Route::get('/cat', 'cat')->name('cat');
    Route::get('/{roomId}', 'show')->name('show');
});

Route::get('/type-room/{typeRoomId}', [RoomController::class, 'typeRoom'])->name('type-room.show');

Route::controller(BookingController::class)->group(function () {
    Route::get('/booking', 'selectBranch')->name('booking.select');
    Route::get('/booking/branches/{branchId}', 'createFromBranch')->name('booking.create.from-branches');
    Route::get('/booking/branch/{branchId}', 'createFromBranch')->name('booking.create');
    Route::post('/booking', 'store')->name('booking.store');
    Route::get('/profile/history-booking', 'index')->name('profile.history-booking.index');
    Route::get('/booking/{bookingId}', 'show')->name('booking.show');
});

Route::controller(PaymentController::class)->group(function () {
    Route::get('/payment', 'create')->name('payment.create');
    Route::get('/payment/check-status/{bookingId}', 'checkStatus')->name('payment.check_status');
    Route::get('/payment/booking/{bookingId}', 'show')->name('payment.show');
    Route::post('/payment/booking/{bookingId}/coupon', 'applyCoupon')->name('payment.apply_coupon');
    Route::post('/payment/booking/{bookingId}', 'process')->name('payment.process');
    Route::get('/payment/success', 'success')->name('payment.success');
    Route::get('/payment/failed', 'failed')->name('payment.failed');
});

Route::controller(AccountController::class)->group(function () {
    Route::get('/profile', 'show')->name('profile.index');
    Route::get('/profile/edit', 'edit')->name('profile.edit');
    Route::post('/profile', 'update')->name('profile.update');
});

Route::controller(PetController::class)->prefix('pets')->name('pets.')->group(function () {
    Route::get('/', 'index')->name('index');
    Route::get('/create', 'create')->name('create');
    Route::post('/', 'store')->name('store');
    Route::get('/{petId}/edit', 'edit')->name('edit');
    Route::post('/{petId}', 'update')->name('update');
});

Route::controller(PetController::class)->prefix('profile/pets')->name('profile.pets.')->group(function () {
    Route::get('/', 'index')->name('index');
    Route::get('/create', 'create')->name('create');
    Route::post('/', 'store')->name('store');
    Route::get('/{petId}/edit', 'edit')->name('edit');
    Route::post('/{petId}', 'update')->name('update');
});

/*
|--------------------------------------------------------------------------
| 3. Role-Based Areas
|--------------------------------------------------------------------------
| Phan tach luong truy cap dua tren vai tro: Customer, Manager, CEO.
*/
Route::prefix('customer')
    ->name('customer.')
    ->middleware('customer.api.token')
    ->group(function () {
        require __DIR__ . '/customer/index.php';
    });

Route::prefix('manager')
    ->name('manager.')
    ->middleware(['auth', 'role:manager'])
    ->group(function () {
        require __DIR__ . '/manager/index.php';
    });

Route::prefix('ceo')
    ->name('ceo.')
    ->middleware(['auth', 'role:ceo'])
    ->group(function () {
        require __DIR__ . '/ceo/index.php';
    });

/*
|--------------------------------------------------------------------------
| 4. Error Handling & Fallback
|--------------------------------------------------------------------------
*/
Route::controller(ErrorController::class)->group(function () {
    
    // Xử lý lỗi 403 (Forbidden): Người dùng đăng nhập nhưng sai vai trò (Ví dụ: Khách hàng cố vào trang CEO)
    Route::get('/unauthorized', 'unauthorized')->name('unauthorized');

    // Xử lý lỗi 404 (Not Found): Tài nguyên (sản phẩm, bài viết) đã bị xóa hoặc không tồn tại
    Route::get('/404', 'notFound')->name('404');
    Route::get('/500', 'serverError')->name('500');

    // Xử lý luồng URL rác (Catch-all): Bắt mọi đường dẫn người dùng gõ bậy trên thanh địa chỉ
    Route::fallback('fallback');
    
});
