# Quy trình chuyển Laravel Pet Hotel Web sang Oracle

Tài liệu này ghi lại toàn bộ quá trình chuyển project Laravel Pet Hotel Web từ hướng MySQL/SQLite sang Oracle Database. Mục tiêu chính là để web chạy được trên Oracle hiện có, sau đó mới chuẩn hóa dần constraint, trigger, procedure, function theo tài liệu `database_mau`.

## 1. Mục tiêu

- Giữ web hiện tại là nguồn đúng chính.
- Chạy được với Oracle bằng Laravel migration và seeder.
- Người khác clone project về chỉ cần cấu hình `.env` Oracle, chạy dependency, migrate, seed và serve.
- Không bê nguyên `database_mau` vào project nếu làm web lỗi.
- Không đổi primary key, tên bảng, tên cột, status hoặc nullable nếu controller, model, seeder, view chưa khớp.
- Ghi rõ phần nào áp dụng ngay, phần nào hoãn và lý do.

## 2. Nguyên tắc chuyển đổi

Thứ tự ưu tiên:

1. Web hiện tại chạy được.
2. Migration, model, seeder, controller, view hiện tại không bị gãy.
3. `database_mau` chỉ dùng để đối chiếu nghiệp vụ và bổ sung có kiểm soát.
4. Constraint Oracle chỉ áp dụng khi không phá migrate, seed và luồng web.
5. Trigger, procedure, function Oracle để sau khi table, seed và web cơ bản đã chạy ổn.

Những việc không làm trong giai đoạn đầu:

- Không đổi toàn bộ integer ID sang `VARCHAR2` code ID.
- Không đổi `users` sang `app_user`.
- Không ép NOT NULL từ `database_mau` khi form/controller/seeder chưa gửi đủ dữ liệu.
- Không ép CHECK constraint nếu status web đang khác status trong `database_mau`.
- Không dùng trigger tự tính tiền nếu seeder/controller vẫn đang tự ghi tổng tiền.
- Không dùng trigger/procedure/function nâng cao khi migration và seed chưa ổn.

## 3. Trạng thái ban đầu của project

- Project ban đầu thiên về MySQL/MariaDB, còn dấu vết SQLite.
- `.env` không có trong workspace và không được commit.
- `.env.example` ban đầu dùng `DB_CONNECTION=mysql` và database `pethotel_db`.
- `config/database.php` ban đầu fallback về `sqlite`.
- `composer.json` đã có package `yajra/laravel-oci8`.
- `composer.lock` đã có `yajra/laravel-oci8` và `yajra/laravel-pdo-via-oci8`.
- Có file `pethotel_db` là SQLite database đang được Git track.
- README ban đầu hướng dẫn MySQL/MariaDB.
- `phpunit.xml` ban đầu ép test dùng SQLite in-memory.
- Một số migration dùng kiểu MySQL/Laravel chưa an toàn cho Oracle như `enum`, `unsignedBigInteger`, trigger MySQL, cú pháp cleanup FK kiểu MySQL trong seeder.

## 4. Oracle Database hiện tại

Oracle Database đã có sẵn. Tài liệu này không hướng dẫn cài Oracle Database từ đầu.

Những phần vẫn cần kiểm tra trên máy chạy web:

- Oracle host, port, service name.
- Oracle user/schema, ví dụ `PET_HOTEL`.
- Password thật trong `.env` local.
- PHP đã có trong `PATH`.
- PHP extension `oci8` đã bật.
- Composer đã có trong `PATH`.
- Node.js và npm đã có trong `PATH` nếu cần build frontend.

Ghi chú: `pdo_oci` không bắt buộc trong hướng này vì project dùng `yajra/laravel-oci8`, package này dùng OCI8 thông qua `yajra/laravel-pdo-via-oci8`.

## 5. Phần mềm và package cần có

Package Oracle đã có:

| Vị trí | Trạng thái |
|---|---|
| `composer.json` | Có `yajra/laravel-oci8` |
| `composer.lock` | Có `yajra/laravel-oci8` v12.11.0 |
| `composer.lock` | Có `yajra/laravel-pdo-via-oci8` v3.7.3 |

Tham khảo cấu hình đã đối chiếu:

- https://github.com/yajra/laravel-oci8
- https://yajrabox.com/docs/laravel-oci8/12.0/general-settings

Trong phiên kiểm tra hiện tại, các lệnh sau chưa chạy được vì chưa có trong `PATH`:

- `php`
- `composer`
- `node`
- `npm`

Cập nhật khi chạy hệ thống ngày 2026-05-24:

- PHP tìm thấy ở `D:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe`.
- PHP version: 8.3.30.
- Extension `oci8` đã bật.
- Composer chạy được bằng `D:\laragon\bin\composer\composer.phar`.
- Node.js chạy được bằng `D:\laragon\bin\nodejs\node-v22\node.exe`.
- npm chạy được bằng `D:\laragon\bin\nodejs\node-v22\npm.cmd`.
- Các lệnh này chưa nằm trong PATH chung của terminal, nên khi chạy thủ công cần dùng đường dẫn đầy đủ hoặc thêm Laragon PHP/Composer/Node vào PATH.

## 6. Cài hoặc bật phần còn thiếu trên máy local

Không cần cài lại Oracle Database. Chỉ cần bảo đảm máy chạy Laravel có các phần sau.

### 6.1 PHP 8.2 trở lên

1. Cài PHP 8.2+ phù hợp với Windows.
2. Thêm thư mục PHP vào biến môi trường `PATH`.
3. Mở terminal mới.
4. Kiểm tra:

```bash
php -v
```

### 6.2 Bật OCI8 cho PHP

1. Xác định file `php.ini` đang dùng:

```bash
php --ini
```

2. Bật extension OCI8 trong `php.ini`.
3. Nếu PHP không tìm thấy Oracle client libraries, cài hoặc cấu hình Oracle Instant Client đúng kiến trúc với PHP.
4. Kiểm tra:

```bash
php -m
php --ri oci8
```

Nếu thấy `oci8` trong danh sách extension là được.

### 6.3 Composer

1. Cài Composer for Windows sau khi PHP đã chạy được.
2. Kiểm tra:

```bash
composer --version
```

### 6.4 Node.js và npm

1. Cài Node.js LTS.
2. Kiểm tra:

```bash
node --version
npm --version
```

## 7. Cấu hình `.env.example` cho Oracle

`.env.example` đã được đổi sang mẫu Oracle:

```env
DB_CONNECTION=oracle
DB_HOST=127.0.0.1
DB_PORT=1521
DB_DATABASE=FREEPDB1
DB_SERVICE_NAME=FREEPDB1
DB_TNS=
DB_USERNAME=PET_HOTEL
DB_PASSWORD=your_password
DB_CHARSET=AL32UTF8
DB_SERVER_VERSION=11g
ORA_MAX_NAME_LEN=30
```

Quy tắc:

- Không commit `.env` thật.
- Không commit password Oracle thật.
- Nếu dùng TNS đầy đủ thì điền `DB_TNS`.
- Nếu dùng service name thì giữ `DB_SERVICE_NAME=FREEPDB1` hoặc đổi theo service thật.

## 8. Cấu hình Laravel Oracle connection

`config/database.php` đã có connection `oracle` rõ ràng cho `yajra/laravel-oci8`.

Các key quan trọng:

| Key | Ý nghĩa |
|---|---|
| `driver` | `oracle` |
| `tns` | Chuỗi TNS nếu dùng |
| `host` | IP hoặc hostname Oracle |
| `port` | Thường là `1521` |
| `database` | Database/service mặc định |
| `service_name` | Service name Oracle, ví dụ `FREEPDB1` |
| `username` | Oracle schema/user |
| `password` | Password lấy từ `.env` |
| `charset` | Thường là `AL32UTF8` |
| `server_version` | Mặc định `11g` để tương thích |
| `max_name_len` | Mặc định `30` để tránh tên object quá dài |

Test kết nối sau khi PHP và OCI8 đã sẵn sàng:

```bash
php artisan config:clear
php artisan tinker
DB::connection()->getPdo();
DB::select('select 1 as ok from dual');
```

Nếu trên Windows gặp `ORA-12638: Failed to retrieve credentials for NTS`, dùng cấu hình Oracle client cục bộ đã thêm trong project:

```powershell
$env:TNS_ADMIN="D:\Web_Pethotel_UTD\pet_hotel_QQ\database\oracle\network\admin"
```

File `database/oracle/network/admin/sqlnet.ora` đặt:

```text
SQLNET.AUTHENTICATION_SERVICES = (NONE)
NAMES.DIRECTORY_PATH = (TNSNAMES, EZCONNECT)
```

## 9. Tạo Oracle user/schema nếu cần

Nếu máy đã có user/schema rồi thì bỏ qua. Nếu cần tạo user mới, chạy bằng tài khoản có quyền DBA:

```sql
CREATE USER PET_HOTEL IDENTIFIED BY your_password;
GRANT CONNECT, RESOURCE TO PET_HOTEL;
ALTER USER PET_HOTEL QUOTA UNLIMITED ON USERS;
```

Không đưa password thật vào Git.

## 10. Quá trình chuyển từ MySQL/SQLite sang Oracle

### 10.1 Đọc project Laravel trước

Đã ưu tiên đọc các phần web đang dùng:

- `.env.example`
- `composer.json`
- `composer.lock`
- `config/database.php`
- `config/queue.php`
- `phpunit.xml`
- `database/migrations`
- `database/seeders`
- `app/Models`
- `app/Http/Controllers`
- `app/Repositories`
- `routes`
- `resources/views`
- `database/database_mau`

Kết luận: web hiện tại dùng schema theo Laravel/MySQL/SQLite style, chưa chạy Oracle thật.

### 10.2 Sửa cấu hình trước

Các thay đổi chính:

- `.env.example`: đổi sang Oracle mẫu.
- `config/database.php`: default fallback đổi sang `oracle`, thêm connection `oracle`.
- `config/queue.php`: fallback database cho queue batch/failed đổi từ SQLite sang Oracle.
- `composer.json`: bỏ script tạo file SQLite mặc định.
- `phpunit.xml`: bỏ ép `DB_CONNECTION=sqlite` và `DB_DATABASE=:memory:`.
- `README.md`: đổi hướng dẫn chạy project sang Oracle.

Lý do: trước khi sửa schema, project phải có đường kết nối Oracle rõ ràng để người clone về không chạy nhầm MySQL/SQLite.

### 10.3 Sửa migration

Nguyên tắc: giữ schema web trước, không đổi máy móc theo `database_mau`.

Những nhóm lỗi MySQL/SQLite đã xử lý:

| Vấn đề | Cách xử lý |
|---|---|
| `enum()` kiểu MySQL | Đổi sang `string` và thêm CHECK constraint Oracle-compatible |
| `unsignedInteger`, `unsignedBigInteger` | Đổi sang integer/bigInteger thường ở FK cần an toàn |
| Tên FK/index/constraint dài | Đặt tên ngắn, duy nhất trong schema |
| Trigger MySQL | Tạm hoãn, migration trigger hiện là no-op |
| SQLite fallback | Không dùng làm default nữa |
| `useCurrentOnUpdate()` | Tránh dùng ở cột có rủi ro Oracle |
| MySQL raw trigger syntax | Không chạy trong Oracle |

ID được giữ là integer:

- Web hiện tại, model, controller, seeder đều đang dựa vào integer primary key.
- `database_mau` có nhiều ID kiểu `VARCHAR2`, nhưng đổi ngay sẽ phải sửa đồng bộ toàn bộ auth, route, relationship, form và seeder.
- Vì mục tiêu là web chạy được trước, giữ integer ID là quyết định an toàn.

Status được xử lý theo hướng chấp nhận trạng thái web:

- `booking.status` giữ thêm `COMPLETED` vì web history/payment dùng.
- `orders.status` cho phép cả `COMPLETED` và `PAID` vì web và API đang dùng khác nhau.
- `booking_service_pet.status` cho phép cả `ASSIGNED` và `SCHEDULED` để có thời gian chuẩn hóa.

Đã thêm migration `payments` vì controller/API/report đã gọi Payment nhưng trước đó thiếu bảng/model đồng bộ.

### 10.4 Sửa seeder

Seeder được sửa theo hướng Oracle-safe:

- `CleanupSeeder` không dùng `SET FOREIGN_KEY_CHECKS=0`.
- Không dùng truncate kiểu có thể vướng FK/identity.
- Xóa dữ liệu theo thứ tự bảng con trước, bảng cha sau.
- Seed bảng cha trước, bảng con sau.
- Seed thêm dữ liệu `payments` cho order đã thanh toán.
- Tổng tiền trong `orders` và `order_details` được ghi rõ vì trigger tính tiền đang hoãn.
- Sau khi seed ID cố định, có logic đồng bộ sequence Oracle để tránh tạo record mới bị trùng ID.

### 10.5 Sửa model

Các model được khai báo rõ hơn để phù hợp integer PK:

- `protected $primaryKey`
- `public $incrementing = true`
- `protected $keyType = 'int'`

Đã thêm/sửa relationship để khớp controller:

- `Booking::rooms()`
- `Booking::bookingServicesPet()`
- `Order::orderDetails()`
- `Order::payment()`
- `Service::products()`

Đã thêm model `Payment` cho bảng `payments`.

### 10.6 Sửa controller và repository

Các điểm đã xử lý:

- API booking không tự sinh ID chuỗi kiểu `BKG`, `BKR`, `BSP` nữa.
- API payment không tự sinh `payment_id` chuỗi nữa.
- Profile API không tự sinh pet ID kiểu chuỗi.
- Payment repository tạo hoặc cập nhật bản ghi `payments`.
- Payment repository coi cả `COMPLETED` và `PAID` là trạng thái đã thanh toán để tránh thanh toán lại.
- Booking API ghi đúng cột `special_notes`, `notes`, integer FK theo migration hiện tại.

### 10.7 Chưa áp trigger/procedure/function

Trigger/procedure/function Oracle trong `database_mau` chưa áp dụng ngay vì:

- Migration và seed cần ổn trước.
- Một số trigger có thể tự tính tiền trong khi controller/seeder đang tự tính.
- Một số trigger kiểm tra overlap booking có thể làm seed hoặc form hiện tại lỗi nếu chưa map đầy đủ.
- Một số trigger/procedure liên quan kho nâng cao mà web hiện chưa dùng.

## 11. Mapping status

| Miền dữ liệu | Web hiện tại | `database_mau` Oracle | Quyết định | Lý do |
|---|---|---|---|---|
| `users.role` | `CUSTOMER`, `RECEPTIONIST`, `GROOMER`, `MANAGER`, `ADMIN` | `role_emp` số 0-5 | KEEP_WEB_VERSION | Auth và middleware đang dùng role dạng text |
| `booking.status` | `PENDING`, `CONFIRMED`, `CHECKED_IN`, `CHECKED_OUT`, `COMPLETED`, `CANCELLED` | Thiếu `COMPLETED` | KEEP_WEB_VERSION | Web lịch sử/payment dùng `COMPLETED` |
| `booking_service_pet.status` | Có `ASSIGNED` | Có `SCHEDULED` | APPLY_AFTER_FIX | Tạm cho phép cả hai, chuẩn hóa UI/API sau |
| `orders.status` | Có `COMPLETED` | Có `PAID`, `PARTIAL` | APPLY_AFTER_FIX | Tạm cho phép cả `COMPLETED` và `PAID` |
| `payments.status` | Thiếu migration/model | `PENDING`, `SUCCESS`, `FAILED`, `REFUNDED` | APPLY_NOW | Controller/report đã dùng Payment |

## 12. So sánh web hiện tại với `database_mau`

| Bảng | Web hiện tại | `database_mau` Oracle | Quyết định | Lý do |
|---|---|---|---|---|
| `users` / `app_user` | `users.id` integer, `name`, `email`, role text | `app_user.user_id VARCHAR2`, role số | KEEP_WEB_VERSION | Auth, middleware, seeder, relationship đang dựa vào `users.id` |
| `customer` | integer `customer_id`, FK `user_id`, email nằm ở `users` | `customer_id VARCHAR2`, email riêng | KEEP_WEB_VERSION | Không cần thêm email vào customer để web chạy |
| `branch` | integer `branch_id`, có timestamps | `branch_id VARCHAR2` | KEEP_WEB_VERSION | View/seeder dùng integer và timestamps |
| `type_room` | Có `max_slot`, tên phòng tiếng Việt | CHECK theo STANDARD/PREMIUM/SUITE | KEEP_WEB_VERSION | UI booking dùng `max_slot`, seed dùng tên tiếng Việt |
| `room` | integer ID, status `AVAILABLE`, `IN_USE`, `MAINTENANCE` | `VARCHAR2` ID, status tương tự | APPLY_NOW một phần | Giữ integer ID, áp status check |
| `product` | Có `item_price`, ảnh, `is_active` | Có `cost_price`, thiếu ảnh/active | KEEP_WEB_VERSION | Service/payment dùng `item_price` và active/image |
| `services` | Có species `ALL`, `BIRD`, `RABBIT`, `OTHER` | Không rõ đầy đủ species | KEEP_WEB_VERSION | Web filter service theo species hiện tại |
| `branch_inventory` | Có surrogate PK `branch_inventory_id` | Composite PK branch/product | KEEP_WEB_VERSION | Model hiện dùng một primary key |
| `booking` | Có `total_amount`, actual times | Có `deposit_amount`, không có `total_amount` | KEEP_WEB_VERSION | Payment repository cập nhật `total_amount` |
| `booking_room_pet` | Có surrogate PK | Composite PK | KEEP_WEB_VERSION | Model/controller cần surrogate ID |
| `orders` | Có coupon/user/payment_method/discount/paid_at | Finance model khác, status PAID/PARTIAL | APPLY_AFTER_FIX | Cần giữ web payment trước |
| `payments` | Thiếu nhưng code gọi | Có bảng payments | APPLY_NOW | Thêm migration/model có kiểm soát |
| `goods_receipt`, `stock_audit`, `material_waste` | Chưa có UI/controller | Có trong Oracle mẫu | SKIP_FOR_NOW | Chưa phục vụ web hiện tại |

## 13. Phân loại constraint

| Constraint | Phân loại | Ghi chú |
|---|---|---|
| CHECK giá, số lượng, tổng tiền không âm | APPLY_NOW | Không xung đột seeder |
| FK theo schema web hiện tại | APPLY_NOW | Giữ tên bảng/cột của web |
| CHECK `room.status` | APPLY_NOW | Web và `database_mau` tương thích |
| CHECK `booking.status` từ `database_mau` | KEEP_WEB_VERSION | `database_mau` thiếu `COMPLETED` |
| CHECK `booking_service_pet.status` | APPLY_AFTER_FIX | Cần thống nhất `ASSIGNED` và `SCHEDULED` |
| CHECK `type_room.type_name` STANDARD/PREMIUM/SUITE | SKIP_FOR_NOW | Seed/web dùng tên tiếng Việt |
| Role số trong `app_user` | SKIP_FOR_NOW | Sẽ phải viết lại auth layer |
| Composite PK cho inventory/booking_room_pet | SKIP_FOR_NOW | Cần sửa model/controller rộng |
| Constraint kho nâng cao | SKIP_FOR_NOW | Web chưa dùng các bảng đó |

## 14. Kế hoạch trigger, procedure, function

Không áp dụng ngay trigger/procedure/function Oracle.

Thứ tự đúng khi áp dụng sau:

1. Chạy được `php artisan migrate:fresh`.
2. Chạy được `php artisan migrate:fresh --seed`.
3. Test login, dashboard, customer, pet, booking, order, payment.
4. Thêm function.
5. Thêm procedure.
6. Thêm trigger.
7. Test lại migrate/seed/web.

Phân loại hiện tại:

| Nhóm | Quyết định | Lý do |
|---|---|---|
| Function cộng phút, kiểm tra overlap | APPLY_AFTER_FIX | Cần map form, seeder, controller ngày giờ trước |
| Trigger đồng bộ payment/order | APPLY_AFTER_FIX | Cần thống nhất `COMPLETED`/`PAID` trước |
| Trigger tự tính tổng tiền | APPLY_AFTER_FIX | Hiện controller/seeder đang tự tính |
| Trigger/procedure kho | SKIP_FOR_NOW | Web chưa có luồng kho đầy đủ |
| Trigger tự sinh ID `VARCHAR2` | SKIP_FOR_NOW | Web đang giữ integer PK |

## 15. Các file đã sửa

| Nhóm | File |
|---|---|
| Cấu hình | `.env.example`, `config/database.php`, `config/queue.php`, `phpunit.xml` |
| Tài liệu | `README.md`, `WORKFLOW_ORACLE_MIGRATION.md` |
| Composer | `composer.json` |
| Migration | `database/migrations/*`, thêm `2026_05_19_000023_create_payments_table.php` |
| Seeder | `CleanupSeeder.php`, `DatabaseSeeder.php`, `OrderSeeder.php` |
| Model | Nhiều model thêm PK metadata, thêm `app/Models/Payment.php` |
| Controller/API | Customer Auth, Booking, Payment, Profile |
| Repository | `PaymentRepository.php` |
| Git ignore | `.gitignore` thêm SQLite cũ |

## 16. Lệnh test bắt buộc

Sau khi PHP, Composer, OCI8, Node/npm sẵn sàng:

```bash
composer install
php artisan config:clear
php artisan cache:clear
php artisan route:clear
php artisan view:clear
php artisan migrate:fresh
php artisan migrate:fresh --seed
php artisan serve
npm install
npm run build
```

Môi trường hiện tại chưa chạy được các lệnh runtime vì `php`, `composer`, `node`, `npm` chưa có trong `PATH`.

## 17. Luồng web cần test sau khi serve

Sau khi chạy được `php artisan serve`, cần kiểm tra:

- Trang login.
- Đăng nhập user seed.
- Dashboard.
- Danh sách khách hàng.
- Thêm, sửa, xóa khách hàng.
- Danh sách thú cưng.
- Thêm, sửa, xóa thú cưng.
- Booking.
- Dịch vụ.
- Phòng.
- Đơn hàng/hóa đơn.
- Thanh toán.
- Kho nếu có UI.
- Các trang có relationship phức tạp.

## 18. Hướng dẫn clone về chạy

```bash
git clone <repo-url>
cd <project-folder>
composer install
cp .env.example .env
php artisan key:generate
```

Trên Windows PowerShell:

```powershell
Copy-Item .env.example .env
```

Cấu hình `.env`:

```env
DB_CONNECTION=oracle
DB_HOST=127.0.0.1
DB_PORT=1521
DB_DATABASE=FREEPDB1
DB_SERVICE_NAME=FREEPDB1
DB_USERNAME=PET_HOTEL
DB_PASSWORD=your_password
```

Chạy:

```bash
php artisan config:clear
php artisan cache:clear
php artisan migrate:fresh --seed
npm install
npm run build
php artisan serve
```

Mở trình duyệt:

```text
http://127.0.0.1:8000
```

Tài khoản seed mẫu dùng password `password123`:

- `admin@pethotel.test`
- `manager@pethotel.test`
- `customer1@pethotel.test`

## 19. Lỗi đã gặp và cách xử lý

| Ngày | Khu vực | Lỗi | Cách xử lý |
|---|---|---|---|
| 2026-05-24 | Kiểm tra môi trường | `php`, `composer`, `node`, `npm` không có trong `PATH` | Ghi nhận chưa test runtime được, cần cài/bật PATH trước |
| 2026-05-24 | Scan code | Còn dấu MySQL/SQLite trong code chạy chính | Bỏ trigger MySQL, bỏ FK cleanup MySQL, bỏ SQLite PHPUnit override, cập nhật README, bỏ sinh ID chuỗi |
| 2026-05-24 | Chạy hệ thống | PHP/OCI8 có trong Laragon nhưng chưa nằm trong PATH | Dùng đường dẫn đầy đủ của Laragon để chạy Composer và Artisan |
| 2026-05-24 | Oracle client | `ORA-12638` do `SQLNET.AUTHENTICATION_SERVICES=(NTS)` trong Oracle Home | Thêm `database/oracle/network/admin/sqlnet.ora` với `AUTHENTICATION_SERVICES=(NONE)` và chạy artisan với biến `TNS_ADMIN` |
| 2026-05-24 | Oracle login | `ORA-01017` khi dùng `PET_HOTEL/your_password@FREEPDB1` | Cần cập nhật `.env` bằng username/password Oracle thật hoặc tạo/reset user `PET_HOTEL` |
| 2026-05-24 | Migration Oracle FK | `ORA-02000: missing CASCADE keyword` | Laravel sinh `ON DELETE RESTRICT` từ `restrictOnDelete()`. Oracle không hỗ trợ `ON DELETE RESTRICT`; nếu muốn restrict thì bỏ khai báo `ON DELETE` vì Oracle mặc định chặn xóa parent khi còn child. Đã xóa 19 `restrictOnDelete()` và không đổi sang cascade/null. |

## 20. Checklist trước khi push Git

- [x] `.env` không bị commit.
- [x] `.env.example` có cấu hình Oracle mẫu.
- [x] `config/database.php` có Oracle connection.
- [x] `composer.json` và `composer.lock` có package Oracle.
- [x] Chạy được migration trên Oracle.
- [ ] Chạy được seeder trên Oracle.
- [x] Model và relationship đã khớp migration trong kiểm tra tĩnh.
- [x] Controller không còn gọi thiếu bảng/model `payments`.
- [ ] View đã được test runtime để chắc không gọi cột cũ.
- [x] `WORKFLOW_ORACLE_MIGRATION.md` đã cập nhật.
- [x] `README.md` đã cập nhật.
- [x] Không commit `vendor`.
- [x] Không commit `node_modules`.
- [ ] Xóa file SQLite cũ khỏi Git nếu không còn cần.
- [x] Không hard-code password Oracle thật.
- [x] Không hard-code đường dẫn máy cá nhân.

Lệnh chuẩn bị commit sau khi test runtime thành công:

```bash
git status
git add .
git commit -m "Migrate Pet Hotel web database layer to Oracle"
git push
```

## 21. Nhật ký theo giai đoạn

### Giai đoạn 1: Cấu hình Oracle

- Kiểm tra `composer.json` và `composer.lock`: đã có `yajra/laravel-oci8`.
- Chưa kiểm tra được `oci8` runtime vì `php` chưa có trong `PATH`.
- Cập nhật `.env.example` sang Oracle.
- Cập nhật `config/database.php` để default fallback là `oracle`.
- Cập nhật `config/queue.php` để batch/failed jobs dùng Oracle fallback.

### Giai đoạn 2: Workflow

- Tạo và cập nhật file workflow này.
- Ghi trạng thái ban đầu, nguyên tắc chuyển đổi, compare với `database_mau`, test command, hướng dẫn clone chạy.

### Giai đoạn 3: Migration

- Đổi `enum` MySQL sang `string` cộng CHECK constraint.
- Đổi unsigned FK sang integer/bigInteger thường.
- Đặt tên FK/index/check ngắn và duy nhất.
- Giữ integer primary key.
- Thêm `pet.sex` vì code booking/profile đọc field này.
- Thêm migration `payments`.
- Hoãn migration trigger MySQL bằng no-op migration.
- Đã xóa 19 `restrictOnDelete()` vì Oracle không hỗ trợ `ON DELETE RESTRICT`.
- Đã chạy thành công `php artisan migrate:fresh` trên Oracle bằng PHP Laragon và `TNS_ADMIN` cục bộ.

### Giai đoạn 4: Seeder

- Sửa `CleanupSeeder` sang xóa theo thứ tự FK.
- Thêm seed payment cho order đã thanh toán.
- Ghi tổng tiền rõ ràng vì trigger tính tiền đang hoãn.
- Thêm logic đồng bộ sequence Oracle sau khi seed ID cố định.
- Chưa chạy được `php artisan migrate:fresh --seed` vì thiếu PHP trong `PATH`.

### Giai đoạn 5: Model

- Thêm metadata integer PK vào các model.
- Thêm model `Payment`.
- Thêm alias relationship mà controller/API đang gọi.

### Giai đoạn 6: Controller và repository

- API booking không còn tự sinh string ID.
- API payment không còn tự sinh string `payment_id`.
- Profile API không còn tự sinh pet ID kiểu chuỗi.
- Web payment repository tạo/cập nhật bảng `payments`.
- Payment flow chấp nhận cả `COMPLETED` và `PAID` là đã thanh toán.

### Giai đoạn 7: Quyết định với `database_mau`

- APPLY_NOW: constraint cơ bản tương thích web, FK hiện tại, room status, bảng payments.
- APPLY_AFTER_FIX: thống nhất order status, service booking status, trigger thanh toán, function kiểm tra overlap.
- KEEP_WEB_VERSION: `users`, integer ID, schema booking/customer/product/room hiện tại, role text.
- SKIP_FOR_NOW: `app_user`, ID `VARCHAR2`, bảng kho nâng cao, composite PK lớn, procedure/trigger chưa dùng.

### Giai đoạn 8: Test

Đã scan tĩnh code chạy chính:

- Không còn `FOREIGN_KEY_CHECKS`.
- Không còn trigger MySQL chạy thật.
- Không còn `enum()` MySQL trong migration.
- Không còn sinh ID `BKG`, `BKR`, `BSP` trong app flow.
- Không còn ép PHPUnit dùng SQLite in-memory.

Ban đầu chưa test runtime vì PHP/Composer/Node/NPM chưa nằm trong `PATH`. Sau đó đã chạy được bằng đường dẫn đầy đủ của Laragon.

Cập nhật chạy hệ thống ngày 2026-05-24:

- `composer install` chạy thành công bằng PHP/Composer của Laragon.
- `.env` đã được tạo từ `.env.example` tại máy local.
- `php artisan key:generate` chạy thành công.
- `php artisan config:clear`, `route:clear`, `view:clear` chạy thành công.
- `php artisan cache:clear` và `php artisan migrate:fresh --seed` từng bị chặn bởi Oracle credential.
- Sau khi set `TNS_ADMIN` trỏ vào cấu hình cục bộ, lỗi `ORA-12638` đã hết.
- Lỗi còn lại là `ORA-01017`, do `.env` đang dùng password mẫu `your_password` hoặc user `PET_HOTEL` chưa đúng/chưa tồn tại.
- Sau khi `.env` có credential Oracle hợp lệ, `php artisan migrate:fresh` chạy thành công.
- Sau khi migration tạo xong bảng cache, `php artisan cache:clear` chạy thành công.
- Lỗi `ORA-02000: missing CASCADE keyword` được xử lý bằng cách xóa `restrictOnDelete()` khỏi migration, giữ hành vi restrict mặc định của Oracle.

### Giai đoạn 9: Chuẩn bị Git

- `.env` không tồn tại trong workspace và không bị commit.
- `.env.example`, README, workflow, config, migration, seeder, model, controller, repository, `.gitignore` đã cập nhật.
- `pethotel_db` vẫn là SQLite artifact đang được Git track. Cần xóa khỏi Git trước khi push nếu project không cần giữ file này.
