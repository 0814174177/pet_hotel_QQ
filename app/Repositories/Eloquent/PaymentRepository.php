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

        $order = $this->ensureOrderForBooking($booking, $user);

        return [
            'order' => $order,
            'payment' => $this->paymentViewData($order),
        ];
    }

    public function confirmBookingPaymentForUser(
        ?User $user,
        string $bookingId,
        string $paymentMethod,
        ?string $couponCode = null
    ): ?Booking {
        $booking = $this->customerBooking($user, $bookingId);

        if (! $booking) {
            return null;
        }

        $order = $this->ensureOrderForBooking($booking, $user);
        $databasePaymentMethod = $this->databasePaymentMethod($paymentMethod);
        $couponCode = $this->normalizeCouponCode($couponCode);

        return DB::transaction(function () use ($booking, $order, $databasePaymentMethod, $couponCode): Booking {
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

    private function ensureOrderForBooking(Booking $booking, ?User $user): Order
    {
        return DB::transaction(function () use ($booking, $user): Order {
            $existingOrder = Order::where('booking_id', $booking->booking_id)
                ->lockForUpdate()
                ->first();

            if ($existingOrder) {
                if (! $existingOrder->details()->exists()) {
                    $booking = Booking::with($this->bookingRelations())
                        ->where('booking_id', $booking->booking_id)
                        ->lockForUpdate()
                        ->firstOrFail();

                    $subtotal = $this->createOrderDetails($existingOrder, $booking);

                    $existingOrder->update([
                        'subtotal' => $subtotal,
                        'grand_total' => $subtotal - (float) $existingOrder->discount_amount,
                    ]);

                    $booking->update(['total_amount' => $subtotal]);
                }

                return $existingOrder->load($this->orderRelations());
            }

            $booking = Booking::with($this->bookingRelations())
                ->where('booking_id', $booking->booking_id)
                ->lockForUpdate()
                ->firstOrFail();

            $order = Order::create([
                'customer_id' => $booking->customer_id,
                'branch_id' => $booking->branch_id,
                'booking_id' => $booking->booking_id,
                'created_by_user_id' => $user?->id,
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
            'customer_name' => $order->customer?->full_name ?: $order->customer?->user?->name ?: 'Khách hàng',
            'customer_phone' => $order->customer?->phone ?: 'Đang cập nhật',
            'customer_email' => $order->customer?->user?->email ?: 'Đang cập nhật',
            'branch_name' => $order->branch?->branch_name ?: 'Chi nhánh đang cập nhật',
            'room_names' => $this->roomNames($booking),
            'checkin' => $this->formatDate($booking?->checkin_expected_at),
            'checkout' => $this->formatDate($booking?->checkout_expected_at),
            'details' => $details->values()->all(),
            'room_total' => $details->where('is_room', true)->sum('line_total'),
            'service_total' => $details->where('is_room', false)->sum('line_total'),
            'discount_amount' => (float) $order->discount_amount,
            'grand_total' => (float) $order->grand_total,
            'coupon_code' => $order->coupon?->coupon_code,
            'process_url' => route('payment.process', $booking?->booking_id),
        ];
    }

    private function normalizeCouponCode(?string $couponCode): ?string
    {
        $couponCode = trim((string) $couponCode);

        return $couponCode === '' ? null : strtoupper($couponCode);
    }

    private function validCouponForOrder(string $couponCode, Order $order): Coupon
    {
        $now = now();
        $coupon = Coupon::query()
            ->whereRaw('UPPER(coupon_code) = ?', [$couponCode])
            ->lockForUpdate()
            ->first();

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
