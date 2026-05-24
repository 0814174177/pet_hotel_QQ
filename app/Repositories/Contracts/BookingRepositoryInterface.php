<?php

/**
 * Interface khai báo các thao tác liên quan đến quy trình đặt phòng của khách hàng.
 *
 * File này chỉ đóng vai trò là "contract" / "hợp đồng" cho Repository xử lý booking.
 * Nghĩa là interface này không trực tiếp viết logic xử lý dữ liệu, không truy vấn database,
 * không tạo booking thật, cũng không tự định dạng dữ liệu hiển thị. Thay vào đó, nó định nghĩa
 * những method bắt buộc mà class triển khai interface này phải có.
 *
 * Trong kiến trúc Repository Pattern, controller hoặc service có thể phụ thuộc vào interface này
 * thay vì phụ thuộc trực tiếp vào một class cụ thể. Việc này giúp code dễ thay thế, dễ test,
 * và tách phần khai báo hành vi khỏi phần cài đặt chi tiết.
 *
 * Lưu ý quan trọng:
 * - Toàn bộ phần được thêm trong file này chỉ là comment / PHPDoc để giải thích code.
 * - Không có bất kỳ method, tham số, kiểu trả về, namespace, use statement hoặc logic nào bị thay đổi.
 * - Vì đây là interface nên các method chỉ có phần khai báo chữ ký hàm, không có thân hàm xử lý.
 */

namespace App\Repositories\Contracts;

// Model Booking đại diện cho một lượt đặt phòng trong hệ thống.
// Các method trong interface này sử dụng Booking khi cần tìm, xem chi tiết,
// hoặc tạo mới một booking ở trạng thái chờ xử lý.
use App\Models\Booking;

// Model Customer đại diện cho thông tin khách hàng gắn với tài khoản người dùng.
// Interface dùng Customer để truy xuất lịch sử booking, tìm booking thuộc về khách hàng,
// và đảm bảo dữ liệu booking được lấy đúng theo chủ sở hữu.
use App\Models\Customer;

// Model User đại diện cho tài khoản đăng nhập của hệ thống Laravel.
// Một User có thể có hoặc không có Customer tương ứng, vì vậy các method nhận User
// thường cho phép null để hỗ trợ cả trường hợp khách chưa đăng nhập hoặc không có hồ sơ khách hàng.
use App\Models\User;

/**
 * BookingRepositoryInterface
 *
 * Interface này gom toàn bộ các hành vi chính liên quan đến booking ở phía khách hàng.
 * Class triển khai interface này thường sẽ chịu trách nhiệm lấy dữ liệu form đặt phòng,
 * lấy danh sách chi nhánh có thể đặt, hiển thị lịch sử đặt phòng, xem chi tiết booking,
 * tìm booking theo khách hàng, và tạo booking tạm thời / pending.
 *
 * Vì đây là interface nên nó không nói rõ dữ liệu được lấy bằng cách nào. Ví dụ:
 * - Có thể class triển khai dùng Eloquent để truy vấn database.
 * - Có thể dùng cache để tăng tốc.
 * - Có thể dùng mock repository khi viết unit test.
 *
 * Điểm quan trọng là bất kỳ class nào implements interface này đều phải có đầy đủ
 * các method bên dưới với đúng tham số và đúng kiểu dữ liệu trả về.
 */
interface BookingRepositoryInterface
{
    /**
     * Lấy thông tin Customer tương ứng với một User.
     *
     * Method này dùng khi hệ thống cần chuyển từ tài khoản đăng nhập Laravel User
     * sang hồ sơ khách hàng Customer. Trong nhiều hệ thống, User thường chứa thông tin
     * đăng nhập như email, password, role; còn Customer chứa thông tin nghiệp vụ như
     * họ tên khách, số điện thoại, lịch sử đặt phòng, hoặc mã khách hàng.
     *
     * Tham số:
     * - $user: User hiện tại đang đăng nhập. Tham số có thể là null, nghĩa là có thể không có
     *   người dùng đăng nhập hoặc hệ thống chưa xác định được user.
     *
     * Giá trị trả về:
     * - Trả về Customer nếu User có hồ sơ khách hàng tương ứng.
     * - Trả về null nếu User là null hoặc không tìm thấy Customer phù hợp.
     *
     * Method này chỉ là khai báo contract. Logic thật sự, ví dụ lấy $user->customer hay query database,
     * sẽ nằm trong class implements interface này.
     */
    public function customerForUser(?User $user): ?Customer;

    /**
     * Chuẩn bị toàn bộ dữ liệu cần thiết để hiển thị form đặt phòng cho khách hàng.
     *
     * Method này thường được controller gọi trước khi render trang đặt phòng. Dữ liệu trả về có thể bao gồm
     * thông tin chi nhánh đang chọn, danh sách loại phòng, dịch vụ đi kèm, thông tin user nếu đã đăng nhập,
     * trạng thái đăng nhập, hoặc các dữ liệu khác cần cho giao diện booking.
     *
     * Tham số:
     * - $branchId: Mã chi nhánh mà khách đang muốn đặt phòng. Đây là chuỗi định danh chi nhánh.
     * - $isAuthenticated: Cho biết khách hiện tại đã đăng nhập hay chưa.
     * - $user: User hiện tại, có thể null. Giá trị mặc định là null để hỗ trợ trường hợp khách chưa đăng nhập.
     *
     * Giá trị trả về:
     * - Trả về một mảng dữ liệu dùng cho view / frontend.
     * - Cấu trúc cụ thể của mảng sẽ do class triển khai quyết định, nhưng method luôn phải trả về array.
     *
     * Method này không tạo booking mới. Nó chỉ chuẩn bị dữ liệu để người dùng có thể nhìn thấy
     * và điền form đặt phòng.
     */
    public function bookingFormViewData(string $branchId, bool $isAuthenticated, ?User $user = null): array;

    /**
     * Lấy danh sách chi nhánh có thể dùng trong quy trình đặt phòng.
     *
     * Method này thường phục vụ các màn hình cho phép khách chọn địa điểm / chi nhánh trước khi đặt phòng.
     * Dữ liệu trả về có thể được dùng để render dropdown, card chi nhánh, danh sách lựa chọn,
     * hoặc điều hướng người dùng đến form booking của một chi nhánh cụ thể.
     *
     * Giá trị trả về:
     * - Trả về array chứa danh sách chi nhánh.
     * - Mỗi phần tử trong mảng có thể chứa các thông tin như mã chi nhánh, tên chi nhánh, địa chỉ,
     *   trạng thái hoạt động, hoặc dữ liệu hiển thị khác tùy class triển khai.
     *
     * Interface không quy định chi tiết cách lấy danh sách này từ database hay nguồn khác.
     */
    public function bookingBranches(): array;

    /**
     * Lấy danh sách lịch sử đặt phòng của một khách hàng cụ thể.
     *
     * Method này dùng cho các trang như "Lịch sử đặt phòng", "My bookings", "Profile booking history",
     * nơi người dùng muốn xem lại các booking đã tạo trước đó.
     *
     * Tham số:
     * - $customer: Hồ sơ khách hàng cần lấy lịch sử booking.
     *
     * Giá trị trả về:
     * - Trả về array gồm các item lịch sử booking.
     * - Mỗi item thường có thể chứa mã booking, ngày check-in, ngày check-out, tên chi nhánh,
     *   trạng thái booking, tổng tiền, hoặc các thông tin tóm tắt khác.
     *
     * Việc sắp xếp theo ngày mới nhất, lọc trạng thái, hoặc định dạng dữ liệu hiển thị
     * sẽ được quyết định trong class cài đặt cụ thể.
     */
    public function bookingHistoryItems(Customer $customer): array;

    /**
     * Tìm một booking cụ thể thuộc về một khách hàng cụ thể.
     *
     * Method này rất quan trọng để đảm bảo khách hàng chỉ xem hoặc thao tác với booking của chính họ.
     * Thay vì chỉ tìm booking theo mã booking, method nhận thêm Customer để ràng buộc quyền sở hữu.
     *
     * Tham số:
     * - $customer: Khách hàng đang thực hiện yêu cầu.
     * - $bookingId: Mã booking cần tìm.
     *
     * Giá trị trả về:
     * - Trả về Booking nếu tìm thấy booking có mã tương ứng và thuộc về customer đó.
     * - Trả về null nếu không tìm thấy booking hoặc booking không thuộc về khách hàng này.
     *
     * Method này chỉ khai báo hành vi. Logic kiểm tra quyền sở hữu và query database
     * sẽ nằm trong repository implementation.
     */
    public function findCustomerBooking(Customer $customer, string $bookingId): ?Booking;

    /**
     * Chuẩn bị dữ liệu chi tiết của một booking để hiển thị cho người dùng.
     *
     * Method này thường được dùng ở trang chi tiết booking. Thay vì trả trực tiếp model thô cho view,
     * repository có thể chuyển Booking thành một array đã được định dạng sẵn, dễ render và nhất quán hơn.
     *
     * Tham số:
     * - $booking: Booking cần lấy thông tin chi tiết.
     *
     * Giá trị trả về:
     * - Trả về array chứa dữ liệu chi tiết booking.
     * - Dữ liệu có thể bao gồm thông tin phòng, thú cưng, dịch vụ, chi nhánh, ngày nhận phòng,
     *   ngày trả phòng, số đêm, trạng thái, tổng tiền, thông tin thanh toán, hoặc các URL liên quan.
     *
     * Interface không thay đổi Booking, không cập nhật database, và không quy định format cụ thể.
     * Class triển khai sẽ quyết định dữ liệu nào cần đưa vào mảng trả về.
     */
    public function bookingDetail(Booking $booking): array;

    /**
     * Tạo một booking mới ở trạng thái chờ xử lý cho User hiện tại.
     *
     * Method này thường được gọi sau khi khách gửi form đặt phòng. Dữ liệu form sẽ được gom vào $bookingData,
     * sau đó class triển khai sẽ kiểm tra, tạo booking, tạo các bản ghi liên quan như phòng đã đặt,
     * dịch vụ đi kèm, thú cưng, hoặc các thông tin nghiệp vụ khác nếu cần.
     *
     * Tham số:
     * - $user: User hiện tại. Có thể null để hỗ trợ một số luồng đặt phòng chưa đăng nhập,
     *   tùy cách hệ thống triển khai.
     * - $bookingData: Mảng dữ liệu đầu vào dùng để tạo booking. Mảng này thường đến từ request đã validate,
     *   có thể bao gồm branch_id, room_id, pet_id, check-in, check-out, dịch vụ, ghi chú, thông tin liên hệ, v.v.
     *
     * Giá trị trả về:
     * - Trả về Booking vừa được tạo.
     * - Kiểu trả về là Booking, không nullable, nghĩa là nếu tạo thất bại thì class triển khai thường sẽ ném exception
     *   hoặc xử lý lỗi theo cách riêng, thay vì trả về null.
     *
     * Vì đây là interface nên method này không chứa logic tạo dữ liệu. Nó chỉ bắt buộc class triển khai
     * phải cung cấp khả năng tạo pending booking theo đúng chữ ký hàm này.
     */
    public function createPendingBookingForUser(?User $user, array $bookingData): Booking;
}