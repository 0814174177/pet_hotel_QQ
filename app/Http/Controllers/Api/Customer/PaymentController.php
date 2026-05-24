<?php

namespace App\Http\Controllers\Api\Customer;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Payment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class PaymentController extends Controller
{
    public function show(Request $request, string $bookingId): JsonResponse
    {
        $customer = $this->currentCustomer($request);
        $order = $this->findOrder($customer, $bookingId);

        return response()->json([
            'data' => [
                'order' => $order,
                'payment' => $order->payment,
            ],
        ]);
    }

    public function process(Request $request, string $bookingId): JsonResponse
    {
        $customer = $this->currentCustomer($request);
        $order = $this->findOrder($customer, $bookingId);

        $validated = $request->validate([
            'payment_method' => ['required', Rule::in(['CASH', 'BANK_TRANSFER', 'CARD', 'EWALLET'])],
            'provider' => ['nullable', 'string', 'max:50'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'note' => ['nullable', 'string', 'max:1000'],
        ]);

        $payment = DB::transaction(function () use ($order, $validated): Payment {
            $payment = Payment::updateOrCreate(
                ['order_id' => $order->order_id],
                [
                    'payment_method' => $validated['payment_method'],
                    'provider' => $validated['provider'] ?? null,
                    'amount' => $validated['amount'],
                    'status' => 'SUCCESS',
                    'paid_at' => now(),
                    'note' => $validated['note'] ?? null,
                ]
            );

            $order->update([
                'status' => 'PAID',
                'paid_at' => now(),
            ]);

            return $payment->fresh('order');
        });

        return response()->json([
            'message' => 'Thanh toan thanh cong.',
            'data' => $payment,
        ]);
    }

    public function success(Request $request): JsonResponse
    {
        return response()->json([
            'message' => 'Thanh toan thanh cong.',
            'booking_id' => $request->query('booking_id'),
        ]);
    }

    public function failed(Request $request): JsonResponse
    {
        $customer = $this->currentCustomer($request);
        $bookingId = $request->query('booking_id');

        if ($bookingId) {
            DB::transaction(function () use ($customer, $bookingId): void {
                $booking = $customer->bookings()
                    ->where('booking_id', $bookingId)
                    ->lockForUpdate()
                    ->first();

                if (! $booking) {
                    return;
                }

                Order::where('booking_id', $booking->booking_id)
                    ->where('customer_id', $customer->customer_id)
                    ->whereIn('status', ['PENDING', 'PROCESSING'])
                    ->update(['status' => 'CANCELLED']);

                if (in_array($booking->status, ['PENDING', 'CONFIRMED'], true)) {
                    $booking->update(['status' => 'CANCELLED']);
                }
            });
        }

        return response()->json([
            'message' => 'Thanh toan da huy. Phong da duoc mo lai neu booking chua hoan tat.',
            'booking_id' => $bookingId,
        ], 422);
    }

    private function currentCustomer(Request $request): Customer
    {
        $user = $request->user();

        abort_if(! $user, 401, 'Ban chua dang nhap.');

        return Customer::where('user_id', $user->id)->firstOrFail();
    }

    private function findOrder(Customer $customer, string $bookingId): Order
    {
        return Order::with(['booking', 'orderDetails', 'payment'])
            ->where('customer_id', $customer->customer_id)
            ->where('booking_id', $bookingId)
            ->firstOrFail();
    }

}
