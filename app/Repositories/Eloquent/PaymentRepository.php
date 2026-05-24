<?php

/*
|--------------------------------------------------------------------------
| PaymentRepository
|--------------------------------------------------------------------------
|
| File này xử lý phần thanh toán cho booking của khách hàng.
|
| Lưu ý quan trọng về bản được chú thích:
| - Không thay đổi logic xử lý.
| - Không đổi tên class, namespace, method, tham số, return type, query, route,
|   status, payment method, relationship hay bất kỳ giá trị nào đang được dùng.
| - Chỉ thêm comment/docblock để giải thích rõ từng bước code đang làm gì.
|
| Vai trò chính của repository này:
| 1. Lấy dữ liệu cần thiết để hiển thị trang thanh toán.
| 2. Tạo hoặc lấy lại Order tương ứng với Booking.
| 3. Xác nhận thanh toán và cập nhật trạng thái Order/Booking.
| 4. Kiểm tra, preview và áp dụng coupon.
| 5. Hủy thanh toán còn pending/processing.
| 6. Chuẩn hóa dữ liệu trả về cho giao diện thanh toán.
|
| Về mặt kiến trúc Laravel:
| - Class này nằm ở tầng Repository, nghĩa là nó gom logic truy vấn database
|   và xử lý nghiệp vụ thanh toán vào một nơi riêng.
| - Controller chỉ cần gọi các method public của repository này, thay vì phải
|   tự viết toàn bộ query, transaction, validation coupon và mapping dữ liệu.
| - Các method private phía dưới là helper nội bộ, dùng để chia nhỏ logic cho dễ
|   đọc, dễ test và tránh lặp code.
*/

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

/*
|--------------------------------------------------------------------------
| Ghi chú về các model / facade được dùng trong file
|--------------------------------------------------------------------------
|
| Booking:
|   Đại diện cho lượt đặt phòng/dịch vụ của khách hàng.
|
| BookingRoom:
|   Đại diện cho từng phòng nằm trong booking.
|
| BookingServicePet:
|   Đại diện cho từng dịch vụ thú cưng được đặt kèm trong booking.
|
| Order:
|   Đại diện cho đơn thanh toán được tạo từ booking.
|
| OrderDetail:
|   Đại diện cho từng dòng chi tiết trong order, ví dụ tiền phòng hoặc tiền dịch vụ.
|
| Coupon:
|   Đại diện cho mã giảm giá.
|
| BookingCouponLog:
|   Ghi log việc áp dụng coupon vào booking khi thanh toán thành công.
|
| DB:
|   Dùng transaction và lockForUpdate để đảm bảo dữ liệu thanh toán không bị sai
|   khi có nhiều request xử lý cùng lúc.
|
| Carbon:
|   Dùng để parse ngày, tính số đêm và kiểm tra thời gian hiệu lực coupon.
|
| ValidationException:
|   Dùng để trả lỗi validation khi coupon không hợp lệ.
*/

/**
 * Repository xử lý nghiệp vụ thanh toán cho booking của khách hàng.
 *
 * Class này implement PaymentRepositoryInterface, nghĩa là nó phải cung cấp
 * đúng các method public mà interface yêu cầu. Những method public thường được
 * controller/service gọi trực tiếp.
 *
 * Các nghiệp vụ chính trong class:
 * - Kiểm tra booking có thật sự thuộc về customer đang đăng nhập không.
 * - Lấy hoặc tạo order từ booking.
 * - Sinh order details từ phòng và dịch vụ trong booking.
 * - Tính subtotal, discount_amount và grand_total.
 * - Preview coupon trước khi người dùng thanh toán.
 * - Xác nhận thanh toán và cập nhật trạng thái order/booking.
 * - Hủy các payment đang chờ xử lý.
 * - Chuẩn bị dữ liệu trả về cho frontend/payment page.
 *
 * Bảo vệ dữ liệu:
 * - Các thao tác quan trọng dùng DB::transaction để đảm bảo tính toàn vẹn.
 * - lockForUpdate được dùng ở các điểm có khả năng bị race condition, ví dụ
 *   cùng một booking/order bị xử lý bởi nhiều request đồng thời.
 */
class PaymentRepository implements PaymentRepositoryInterface
{
    /**
     * Lấy dữ liệu để hiển thị trang thanh toán cho user hiện tại.
     *
     * Input:
     * - $user: user đang đăng nhập, có thể null.
     * - $bookingId: mã booking cần thanh toán.
     *
     * Flow xử lý:
     * 1. Tìm booking theo booking_id và customer của user.
     * 2. Nếu booking không tồn tại hoặc không thuộc về user, trả về null.
     * 3. Nếu booking đã có order, trả dữ liệu payment dựa trên order hiện có.
     * 4. Nếu booking chưa có order, trả dữ liệu preview dựa trực tiếp trên booking.
     *
     * Return:
     * - null nếu user không hợp lệ hoặc booking không thuộc user.
     * - array chứa key 'payment' để view/frontend render trang thanh toán.
     *
     * Ghi chú:
     * - Method này chỉ chuẩn bị dữ liệu hiển thị.
     * - Không tạo order mới.
     * - Không thay đổi trạng thái booking/order.
     */
    public function paymentPageDataForUser(?User $user, string $bookingId): ?array
    {
        // Kiểm tra booking theo booking_id và customer của user để tránh user thao tác booking của người khác.
        $booking = $this->customerBooking($user, $bookingId);

        // Nếu không tìm thấy booking hợp lệ thì dừng sớm và trả null cho caller xử lý.
        if (! $booking) {
            return null;
        }

        // Nếu order đã tồn tại thì dùng dữ liệu order thật; nếu chưa có order thì chỉ tạo dữ liệu preview từ booking.
        return [
            'payment' => ($order = $this->existingOrderForBooking($booking))
                ? $this->paymentViewData($order)
                : $this->bookingPaymentPreviewData($booking),
        ];
    }

    /**
     * Xác nhận thanh toán cho một booking của user hiện tại.
     *
     * Input:
     * - $user: user đang đăng nhập, có thể null.
     * - $bookingId: booking cần thanh toán.
     * - $paymentMethod: phương thức thanh toán từ phía request/frontend.
     * - $couponCode: mã giảm giá user nhập, có thể null.
     * - $contact: thông tin liên hệ khách hàng gửi kèm khi thanh toán.
     *
     * Flow xử lý tổng quát:
     * 1. Kiểm tra booking có thuộc customer của user không.
     * 2. Tạo order nếu booking chưa có order, hoặc lấy order hiện có.
     * 3. Chuẩn hóa payment method về dạng lưu trong database.
     * 4. Chuẩn hóa coupon code và thông tin liên hệ.
     * 5. Mở transaction để cập nhật order/booking an toàn.
     * 6. Lock order để tránh 2 request cùng thanh toán một order.
     * 7. Nếu order đã ở trạng thái kết thúc, trả booking mới nhất.
     * 8. Validate coupon nếu có.
     * 9. Tính discount và grand total.
     * 10. Cập nhật order thành COMPLETED.
     * 11. Bổ sung thông tin customer còn thiếu nếu cần.
     * 12. Ghi log coupon và tăng used_count nếu dùng coupon.
     * 13. Cập nhật booking thành CONFIRMED nếu trạng thái hiện tại cho phép.
     *
     * Return:
     * - null nếu booking không hợp lệ/không thuộc user.
     * - Booking mới nhất sau khi thanh toán thành công hoặc sau khi phát hiện
     *   order đã ở trạng thái kết thúc.
     */
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

        // Đảm bảo có order trước khi thanh toán vì discount/grand total cần dựa trên subtotal của order.
        $order = $this->ensureOrderForBooking($booking, $user);

        // Chuẩn hóa dữ liệu đầu vào trước khi ghi database.
        $databasePaymentMethod = $this->databasePaymentMethod($paymentMethod);
        $couponCode = $this->normalizeCouponCode($couponCode);
        $contact = $this->normalizeContact($contact);

        // Toàn bộ bước xác nhận thanh toán nằm trong transaction để tránh order/booking cập nhật nửa chừng.
        return DB::transaction(function () use ($booking, $order, $databasePaymentMethod, $couponCode, $contact, $user): Booking {
            // Lock order hiện tại trong transaction để tránh hai request cùng lúc cùng hoàn tất một order.
            $order = Order::where('order_id', $order->order_id)
                ->lockForUpdate()
                ->firstOrFail();

            if (in_array($order->status, ['COMPLETED', 'PAID', 'CANCELLED', 'REFUNDED'], true)) {
                return $booking->fresh();
            }

            // Chỉ validate và tính giảm giá khi user thật sự nhập coupon code.
            $coupon = $couponCode ? $this->validCouponForOrder($couponCode, $order) : null;
            $discountAmount = $coupon ? $this->discountAmountFor($coupon, (float) $order->subtotal) : 0.0;

            // Cập nhật order thành đã thanh toán, đồng thời lưu coupon, discount, grand total và thông tin liên hệ.
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

            // Chỉ cập nhật booking nếu booking đang ở trạng thái được phép xác nhận.
            if (in_array($booking->status, ['PENDING', 'CONFIRMED'], true)) {
                $booking->update([
                    'status' => 'CONFIRMED',
                    'total_amount' => $order->grand_total,
                ]);
            }

            return $booking->fresh('orders');
        });
    }

    /**
     * Preview mã giảm giá trước khi user xác nhận thanh toán.
     *
     * Method này giúp frontend hiển thị trước:
     * - coupon có hợp lệ không,
     * - giảm được bao nhiêu tiền,
     * - grand_total mới sau khi áp dụng coupon là bao nhiêu.
     *
     * Flow xử lý:
     * 1. Kiểm tra booking thuộc customer của user.
     * 2. Đảm bảo booking có order để có subtotal chính xác.
     * 3. Chuẩn hóa coupon code.
     * 4. Nếu user chưa nhập coupon, trả message yêu cầu nhập mã.
     * 5. Nếu có coupon, validate coupon nhưng không lock bản ghi coupon.
     * 6. Tính discount và trả dữ liệu preview.
     *
     * Ghi chú:
     * - Method này không tăng used_count của coupon.
     * - Method này không ghi BookingCouponLog.
     * - Method này không đánh dấu order là COMPLETED.
     */
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

    /**
     * Lấy trạng thái order của booking cho user hiện tại.
     *
     * Method này thường dùng cho frontend polling/check trạng thái thanh toán.
     *
     * Flow xử lý:
     * 1. Lấy customer từ user.
     * 2. Nếu user không có customer, trả null.
     * 3. Tìm order theo booking_id và customer_id.
     * 4. Nếu không có order, trả null.
     * 5. Trả về status, grand_total và timestamp updated_at.
     *
     * Ghi chú:
     * - Query chỉ select một số cột cần thiết để nhẹ hơn.
     * - Không load relationship vì chỉ cần trạng thái thanh toán.
     */
    public function orderStatusForUser(?User $user, string $bookingId): ?array
    {
        // Dùng null-safe operator vì user có thể null hoặc user chưa có customer profile.
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

    /**
     * Hủy payment còn đang chờ xử lý của một booking.
     *
     * Flow xử lý:
     * 1. Kiểm tra booking có thuộc customer của user không.
     * 2. Mở transaction.
     * 3. Lock booking để tránh cập nhật song song.
     * 4. Đổi các order thuộc booking có status PENDING/PROCESSING sang CANCELLED.
     * 5. Nếu booking còn PENDING/CONFIRMED thì đổi booking sang CANCELLED.
     * 6. Trả về booking mới nhất kèm các relationship cần thiết.
     *
     * Ghi chú:
     * - Method này chỉ hủy payment/order còn chưa hoàn tất.
     * - Không hủy order đã COMPLETED, REFUNDED hoặc các trạng thái khác.
     */
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

    /**
     * Tìm booking thuộc về customer của user hiện tại.
     *
     * Đây là helper bảo mật rất quan trọng:
     * - User chỉ được thao tác với booking của chính customer gắn với user đó.
     * - Nếu user null hoặc user không có customer, trả null.
     * - Nếu booking_id tồn tại nhưng thuộc customer khác, cũng trả null.
     *
     * Method này giúp các public method phía trên tránh lỗi truy cập trái phép
     * bằng cách luôn lọc theo cả booking_id và customer_id.
     */
    private function customerBooking(?User $user, string $bookingId): ?Booking
    {
        $customer = $user?->customer;

        if (! $customer) {
            return null;
        }

        // Eager load các relationship cần thiết để những bước sau không phải query lẻ từng phần.
        return Booking::with($this->bookingRelations())
            ->where('booking_id', $bookingId)
            ->where('customer_id', $customer->customer_id)
            ->first();
    }

    /**
     * Lấy order đã tồn tại của một booking nếu có.
     *
     * Flow:
     * - Query bảng orders theo booking_id.
     * - Nếu tìm thấy order thì load thêm các relationship cần cho payment view.
     * - Nếu không có order thì trả null.
     *
     * Ghi chú:
     * - Method này không tạo order mới.
     * - Chỉ dùng để đọc order hiện có.
     */
    private function existingOrderForBooking(Booking $booking): ?Order
    {
        return Order::where('booking_id', $booking->booking_id)
            ->first()
            ?->load($this->orderRelations());
    }

    /**
     * Đảm bảo booking có một order tương ứng.
     *
     * Đây là một trong những method quan trọng nhất của file.
     *
     * Flow xử lý:
     * 1. Mở transaction để việc tạo order và order details diễn ra an toàn.
     * 2. Lock booking theo booking_id.
     * 3. Tìm order hiện có của booking và lock order đó nếu có.
     * 4. Nếu đã có order:
     *    - Kiểm tra order đã có details chưa.
     *    - Nếu chưa có details, sinh lại details từ booking.
     *    - Cập nhật subtotal, grand_total và total_amount của booking.
     *    - Trả order đã load relationship.
     * 5. Nếu chưa có order:
     *    - Tạo order mới với thông tin customer/branch/booking/user.
     *    - Sinh order details từ phòng và dịch vụ.
     *    - Cập nhật subtotal, grand_total và total_amount.
     *    - Trả order đã load relationship.
     *
     * Xử lý lỗi duplicate:
     * - Nếu gặp QueryException code 23000, thường là lỗi ràng buộc unique/duplicate.
     * - Khi đó method thử lấy lại order hiện có của booking.
     * - Nếu lấy được thì trả order đó, nếu không thì ném lại exception.
     *
     * Mục đích:
     * - Tránh tạo nhiều order cho cùng một booking trong trường hợp request song song.
     * - Đảm bảo order luôn có dữ liệu chi tiết để tính tiền.
     */
    private function ensureOrderForBooking(Booking $booking, ?User $user): Order
    {
        try {
            // Tạo/lấy order được đặt trong transaction vì có thể phát sinh nhiều thao tác ghi liên quan nhau.
            return DB::transaction(function () use ($booking, $user): Order {
                // Lock booking để đảm bảo không có request khác cùng lúc tạo/cập nhật order cho booking này.
                $booking = Booking::with($this->bookingRelations())
                    ->where('booking_id', $booking->booking_id)
                    ->lockForUpdate()
                    ->firstOrFail();

                // Tìm và lock order hiện có nếu đã từng được tạo cho booking này.
                $existingOrder = Order::where('booking_id', $booking->booking_id)
                    ->lockForUpdate()
                    ->first();

                // Nếu order đã tồn tại thì không tạo order mới; chỉ bổ sung details nếu order đang thiếu details.
                if ($existingOrder) {
                    if (! $existingOrder->details()->exists()) {
                        // Sinh details từ booking để có subtotal chính xác cho order cũ đang thiếu chi tiết.
                        $subtotal = $this->createOrderDetails($existingOrder, $booking);

                        $existingOrder->update([
                            'subtotal' => $subtotal,
                            'grand_total' => $subtotal - (float) $existingOrder->discount_amount,
                        ]);

                        $booking->update(['total_amount' => $subtotal]);
                    }

                    return $existingOrder->load($this->orderRelations());
                }

                // Nếu chưa có order, tạo order mới ở trạng thái PENDING với tổng tiền ban đầu bằng 0.
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

                // Sau khi tạo order, tạo từng dòng chi tiết để tính subtotal thật.
                $subtotal = $this->createOrderDetails($order, $booking);

                $order->update([
                    'subtotal' => $subtotal,
                    'grand_total' => $subtotal - (float) $order->discount_amount,
                ]);

                $booking->update(['total_amount' => $subtotal]);

                return $order->load($this->orderRelations());
            });
        } catch (QueryException $e) {
            // Chỉ xử lý riêng lỗi 23000, thường liên quan tới duplicate/constraint; lỗi khác vẫn ném ra ngoài.
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

    /**
     * Tạo toàn bộ order details cho một order dựa trên booking.
     *
     * Order details gồm hai nhóm:
     * 1. Chi tiết tiền phòng, lấy từ bookingRooms.
     * 2. Chi tiết tiền dịch vụ thú cưng, lấy từ bookingServicePets.
     *
     * Flow:
     * - Khởi tạo subtotal bằng 0.
     * - Tính số đêm của booking.
     * - Với mỗi phòng, tạo một dòng order detail và cộng vào subtotal.
     * - Với mỗi dịch vụ, tạo một dòng order detail và cộng vào subtotal.
     * - Trả về tổng subtotal.
     *
     * Ghi chú:
     * - Method này có tạo dữ liệu mới trong bảng order_details.
     * - Method này không tự update order, chỉ trả subtotal cho caller update.
     */
    private function createOrderDetails(Order $order, Booking $booking): float
    {
        // subtotal bắt đầu từ 0 và sẽ cộng dần từng dòng phòng/dịch vụ.
        $subtotal = 0.0;
        $nights = $this->bookingNights($booking);

        // Mỗi booking room tạo một dòng order detail riêng cho phần tiền phòng.
        foreach ($booking->bookingRooms as $bookingRoom) {
            $subtotal += $this->createRoomDetail($order, $bookingRoom, $nights);
        }

        // Mỗi service pet tạo một dòng order detail riêng cho phần tiền dịch vụ.
        foreach ($booking->bookingServicePets as $bookingServicePet) {
            $subtotal += $this->createServiceDetail($order, $bookingServicePet);
        }

        return $subtotal;
    }

    /**
     * Tạo một dòng order detail cho tiền phòng.
     *
     * Công thức đang dùng:
     * - unitPrice = base_price_per_day của loại phòng.
     * - lineTotal = unitPrice * số đêm.
     *
     * Dữ liệu lưu vào OrderDetail:
     * - order_id: order đang được tạo chi tiết.
     * - booking_room_id: liên kết với phòng trong booking.
     * - booking_service_pet_id: null vì đây là dòng tiền phòng.
     * - title: tên hiển thị của dòng tiền phòng.
     * - quantity: số đêm.
     * - unit_price: giá mỗi đêm.
     * - line_total: tổng tiền phòng cho dòng này.
     *
     * Return:
     * - lineTotal để cộng vào subtotal của order.
     */
    private function createRoomDetail(Order $order, BookingRoom $bookingRoom, int $nights): float
    {
        // Lấy room và typeRoom từ relationship đã eager load để xác định tên phòng và đơn giá.
        $room = $bookingRoom->room;
        $typeRoom = $room?->typeRoom;
        // Nếu thiếu giá phòng thì fallback về 0 để tránh lỗi; tổng dòng = đơn giá * số đêm.
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

    /**
     * Tạo một dòng order detail cho dịch vụ thú cưng.
     *
     * Công thức đang dùng:
     * - unitPrice = base_price của service.
     * - quantity cố định là 1.
     * - line_total = unitPrice.
     *
     * Dữ liệu lưu vào OrderDetail:
     * - booking_room_id: null vì đây không phải dòng tiền phòng.
     * - booking_service_pet_id: id của dịch vụ thú cưng trong booking.
     * - title: tên dịch vụ, có thể kèm tên pet.
     *
     * Return:
     * - unitPrice để cộng vào subtotal của order.
     */
    private function createServiceDetail(Order $order, BookingServicePet $bookingServicePet): float
    {
        // Lấy service để biết giá dịch vụ và lấy petName để title hiển thị rõ dịch vụ của pet nào.
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

    /**
     * Chuẩn bị dữ liệu thanh toán từ một order đã tồn tại.
     *
     * Method này biến dữ liệu Eloquent model thành array dễ dùng cho view/frontend.
     *
     * Dữ liệu trả về gồm:
     * - thông tin booking/order,
     * - trạng thái order,
     * - chi nhánh,
     * - tên phòng,
     * - ngày checkin/checkout,
     * - số đêm,
     * - thông tin customer,
     * - danh sách chi tiết tiền phòng/dịch vụ,
     * - tổng tiền phòng,
     * - tổng tiền dịch vụ,
     * - discount,
     * - grand total,
     * - coupon,
     * - payment method label,
     * - các URL cần dùng trên frontend.
     *
     * Ghi chú:
     * - Method này không update database.
     * - Chỉ format dữ liệu để hiển thị.
     */
    private function paymentViewData(Order $order): array
    {
        // Lấy booking từ order, sau đó map từng OrderDetail thành array đơn giản cho view/frontend.
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

    /**
     * Chuẩn bị dữ liệu preview thanh toán khi booking chưa có order.
     *
     * Khác với paymentViewData():
     * - Method này lấy dữ liệu trực tiếp từ booking.
     * - order_id trả về null.
     * - order_status là DRAFT vì chưa có order chính thức.
     * - discount_amount mặc định là 0.
     * - coupon_code mặc định là null.
     *
     * Flow:
     * 1. Tính số đêm.
     * 2. Tạo collection details tạm thời.
     * 3. Duyệt bookingRooms để tạo dòng preview tiền phòng.
     * 4. Duyệt bookingServicePets để tạo dòng preview tiền dịch vụ.
     * 5. Tính roomTotal, serviceTotal và grandTotal.
     * 6. Trả array dữ liệu cho payment page.
     *
     * Ghi chú:
     * - Method này không tạo OrderDetail trong database.
     * - Chỉ tạo details dạng array/collection để preview.
     */
    private function bookingPaymentPreviewData(Booking $booking): array
    {
        // Preview dùng collection tạm, không ghi order_details vào database.
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

        // Tách tổng tiền phòng và tổng tiền dịch vụ để giao diện có thể hiển thị rõ từng nhóm chi phí.
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

    /**
     * Chuẩn hóa coupon code trước khi validate hoặc áp dụng.
     *
     * Logic:
     * - Ép giá trị về string để tránh lỗi khi null.
     * - trim để bỏ khoảng trắng đầu/cuối.
     * - Nếu sau trim là chuỗi rỗng thì trả null.
     * - Nếu có giá trị thì chuyển thành uppercase.
     *
     * Mục đích:
     * - Cho phép user nhập mã thường/hoa lẫn lộn nhưng vẫn match coupon.
     * - Tránh xử lý chuỗi rỗng như một coupon code thật.
     */
    private function normalizeCouponCode(?string $couponCode): ?string
    {
        $couponCode = trim((string) $couponCode);

        return $couponCode === '' ? null : strtoupper($couponCode);
    }

    /**
     * Chuẩn hóa thông tin liên hệ gửi từ form thanh toán.
     *
     * Method này luôn trả đủ 3 key:
     * - customer_name
     * - customer_phone
     * - customer_email
     *
     * Nếu input thiếu key nào thì dùng chuỗi rỗng.
     * Tất cả giá trị đều được ép string và trim khoảng trắng.
     *
     * Mục đích:
     * - Tránh lỗi undefined index khi cập nhật order.
     * - Đảm bảo dữ liệu lưu vào order sạch hơn.
     */
    private function normalizeContact(array $contact): array
    {
        return [
            'customer_name' => trim((string) ($contact['customer_name'] ?? '')),
            'customer_phone' => trim((string) ($contact['customer_phone'] ?? '')),
            'customer_email' => trim((string) ($contact['customer_email'] ?? '')),
        ];
    }

    /**
     * Bổ sung thông tin user/customer còn thiếu bằng dữ liệu contact từ thanh toán.
     *
     * Flow:
     * 1. Nếu user null thì dừng.
     * 2. Nếu user thiếu name và contact có customer_name, cập nhật user.name.
     * 3. Nếu user thiếu email và contact có customer_email, cập nhật user.email.
     * 4. Lấy customer liên kết với user.
     * 5. Nếu customer không tồn tại thì dừng.
     * 6. Nếu customer thiếu full_name/phone thì cập nhật từ contact.
     *
     * Ghi chú:
     * - Method này chỉ fill dữ liệu đang blank.
     * - Không ghi đè thông tin đã có sẵn.
     */
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

    /**
     * Validate coupon trước khi preview hoặc áp dụng cho order.
     *
     * Các điều kiện kiểm tra:
     * 1. Coupon code phải tồn tại.
     * 2. Coupon phải đang active.
     * 3. Coupon phải nằm trong thời gian hiệu lực.
     * 4. Coupon chưa vượt quá max_uses nếu max_uses không null.
     * 5. Subtotal của order phải đạt min_order_value.
     *
     * Tham số $lock:
     * - true: dùng lockForUpdate, phù hợp khi thật sự áp dụng coupon lúc thanh toán.
     * - false: không lock, phù hợp khi chỉ preview coupon.
     *
     * Return:
     * - Coupon hợp lệ.
     *
     * Exception:
     * - Ném ValidationException với message tương ứng nếu coupon không hợp lệ.
     */
    private function validCouponForOrder(string $couponCode, Order $order, bool $lock = true): Coupon
    {
        $now = now();
        // So sánh coupon theo uppercase để tương thích với couponCode đã được normalize thành chữ hoa.
        $query = Coupon::query()
            ->whereRaw('UPPER(coupon_code) = ?', [$couponCode]);

        // Khi áp dụng coupon thật sự, lock coupon để tránh vượt giới hạn used_count trong request song song.
        if ($lock) {
            $query->lockForUpdate();
        }

        $coupon = $query->first();

        // Không tìm thấy coupon thì trả lỗi validation cho field coupon_code.
        if (! $coupon) {
            throw ValidationException::withMessages([
                'coupon_code' => 'Ma giam gia khong ton tai.',
            ]);
        }

        // Coupon tồn tại nhưng bị tắt thì không cho dùng.
        if (! $coupon->is_active) {
            throw ValidationException::withMessages([
                'coupon_code' => 'Ma giam gia hien khong hoat dong.',
            ]);
        }

        // Coupon chỉ hợp lệ trong khoảng [effective_from, expired_at).
        if (Carbon::parse($coupon->effective_from)->gt($now) || Carbon::parse($coupon->expired_at)->lte($now)) {
            throw ValidationException::withMessages([
                'coupon_code' => 'Ma giam gia da het han hoac chua den thoi gian su dung.',
            ]);
        }

        // Nếu coupon có giới hạn lượt dùng thì không cho dùng khi used_count đã đạt max_uses.
        if ($coupon->max_uses !== null && (int) $coupon->used_count >= (int) $coupon->max_uses) {
            throw ValidationException::withMessages([
                'coupon_code' => 'Ma giam gia da het luot su dung.',
            ]);
        }

        // Order phải đạt giá trị tối thiểu thì coupon mới được áp dụng.
        if ((float) $order->subtotal < (float) $coupon->min_order_value) {
            throw ValidationException::withMessages([
                'coupon_code' => 'Don hang chua dat gia tri toi thieu de su dung ma giam gia nay.',
            ]);
        }

        return $coupon;
    }

    /**
     * Tạo array dữ liệu trả về cho chức năng preview coupon.
     *
     * Dữ liệu trả về gồm:
     * - coupon_code: mã coupon nếu có.
     * - discount_amount: số tiền được giảm.
     * - grand_total: tổng tiền sau giảm, không nhỏ hơn 0.
     * - subtotal: tổng tiền gốc của order.
     * - message: thông báo cho frontend/user.
     *
     * Ghi chú:
     * - Method này không validate coupon.
     * - Method này không update database.
     */
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

    /**
     * Tính số tiền được giảm từ coupon dựa trên subtotal.
     *
     * Logic:
     * - Nếu discount_type là PERCENT, discount = subtotal * discount_value / 100.
     * - Nếu không phải PERCENT, discount = discount_value cố định.
     * - Nếu coupon có max_discount, discount không được vượt max_discount.
     * - Discount cũng không được vượt subtotal.
     * - Kết quả được round 2 chữ số thập phân.
     *
     * Return:
     * - Số tiền giảm cuối cùng được dùng để tính grand_total.
     */
    private function discountAmountFor(Coupon $coupon, float $subtotal): float
    {
        // Hỗ trợ hai kiểu giảm giá: phần trăm theo subtotal hoặc số tiền cố định.
        $discountAmount = strtoupper((string) $coupon->discount_type) === 'PERCENT'
            ? $subtotal * ((float) $coupon->discount_value / 100)
            : (float) $coupon->discount_value;

        // Nếu coupon có trần giảm giá, discount không được vượt quá max_discount.
        if ($coupon->max_discount !== null) {
            $discountAmount = min($discountAmount, (float) $coupon->max_discount);
        }

        // Discount cuối cùng không được lớn hơn subtotal, sau đó làm tròn 2 chữ số thập phân.
        return round(min($discountAmount, $subtotal), 2);
    }

    /**
     * Lấy danh sách tên loại phòng trong booking để hiển thị.
     *
     * Flow:
     * - Nếu booking null, trả text mặc định.
     * - Duyệt bookingRooms để lấy type_name của từng phòng.
     * - Loại bỏ giá trị rỗng.
     * - unique để tránh lặp tên loại phòng.
     * - Nếu có tên phòng thì nối bằng dấu phẩy.
     * - Nếu không có thì trả text mặc định.
     */
    private function roomNames(?Booking $booking): string
    {
        if (! $booking) {
            return 'Phòng đang cập nhật';
        }

        // Lấy tên loại phòng từ từng bookingRoom, sau đó lọc rỗng và loại trùng.
        $names = $booking->bookingRooms
            ->map(fn (BookingRoom $bookingRoom) => $bookingRoom->room?->typeRoom?->type_name)
            ->filter()
            ->unique()
            ->values();

        return $names->isNotEmpty() ? $names->implode(', ') : 'Phòng đang cập nhật';
    }

    /**
     * Tính số đêm của booking dựa trên ngày checkin và checkout dự kiến.
     *
     * Logic:
     * - Parse checkin_expected_at và checkout_expected_at bằng Carbon.
     * - Đưa cả hai về startOfDay để tính theo ngày, bỏ qua giờ/phút/giây.
     * - diffInDays để tính khoảng cách ngày.
     * - max(1, ...) đảm bảo booking luôn có ít nhất 1 đêm.
     *
     * Mục đích:
     * - Tránh trường hợp checkin/checkout cùng ngày làm số đêm bằng 0.
     */
    private function bookingNights(Booking $booking): int
    {
        // Luôn trả ít nhất 1 đêm để tránh tổng tiền phòng bằng 0 khi ngày trùng nhau.
        return max(1, (int) Carbon::parse($booking->checkin_expected_at)
            ->startOfDay()
            ->diffInDays(Carbon::parse($booking->checkout_expected_at)->startOfDay()));
    }

    /**
     * Format ngày để hiển thị cho user.
     *
     * Logic:
     * - Nếu có ngày, parse bằng Carbon và format dạng d/m/Y.
     * - Nếu không có ngày, trả text 'Đang cập nhật'.
     *
     * Method này chỉ phục vụ hiển thị, không dùng để lưu database.
     */
    private function formatDate(mixed $date): string
    {
        return $date ? Carbon::parse($date)->format('d/m/Y') : 'Đang cập nhật';
    }

    /**
     * Chuyển payment method từ input/frontend sang giá trị lưu trong database.
     *
     * Mapping hiện tại:
     * - 'bank'   => 'BANK_TRANSFER'
     * - 'wallet' => 'MOMO'
     * - còn lại  => 'CASH'
     *
     * Ghi chú:
     * - default là CASH để tránh input không khớp làm lỗi hệ thống.
     */
    private function databasePaymentMethod(string $paymentMethod): string
    {
        // match expression giúp mapping ngắn gọn, rõ ràng và tránh nhiều if/else.
        return match ($paymentMethod) {
            'bank' => 'BANK_TRANSFER',
            'wallet' => 'MOMO',
            default => 'CASH',
        };
    }

    /**
     * Chuyển payment method trong database thành nhãn tiếng Việt để hiển thị.
     *
     * Mapping hiện tại:
     * - BANK_TRANSFER => Chuyển khoản ngân hàng
     * - MOMO          => Ví điện tử
     * - còn lại       => Tiền mặt khi nhận phòng
     */
    private function paymentMethodLabel(string $paymentMethod): string
    {
        // strtoupper giúp nhận diện payment method nhất quán dù dữ liệu truyền vào có chữ thường/chữ hoa.
        return match (strtoupper($paymentMethod)) {
            'BANK_TRANSFER' => 'Chuyển khoản ngân hàng',
            'MOMO' => 'Ví điện tử',
            default => 'Tiền mặt khi nhận phòng',
        };
    }

    /**
     * Danh sách relationship cần eager load khi làm việc với Booking.
     *
     * Lý do eager load:
     * - Giảm N+1 query.
     * - Đảm bảo các method tính tiền/format dữ liệu có sẵn dữ liệu liên quan.
     *
     * Các relationship gồm:
     * - customer.user: thông tin khách hàng và tài khoản user.
     * - branch: chi nhánh của booking.
     * - bookingRooms.room.typeRoom: phòng và loại phòng để lấy giá phòng.
     * - bookingServicePets.service: dịch vụ thú cưng để lấy giá dịch vụ.
     * - bookingServicePets.pet: thông tin pet để hiển thị tên pet.
     * - orders.details: order và chi tiết order nếu đã có.
     */
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

    /**
     * Danh sách relationship cần eager load khi làm việc với Order.
     *
     * Các relationship này phục vụ paymentViewData(), giúp view/frontend có đủ:
     * - thông tin customer/user,
     * - chi nhánh,
     * - coupon,
     * - booking và loại phòng,
     * - chi tiết tiền phòng,
     * - chi tiết dịch vụ thú cưng và pet.
     */
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