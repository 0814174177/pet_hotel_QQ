<?php

namespace App\Http\Controllers\Web\Customer;

use App\Http\Controllers\Web\WebController;
use App\Repositories\Contracts\PaymentRepositoryInterface;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class PaymentController extends WebController
{
    public function __construct(private PaymentRepositoryInterface $payments)
    {
    }

    public function create(Request $request): View|RedirectResponse
    {
        if ($redirect = $this->redirectIfGuest('Vui long dang nhap truoc khi thanh toan.')) { // Kiểm tra đăng nhập trước khi hiển thị trang thanh toán
            return $redirect;
        }

        $bookingId = $request->query('booking_id'); // Lấy booking_id từ query parameter để hiển thị trang thanh toán cho booking đó

        if (! $bookingId) { // Nếu không có booking_id trong query parameter, chuyển hướng về trang lịch sử đặt phòng với thông báo lỗi
            return $this->missingPaymentRedirect('Khong tim thay thong tin booking can thanh toan.');
        }

        return $this->paymentViewForBooking((string) $bookingId);
    }

    public function show(string $bookingId): View|RedirectResponse
    {
        if ($redirect = $this->redirectIfGuest('Vui long dang nhap truoc khi thanh toan.')) {
            return $redirect;
        }

        return $this->paymentViewForBooking($bookingId);
    }

    public function process(Request $request, string $bookingId): RedirectResponse
    {
        if ($redirect = $this->redirectIfGuest('Vui long dang nhap truoc khi thanh toan.')) {
            return $redirect;
        }

        $validated = $request->validate([
            'customer_name' => ['required', 'string', 'max:100'],
            'customer_phone' => [
                'required',
                'string',
                'max:20',
                Rule::unique('customer', 'phone')->ignore(Auth::user()?->customer?->customer_id, 'customer_id'),
            ],
            'customer_email' => [
                'required',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore(Auth::id()),
            ],
            'payment_method' => ['required', 'in:cod,wallet,bank'],
            'coupon_code' => ['nullable', 'string', 'max:50'],
        ], [
            'customer_name.required' => 'Vui long nhap ho va ten.',
            'customer_name.max' => 'Ho va ten khong duoc vuot qua 100 ky tu.',
            'customer_phone.required' => 'Vui long nhap so dien thoai.',
            'customer_phone.unique' => 'So dien thoai nay da duoc su dung.',
            'customer_email.required' => 'Vui long nhap email.',
            'customer_email.email' => 'Email khong hop le.',
            'customer_email.unique' => 'Email nay da duoc su dung.',
            'payment_method.required' => 'Vui long chon phuong thuc thanh toan.',
            'payment_method.in' => 'Phuong thuc thanh toan khong hop le.',
            'coupon_code.max' => 'Ma giam gia khong duoc vuot qua 50 ky tu.',
        ]);

        $booking = $this->payments->confirmBookingPaymentForUser(
            Auth::user(),
            $bookingId,
            $validated['payment_method'],
            $validated['coupon_code'] ?? null,
            [
                'customer_name' => $validated['customer_name'],
                'customer_phone' => $validated['customer_phone'],
                'customer_email' => $validated['customer_email'],
            ]
        );

        if (! $booking) {
            return $this->missingPaymentRedirect('Don booking khong ton tai hoac khong thuoc tai khoan cua ban.');
        }

        return redirect()
            ->route('payment.success', ['booking_id' => $booking->booking_id])
            ->with('status', 'Thanh toan thanh cong. Cam on ban da su dung dich vu cua Pet Hotel.');
    }

    public function applyCoupon(Request $request, string $bookingId): JsonResponse
    {
        if (! Auth::check()) {
            return response()->json([
                'exists' => false,
                'message' => 'Ban chua dang nhap.',
            ], 401);
        }

        $validated = $request->validate([
            'coupon_code' => ['nullable', 'string', 'max:50'],
        ], [
            'coupon_code.max' => 'Ma giam gia khong duoc vuot qua 50 ky tu.',
        ]);

        $preview = $this->payments->previewCouponForUser(
            Auth::user(),
            $bookingId,
            $validated['coupon_code'] ?? null
        );

        if (! $preview) {
            return response()->json([
                'exists' => false,
                'message' => 'Hoa don khong ton tai.',
            ], 404);
        }

        return response()->json([
            'exists' => true,
            'payment' => $preview,
        ]);
    }

    public function success(Request $request): View|RedirectResponse
    {
        if ($redirect = $this->redirectIfGuest('Vui long dang nhap de xem ket qua thanh toan.')) {
            return $redirect;
        }

        $bookingId = $request->query('booking_id');

        if (! $bookingId) {
            return redirect()->route('profile.history-booking.index');
        }

        $orderStatus = $this->payments->orderStatusForUser(Auth::user(), (string) $bookingId);

        if (! $orderStatus || strtoupper((string) $orderStatus['status']) !== 'COMPLETED') {
            return redirect()
                ->route('payment.show', $bookingId)
                ->withErrors(['payment' => 'Thanh toan chua duoc ghi nhan. Vui long xac nhan thanh toan truoc.']);
        }

        $data = $this->payments->paymentPageDataForUser(Auth::user(), (string) $bookingId);

        if (! $data) {
            return $this->missingPaymentRedirect('Don booking khong ton tai hoac khong thuoc tai khoan cua ban.');
        }

        return view('client.payments.success', $data);
    }

    public function failed(Request $request): RedirectResponse
    {
        $bookingId = $request->query('booking_id');

        if ($bookingId) {
            $this->payments->cancelPendingPaymentForUser(Auth::user(), (string) $bookingId);

            return redirect()
                ->route('profile.history-booking.index')
                ->withErrors(['payment' => 'Thanh toan da huy. Phong da duoc mo lai neu booking chua hoan tat.']);
        }

        return $this->missingPaymentRedirect('Thanh toan chua thanh cong.');
    }

    public function checkStatus(string $bookingId): JsonResponse
    {
        if (! Auth::check()) {
            return response()->json([
                'exists' => false,
                'message' => 'Ban chua dang nhap.',
            ], 401);
        }

        $orderStatus = $this->payments->orderStatusForUser(Auth::user(), $bookingId);

        if (! $orderStatus) {
            return response()->json([
                'exists' => false,
                'message' => 'Hoa don khong ton tai.',
            ], 404);
        }

        return response()->json([
            'exists' => true,
            ...$orderStatus,
        ]);
    }

    private function paymentViewForBooking(string $bookingId): View|RedirectResponse
    {
        $data = $this->payments->paymentPageDataForUser(Auth::user(), $bookingId);

        if (! $data) {
            return $this->missingPaymentRedirect('Don booking khong ton tai hoac khong thuoc tai khoan cua ban.');
        }

        return view('client.payments.create', $data);
    }

    private function missingPaymentRedirect(string $message): RedirectResponse
    {
        return redirect()
            ->route('profile.history-booking.index')
            ->withErrors(['payment' => $message]);
    }
}
