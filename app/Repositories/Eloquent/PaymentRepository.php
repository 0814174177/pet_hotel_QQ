<?php

namespace App\Repositories\Eloquent;

use App\Models\Booking;
use App\Models\BookingCouponLog;
use App\Models\BookingRoom;
use App\Models\BookingServicePet;
use App\Models\Coupon;
use App\Models\Order;
use App\Models\OrderDetail;
use App\Models\Payment;
use App\Models\User;
use App\Repositories\Contracts\PaymentRepositoryInterface;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PaymentRepository implements PaymentRepositoryInterface
{
    public function paymentPageDataForUser(?User $user, string $bookingId): ?array
    {
        $booking = $this->customerBooking($user, $bookingId);

        if (! $booking) {
            return null;
        }

        return [
            'payment' => ($order = $this->existingOrderForBooking($booking))
                ? $this->paymentViewData($order)
                : $this->bookingPaymentPreviewData($booking),
        ];
    }

    public function confirmBookingPaymentForUser(
        ?User $user,
        string $bookingId,
        string $paymentMethod,
        ?string $couponCode = null,
        array $contact = []
    ): ?Booking {
        $booking = $this->customerBooking($user, $bookingId);

        if (! $booking) {
            return null;
        }

        $order = $this->ensureOrderForBooking($booking, $user);
        $databasePaymentMethod = $this->databasePaymentMethod($paymentMethod);
        $couponCode = $this->normalizeCouponCode($couponCode);
        $contact = $this->normalizeContact($contact);

        return DB::transaction(function () use ($booking, $order, $databasePaymentMethod, $couponCode, $contact, $user): Booking {
            $order = Order::where('order_id', $order->order_id)
                ->lockForUpdate()
                ->firstOrFail();

            if (in_array($order->status, ['COMPLETED', 'PAID', 'CANCELLED', 'REFUNDED'], true)) {
                return $booking->fresh();
            }

            $coupon = $couponCode ? $this->validCouponForOrder($couponCode, $order) : null;
            $discountAmount = $coupon ? $this->discountAmountFor($coupon, (float) $order->subtotal) : 0.0;

            $order->update([
                'coupon_id' => $coupon?->coupon_id,
                'discount_amount' => $discountAmount,
                'grand_total' => max(0, round((float) $order->subtotal - $discountAmount, 2)),
                'payment_method' => $databasePaymentMethod,
                'customer_name' => $contact['customer_name'],
                'customer_phone' => $contact['customer_phone'],
                'customer_email' => $contact['customer_email'],
                'status' => 'COMPLETED',
                'paid_at' => now(),
            ]);

            if ((float) $order->grand_total > 0) {
                Payment::updateOrCreate(
                    ['order_id' => $order->order_id],
                    [
                        'payment_method' => $databasePaymentMethod,
                        'provider' => null,
                        'amount' => $order->grand_total,
                        'status' => 'SUCCESS',
                        'paid_at' => $order->paid_at,
                        'note' => 'Thanh toan booking qua web.',
                    ]
                );
            }

            if ($coupon) {
                $coupon->increment('used_count');

                BookingCouponLog::create([
                    'booking_id' => $booking->booking_id,
                    'coupon_id' => $coupon->coupon_id,
                    'applied_at' => now(),
                    'notes' => 'Ap dung ma giam gia '.$coupon->coupon_code.' khi thanh toan.',
                ]);
            }

            if (in_array($booking->status, ['PENDING', 'CONFIRMED'], true)) {
                $booking->update([
                    'status' => 'CONFIRMED',
                    'total_amount' => $order->grand_total,
                ]);
            }

            return $booking->fresh('orders');
        });
    }

    public function previewCouponForUser(?User $user, string $bookingId, ?string $couponCode = null): ?array
    {
        $booking = $this->customerBooking($user, $bookingId);

        if (! $booking) {
            return null;
        }

        $order = $this->ensureOrderForBooking($booking, $user);
        $couponCode = $this->normalizeCouponCode($couponCode);

        if (! $couponCode) {
            return $this->couponPreviewData($order, null, 0.0, 'Nhap ma giam gia de ap dung.');
        }

        $coupon = $this->validCouponForOrder($couponCode, $order, false);
        $discountAmount = $this->discountAmountFor($coupon, (float) $order->subtotal);

        return $this->couponPreviewData($order, $coupon, $discountAmount, 'Ma giam gia da duoc ap dung.');
    }

    public function orderStatusForUser(?User $user, string $bookingId): ?array
    {
        $customer = $user?->customer;

        if (! $customer) {
            return null;
        }

        $order = Order::query()
            ->select(['order_id', 'booking_id', 'customer_id', 'status', 'grand_total', 'updated_at'])
            ->where('booking_id', $bookingId)
            ->where('customer_id', $customer->customer_id)
            ->first();

        if (! $order) {
            return null;
        }

        return [
            'status' => $order->status,
            'grand_total' => (float) $order->grand_total,
            'updated_at' => $order->updated_at?->timestamp,
        ];
    }

    public function cancelPendingPaymentForUser(?User $user, string $bookingId): ?Booking
    {
        $booking = $this->customerBooking($user, $bookingId);

        if (! $booking) {
            return null;
        }

        return DB::transaction(function () use ($booking): Booking {
            $booking = Booking::with($this->bookingRelations())
                ->where('booking_id', $booking->booking_id)
                ->lockForUpdate()
                ->firstOrFail();

            Order::where('booking_id', $booking->booking_id)
                ->whereIn('status', ['PENDING', 'PROCESSING'])
                ->update(['status' => 'CANCELLED']);

            if (in_array($booking->status, ['PENDING', 'CONFIRMED'], true)) {
                $booking->update(['status' => 'CANCELLED']);
            }

            return $booking->fresh($this->bookingRelations());
        });
    }

    private function customerBooking(?User $user, string $bookingId): ?Booking
    {
        $customer = $user?->customer;

        if (! $customer) {
            return null;
        }

        return Booking::with($this->bookingRelations())
            ->where('booking_id', $bookingId)
            ->where('customer_id', $customer->customer_id)
            ->first();
    }

    private function existingOrderForBooking(Booking $booking): ?Order
    {
        return Order::where('booking_id', $booking->booking_id)
            ->first()
            ?->load($this->orderRelations());
    }

    private function ensureOrderForBooking(Booking $booking, ?User $user): Order
    {
        try {
            return DB::transaction(function () use ($booking, $user): Order {
                $booking = Booking::with($this->bookingRelations())
                    ->where('booking_id', $booking->booking_id)
                    ->lockForUpdate()
                    ->firstOrFail();

                $existingOrder = Order::where('booking_id', $booking->booking_id)
                    ->lockForUpdate()
                    ->first();

                if ($existingOrder) {
                    if (! $existingOrder->details()->exists()) {
                        $subtotal = $this->createOrderDetails($existingOrder, $booking);

                        $existingOrder->update([
                            'subtotal' => $subtotal,
                            'grand_total' => $subtotal - (float) $existingOrder->discount_amount,
                        ]);

                        $booking->update(['total_amount' => $subtotal]);
                    }

                    return $existingOrder->load($this->orderRelations());
                }

                $order = Order::create([
                    'customer_id' => $booking->customer_id,
                    'branch_id' => $booking->branch_id,
                'booking_id' => $booking->booking_id,
                'created_by_user_id' => $user?->id,
                'customer_name' => $booking->customer?->full_name ?: $user?->name,
                'customer_phone' => $booking->customer?->phone,
                'customer_email' => $user?->email,
                'payment_method' => 'CASH',
                'status' => 'PENDING',
                'subtotal' => 0,
                    'discount_amount' => 0,
                    'grand_total' => 0,
                ]);

                $subtotal = $this->createOrderDetails($order, $booking);

                $order->update([
                    'subtotal' => $subtotal,
                    'grand_total' => $subtotal - (float) $order->discount_amount,
                ]);

                $booking->update(['total_amount' => $subtotal]);

                return $order->load($this->orderRelations());
            });
        } catch (QueryException $e) {
            if ($e->getCode() !== '23000') {
                throw $e;
            }

            $existingOrder = $this->existingOrderForBooking($booking);

            if (! $existingOrder) {
                throw $e;
            }

            return $existingOrder;
        }
    }

    private function createOrderDetails(Order $order, Booking $booking): float
    {
        $subtotal = 0.0;
        $nights = $this->bookingNights($booking);

        foreach ($booking->bookingRooms as $bookingRoom) {
            $subtotal += $this->createRoomDetail($order, $bookingRoom, $nights);
        }

        foreach ($booking->bookingServicePets as $bookingServicePet) {
            $subtotal += $this->createServiceDetail($order, $bookingServicePet);
        }

        return $subtotal;
    }

    private function createRoomDetail(Order $order, BookingRoom $bookingRoom, int $nights): float
    {
        $room = $bookingRoom->room;
        $typeRoom = $room?->typeRoom;
        $unitPrice = (float) ($typeRoom?->base_price_per_day ?? 0);
        $lineTotal = round($unitPrice * $nights, 2);

        OrderDetail::create([
            'order_id' => $order->order_id,
            'booking_room_id' => $bookingRoom->booking_room_id,
            'booking_service_pet_id' => null,
            'title' => sprintf(
                'Phòng %s (%d đêm)',
                $typeRoom?->type_name ?: $room?->room_number ?: 'đã đặt',
                $nights
            ),
            'quantity' => $nights,
            'unit_price' => $unitPrice,
            'line_total' => $lineTotal,
        ]);

        return $lineTotal;
    }

    private function createServiceDetail(Order $order, BookingServicePet $bookingServicePet): float
    {
        $service = $bookingServicePet->service;
        $petName = $bookingServicePet->pet?->pet_name;
        $unitPrice = (float) ($service?->base_price ?? 0);

        OrderDetail::create([
            'order_id' => $order->order_id,
            'booking_room_id' => null,
            'booking_service_pet_id' => $bookingServicePet->booking_service_pet_id,
            'title' => trim(($service?->service_name ?: 'Dịch vụ') . ($petName ? ' - '.$petName : '')),
            'quantity' => 1,
            'unit_price' => $unitPrice,
            'line_total' => $unitPrice,
        ]);

        return $unitPrice;
    }

    private function paymentViewData(Order $order): array
    {
        $booking = $order->booking;
        $details = $order->details->map(fn (OrderDetail $detail): array => [
            'title' => $detail->title,
            'quantity' => (int) $detail->quantity,
            'unit_price' => (float) $detail->unit_price,
            'line_total' => (float) $detail->line_total,
            'is_room' => filled($detail->booking_room_id),
        ]);

        return [
            'booking_id' => $booking?->booking_id,
            'order_id' => $order->order_id,
            'order_status' => $order->status,
            'branch_name' => $order->branch?->branch_name ?: 'Chi nhánh đang cập nhật',
            'room_names' => $this->roomNames($booking),
            'checkin' => $this->formatDate($booking?->checkin_expected_at),
            'checkout' => $this->formatDate($booking?->checkout_expected_at),
            'nights' => $booking ? $this->bookingNights($booking) : 0,
            'customer_name' => $order->customer_name ?: $order->customer?->full_name ?: $order->customer?->user?->name ?: '',
            'customer_phone' => $order->customer_phone ?: $order->customer?->phone ?: '',
            'customer_email' => $order->customer_email ?: $order->customer?->user?->email ?: '',
            'details' => $details->values()->all(),
            'room_total' => $details->where('is_room', true)->sum('line_total'),
            'service_total' => $details->where('is_room', false)->sum('line_total'),
            'discount_amount' => (float) $order->discount_amount,
            'grand_total' => (float) $order->grand_total,
            'server_grand_total' => (float) $order->grand_total,
            'coupon_code' => $order->coupon?->coupon_code,
            'payment_method' => $this->paymentMethodLabel((string) $order->payment_method),
            'process_url' => route('payment.process', $booking?->booking_id),
            'apply_coupon_url' => route('payment.apply_coupon', $booking?->booking_id),
            'check_status_url' => route('payment.check_status', $booking?->booking_id),
            'history_url' => route('profile.history-booking.index'),
            'booking_url' => route('booking.show', $booking?->booking_id),
            'home_url' => route('home'),
        ];
    }

    private function bookingPaymentPreviewData(Booking $booking): array
    {
        $nights = $this->bookingNights($booking);
        $details = collect();

        foreach ($booking->bookingRooms as $bookingRoom) {
            $room = $bookingRoom->room;
            $typeRoom = $room?->typeRoom;
            $unitPrice = (float) ($typeRoom?->base_price_per_day ?? 0);
            $lineTotal = round($unitPrice * $nights, 2);

            $details->push([
                'title' => sprintf('Phong %s (%d dem)', $typeRoom?->type_name ?: $room?->room_number ?: 'da dat', $nights),
                'quantity' => $nights,
                'unit_price' => $unitPrice,
                'line_total' => $lineTotal,
                'is_room' => true,
            ]);
        }

        foreach ($booking->bookingServicePets as $bookingServicePet) {
            $service = $bookingServicePet->service;
            $petName = $bookingServicePet->pet?->pet_name;
            $unitPrice = (float) ($service?->base_price ?? 0);

            $details->push([
                'title' => trim(($service?->service_name ?: 'Dich vu') . ($petName ? ' - '.$petName : '')),
                'quantity' => 1,
                'unit_price' => $unitPrice,
                'line_total' => $unitPrice,
                'is_room' => false,
            ]);
        }

        $roomTotal = (float) $details->where('is_room', true)->sum('line_total');
        $serviceTotal = (float) $details->where('is_room', false)->sum('line_total');
        $grandTotal = $roomTotal + $serviceTotal;

        return [
            'booking_id' => $booking->booking_id,
            'order_id' => null,
            'order_status' => 'DRAFT',
            'branch_name' => $booking->branch?->branch_name ?: 'Chi nhanh dang cap nhat',
            'room_names' => $this->roomNames($booking),
            'checkin' => $this->formatDate($booking->checkin_expected_at),
            'checkout' => $this->formatDate($booking->checkout_expected_at),
            'nights' => $nights,
            'customer_name' => $booking->customer?->full_name ?: $booking->customer?->user?->name ?: '',
            'customer_phone' => $booking->customer?->phone ?: '',
            'customer_email' => $booking->customer?->user?->email ?: '',
            'details' => $details->values()->all(),
            'room_total' => $roomTotal,
            'service_total' => $serviceTotal,
            'discount_amount' => 0.0,
            'grand_total' => $grandTotal,
            'server_grand_total' => $grandTotal,
            'coupon_code' => null,
            'payment_method' => $this->paymentMethodLabel('CASH'),
            'process_url' => route('payment.process', $booking->booking_id),
            'apply_coupon_url' => route('payment.apply_coupon', $booking->booking_id),
            'check_status_url' => route('payment.check_status', $booking->booking_id),
            'history_url' => route('profile.history-booking.index'),
            'booking_url' => route('booking.show', $booking->booking_id),
            'home_url' => route('home'),
        ];
    }

    private function normalizeCouponCode(?string $couponCode): ?string
    {
        $couponCode = trim((string) $couponCode);

        return $couponCode === '' ? null : strtoupper($couponCode);
    }

    private function normalizeContact(array $contact): array
    {
        return [
            'customer_name' => trim((string) ($contact['customer_name'] ?? '')),
            'customer_phone' => trim((string) ($contact['customer_phone'] ?? '')),
            'customer_email' => trim((string) ($contact['customer_email'] ?? '')),
        ];
    }

    private function fillMissingCustomerContact(?User $user, array $contact): void
    {
        if (! $user) {
            return;
        }

        if (blank($user->name) && filled($contact['customer_name'])) {
            $user->update(['name' => $contact['customer_name']]);
        }

        if (blank($user->email) && filled($contact['customer_email'])) {
            $user->update(['email' => $contact['customer_email']]);
        }

        $customer = $user->customer;

        if (! $customer) {
            return;
        }

        $updates = [];

        if (blank($customer->full_name) && filled($contact['customer_name'])) {
            $updates['full_name'] = $contact['customer_name'];
        }

        if (blank($customer->phone) && filled($contact['customer_phone'])) {
            $updates['phone'] = $contact['customer_phone'];
        }

        if ($updates !== []) {
            $customer->update($updates);
        }
    }

    private function validCouponForOrder(string $couponCode, Order $order, bool $lock = true): Coupon
    {
        $now = now();
        $query = Coupon::query()
            ->whereRaw('UPPER(coupon_code) = ?', [$couponCode]);

        if ($lock) {
            $query->lockForUpdate();
        }

        $coupon = $query->first();

        if (! $coupon) {
            throw ValidationException::withMessages([
                'coupon_code' => 'Ma giam gia khong ton tai.',
            ]);
        }

        if (! $coupon->is_active) {
            throw ValidationException::withMessages([
                'coupon_code' => 'Ma giam gia hien khong hoat dong.',
            ]);
        }

        if (Carbon::parse($coupon->effective_from)->gt($now) || Carbon::parse($coupon->expired_at)->lte($now)) {
            throw ValidationException::withMessages([
                'coupon_code' => 'Ma giam gia da het han hoac chua den thoi gian su dung.',
            ]);
        }

        if ($coupon->max_uses !== null && (int) $coupon->used_count >= (int) $coupon->max_uses) {
            throw ValidationException::withMessages([
                'coupon_code' => 'Ma giam gia da het luot su dung.',
            ]);
        }

        if ((float) $order->subtotal < (float) $coupon->min_order_value) {
            throw ValidationException::withMessages([
                'coupon_code' => 'Don hang chua dat gia tri toi thieu de su dung ma giam gia nay.',
            ]);
        }

        return $coupon;
    }

    private function couponPreviewData(Order $order, ?Coupon $coupon, float $discountAmount, string $message): array
    {
        return [
            'coupon_code' => $coupon?->coupon_code,
            'discount_amount' => $discountAmount,
            'grand_total' => max(0, round((float) $order->subtotal - $discountAmount, 2)),
            'subtotal' => (float) $order->subtotal,
            'message' => $message,
        ];
    }

    private function discountAmountFor(Coupon $coupon, float $subtotal): float
    {
        $discountAmount = strtoupper((string) $coupon->discount_type) === 'PERCENT'
            ? $subtotal * ((float) $coupon->discount_value / 100)
            : (float) $coupon->discount_value;

        if ($coupon->max_discount !== null) {
            $discountAmount = min($discountAmount, (float) $coupon->max_discount);
        }

        return round(min($discountAmount, $subtotal), 2);
    }

    private function roomNames(?Booking $booking): string
    {
        if (! $booking) {
            return 'Phòng đang cập nhật';
        }

        $names = $booking->bookingRooms
            ->map(fn (BookingRoom $bookingRoom) => $bookingRoom->room?->typeRoom?->type_name)
            ->filter()
            ->unique()
            ->values();

        return $names->isNotEmpty() ? $names->implode(', ') : 'Phòng đang cập nhật';
    }

    private function bookingNights(Booking $booking): int
    {
        return max(1, (int) Carbon::parse($booking->checkin_expected_at)
            ->startOfDay()
            ->diffInDays(Carbon::parse($booking->checkout_expected_at)->startOfDay()));
    }

    private function formatDate(mixed $date): string
    {
        return $date ? Carbon::parse($date)->format('d/m/Y') : 'Đang cập nhật';
    }

    private function databasePaymentMethod(string $paymentMethod): string
    {
        return match ($paymentMethod) {
            'bank' => 'BANK_TRANSFER',
            'wallet' => 'MOMO',
            default => 'CASH',
        };
    }

    private function paymentMethodLabel(string $paymentMethod): string
    {
        return match (strtoupper($paymentMethod)) {
            'BANK_TRANSFER' => 'Chuyển khoản ngân hàng',
            'MOMO' => 'Ví điện tử',
            default => 'Tiền mặt khi nhận phòng',
        };
    }

    private function bookingRelations(): array
    {
        return [
            'customer.user',
            'branch',
            'bookingRooms.room.typeRoom',
            'bookingServicePets.service',
            'bookingServicePets.pet',
            'orders.details',
        ];
    }

    private function orderRelations(): array
    {
        return [
            'customer.user',
            'branch',
            'coupon',
            'payment',
            'booking.bookingRooms.room.typeRoom',
            'details.bookingRoom.room.typeRoom',
            'details.bookingServicePet.service',
            'details.bookingServicePet.pet',
        ];
    }
}