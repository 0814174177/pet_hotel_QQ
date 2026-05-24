<?php

namespace App\Http\Controllers\Web\Customer;

use App\Http\Controllers\Web\WebController;
use App\Http\Requests\Web\Customer\StoreBookingRequest;
use App\Repositories\Contracts\BookingRepositoryInterface;
use Exception;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;

/**
 * Class BookingController
 * Điều phối các request liên quan đến tính năng đặt phòng (Booking) trên giao diện Web khách hàng.
 */
class BookingController extends WebController
{
    /**
     * Khởi tạo Controller và tiêm (Inject) interface của BookingRepository.
     * Tách biệt logic nghiệp vụ khỏi Controller.
     */
    public function __construct(private BookingRepositoryInterface $bookings)
    {
        // Dependency Injection của BookingRepositoryInterface để sử dụng trong controller này
    }

    /**
     * Giao diện lịch sử đặt phòng của khách hàng.
     */
    public function index(): View|RedirectResponse
    {
        // Sử dụng hàm helper từ WebController để chặn khách vãng lai
        if ($redirect = $this->redirectIfGuest('Vui lòng đăng nhập để xem lịch sử đặt phòng.')) {
            return $redirect;
        }

        // Lấy thông tin Customer mapping với User hiện tại
        $customer = $this->bookings->customerForUser(Auth::user());

        // Đổ dữ liệu ra View, nếu chưa có thông tin Customer thì trả về mảng rỗng
        return view('client.profile.history-booking.index', [
            'bookings' => $customer ? $this->bookings->bookingHistoryItems($customer) : [],
        ]);
    }

    /**
     * Giao diện tạo đặt phòng (Mặc định).
     */
    public function create(): View
    {
        // Khởi tạo form với chi nhánh mặc định có ID là '1'
        return $this->bookingFormView('1');
    }

    /**
     * Giao diện danh sách các chi nhánh để khách hàng lựa chọn trước.
     */
    public function selectBranch(): View
    {
        return view('client.bookings.select-branch', [
            'branches' => $this->bookings->bookingBranches(),
        ]);
    }

    /**
     * Giao diện tạo đặt phòng cho một chi nhánh cụ thể.
     */
    public function createFromBranch(string $branchId): View
    {
        // Khởi tạo form dựa trên ID chi nhánh được truyền trên URL
        return $this->bookingFormView($branchId);
    }

    /**
     * Giao diện xem chi tiết một đơn đặt phòng cụ thể.
     */
    public function show(string $bookingId): View|RedirectResponse
    {
        // Bảo vệ route, yêu cầu xác thực
        if ($redirect = $this->redirectIfGuest('Vui lòng đăng nhập để xem chi tiết đặt phòng.')) {
            return $redirect;
        }

        $customer = $this->bookings->customerForUser(Auth::user());
        
        // Tìm booking theo ID, đảm bảo booking này thuộc về Customer hiện tại
        $booking = $customer ? $this->bookings->findCustomerBooking($customer, $bookingId) : null;

        // Nếu không tìm thấy hoặc không có quyền truy cập, đẩy về trang danh sách kèm lỗi
        if (! $booking) {
            return redirect()
                ->route('profile.history-booking.index')
                ->withErrors(['booking' => 'Không tìm thấy đơn booking trong lịch sử của bạn.']);
        }

        // Render view kèm dữ liệu chi tiết đã được format từ Repository
        return view('client.bookings.show', [
            'booking' => $this->bookings->bookingDetail($booking),
        ]);
    }

    /**
     * Xử lý logic lưu đơn đặt phòng vào Database.
     */
    public function store(StoreBookingRequest $request): RedirectResponse
    {
        // Chặn khách vãng lai gọi hàm tạo booking
        if ($redirect = $this->redirectIfGuest('Vui lòng đăng nhập trước khi thanh toán.')) {
            return $redirect;
        }

        try {
            // Gọi Repository thực thi Transaction tạo Booking và giữ chỗ (lock phòng)
            $booking = $this->bookings->createPendingBookingForUser(
                Auth::user(),
                $request->bookingPayload()
            );

            // Kiểm tra lựa chọn thanh toán của User: Chỉ giữ chỗ hay Thanh toán ngay
            if ($request->isHoldOnly()) {
                // Điều hướng về trang chi tiết nếu khách chỉ muốn giữ chỗ
                return redirect()
                    ->route('booking.show', $booking->booking_id)
                    ->with('status', 'Đã giữ chỗ cho bạn. Bạn có thể thanh toán sau trong lịch sử đặt phòng.');
            }

            // Điều hướng sang cổng thanh toán
            return redirect()
                ->route('payment.show', $booking->booking_id)
                ->with('status', 'Đã tìm thấy phòng và giữ chỗ cho bạn. Vui lòng thanh toán.');
        } catch (Exception $e) {
            // Bắt lỗi từ logic nghiệp vụ (hết phòng, sai số lượng,...) và trả về form
            return $this->bookingErrorResponse($e);
        }
    }

    /**
     * Hàm hỗ trợ (Private) đóng gói việc gọi Repository để lấy dữ liệu tổng hợp cho View tạo Booking.
     */
    private function bookingFormView(string $branchId): View
    {
        return view('client.bookings.create', $this->bookings->bookingFormViewData(
            $branchId,
            Auth::check(),
            Auth::user()
        ));
    }

    /**
     * Hàm hỗ trợ (Private) xử lý Exception trả về từ Repository.
     * Quy ước Exception message có dạng: "tên_trường_lỗi|Nội_dung_thông_báo".
     */
    private function bookingErrorResponse(Exception $e): RedirectResponse
    {
        // Cắt chuỗi theo dấu '|'
        $errorParts = explode('|', $e->getMessage(), 2);
        
        // Nếu có 2 phần, phần đầu là tên field (ví dụ: 'pet_ids'), phần sau là message.
        // Nếu không đúng định dạng, gán lỗi vào biến chung 'booking_error'.
        $field = count($errorParts) === 2 ? $errorParts[0] : 'booking_error';
        $message = count($errorParts) === 2 ? $errorParts[1] : $e->getMessage();

        // Trả về trang trước đó, giữ lại input cũ và đính kèm danh sách lỗi
        return back()
            ->withInput()
            ->withErrors([$field => $message]);
    }
}