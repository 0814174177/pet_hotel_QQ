# Pet Hotel Laravel - Oracle Setup

Project da duoc chuan bi de chay voi Oracle Database thong qua package
`yajra/laravel-oci8`. Oracle Database duoc gia dinh la da co san; nguoi clone
project chi can cau hinh schema/user Oracle trong `.env`.

## Yeu Cau

- PHP 8.2 tro len
- Composer
- Node.js va npm
- PHP extension `oci8` da bat
- Oracle Database service dang chay, vi du `FREEPDB1`
- Oracle user/schema, vi du `PET_HOTEL`

Kiem tra PHP extension:

```bash
php -m
php --ri oci8
```

## Cai Dat

```bash
git clone <repo-url>
cd <project-folder>
composer install
npm install
npm run build
cp .env.example .env
php artisan key:generate
```

Tren Windows PowerShell co the copy env bang:

```powershell
Copy-Item .env.example .env
```

## Cau Hinh Oracle

Sua `.env` theo Oracle hien co:

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

Khong commit `.env` that hoac password Oracle that.

Neu can tao user/schema Oracle, chay bang tai khoan co quyen DBA:

```sql
CREATE USER PET_HOTEL IDENTIFIED BY your_password;
GRANT CONNECT, RESOURCE TO PET_HOTEL;
ALTER USER PET_HOTEL QUOTA UNLIMITED ON USERS;
```

## Chay Migration, Seeder Va Web

```bash
php artisan config:clear
php artisan cache:clear
php artisan route:clear
php artisan view:clear
php artisan migrate:fresh --seed
php artisan serve
```

Mo trinh duyet:

```text
http://127.0.0.1:8000
```

Tai khoan seed mac dinh dung password `password123`, vi du:

- `admin@pethotel.test`
- `manager@pethotel.test`
- `customer1@pethotel.test`

## Ghi Chu Oracle Migration

Chi tiet qua trinh chuyen doi, cac constraint da ap dung/chua ap dung, va cac
buoc test nam trong `WORKFLOW_ORACLE_MIGRATION.md`.
