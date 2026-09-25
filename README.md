# Simie Home Calculator (simiehome_tool)

Công cụ tính giá và lập báo giá nội thất cho Simie Home: tính giá theo sản phẩm, sản phẩm phụ, phụ phí, giảm giá (theo số tiền hoặc %), lưu lịch sử báo giá và xuất PDF.

**Công nghệ:** Laravel 12 · PHP 8.2+ · Vite + Tailwind + Alpine.js · SQLite (local) / MySQL (production)

---

## 1. Chạy dưới local (macOS)

### Cài đặt lần đầu

1. Cài PHP 8.2+, Composer, Node.js. Dễ nhất là cài [Laravel Herd](https://herd.laravel.com) (có sẵn PHP + Composer), hoặc dùng Homebrew:

   ```bash
   brew install php composer node
   ```

2. Cài thư viện và chuẩn bị database:

   ```bash
   cd ~/Workspace/simiehome_tool
   composer install
   npm install

   # Chỉ khi chưa có file .env
   cp .env.example .env
   php artisan key:generate

   # Local dùng SQLite (DB_CONNECTION=sqlite trong .env)
   touch database/database.sqlite
   php artisan migrate
   php artisan storage:link
   ```

### Chạy dự án

Cách nhanh — chạy web, queue, log và Vite cùng lúc trong một cửa sổ:

```bash
composer run dev
```

Hoặc chạy riêng bằng 2 cửa sổ Terminal:

```bash
php artisan serve   # cửa sổ 1: web server
npm run dev         # cửa sổ 2: Vite (CSS/JS tự cập nhật khi sửa code)
```

Mở **http://127.0.0.1:8000**. Nhấn `Ctrl + C` để dừng.

> Trang mất CSS/JS → chưa chạy `npm run dev` (hoặc chạy `npm run build` một lần).

### Chạy test

```bash
php artisan test
```

---

## 2. Tài khoản admin

Tạo mới hoặc đặt lại mật khẩu admin (chạy được cả local lẫn VPS):

```bash
php artisan tinker --execute="App\Models\User::query()->updateOrCreate(['email'=>'admin@simiehome.com'], ['name'=>'Admin','password'=>'MatKhauManh@123'])->forceFill(['role'=>'admin'])->save(); echo 'OK';"
```

Sau khi có admin, tạo các tài khoản khác (sales / manager) tại trang **`/admin/users`**.

---

## 3. Import sản phẩm

Với file CSV lớn, **import bằng dòng lệnh** thay vì qua web (tải ảnh qua web dễ bị timeout 502):

```bash
php artisan products:import storage/app/products.csv --skip-images   # nhập dữ liệu
php artisan products:cache-images                                    # tải ảnh (chạy lại an toàn)
```

---

## 4. Deploy lên VPS (CyberPanel / nginx)

Code nằm **ngoài** `public_html`; chỉ thư mục `public/` được public.

```
/home/calculator.simiehome.com/
├── simiehome_tool/      ← code (git clone)
└── public_html → simiehome_tool/public   (symlink, hoặc đặt Document Root = public/)
```

### Cài đặt lần đầu

```bash
cd /home/calculator.simiehome.com
git clone https://github.com/GioanNguyen/simiehome_tool.git
cd simiehome_tool
git config core.fileMode false          # tránh git báo "modified" khi chmod

composer install --no-dev --optimize-autoloader
npm ci && npm run build                 # hoặc build trên Mac rồi upload public/build

cp .env.example .env && nano .env       # xem mục cấu hình bên dưới
php artisan key:generate
php artisan migrate --force
php artisan storage:link

# Trỏ public_html vào public/
cd .. && mv public_html public_html_old && ln -s "$PWD/simiehome_tool/public" public_html
```

### Cấu hình `.env` production

```dotenv
APP_ENV=production
APP_DEBUG=false
APP_URL=https://calculator.simiehome.com

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=ten_database
DB_USERNAME=ten_user
DB_PASSWORD="mat_khau"
```

Database và user MySQL tạo trong CyberPanel/cPanel, quản lý bằng phpMyAdmin.

### Quyền thư mục

PHP chạy bằng **user của website**, không phải root. Xem user đó:

```bash
stat -c '%U:%G' /home/calculator.simiehome.com
```

Nếu chạy lệnh bằng root, luôn trả quyền lại sau đó (thay `calcu1234` bằng kết quả trên):

```bash
chown -R calcu1234:calcu1234 .
chmod -R 775 storage bootstrap/cache
```

### Cron (scheduler + queue)

`crontab -u calcu1234 -e`:

```cron
* * * * * cd /home/calculator.simiehome.com/simiehome_tool && php artisan schedule:run >> /dev/null 2>&1
* * * * * cd /home/calculator.simiehome.com/simiehome_tool && php artisan queue:work --stop-when-empty --max-time=55 >> /dev/null 2>&1
```

### Cập nhật code (mỗi lần deploy)

Sửa code trên máy → `git push`, rồi trên VPS chạy `./deploy.sh`:

```bash
#!/bin/bash
set -e
cd /home/calculator.simiehome.com/simiehome_tool
OWNER=calcu1234:calcu1234

git pull
composer install --no-dev -o --no-interaction
npm ci && npm run build
php artisan migrate --force
php artisan optimize

chown -R $OWNER .
chmod -R 775 storage bootstrap/cache
echo "Deploy xong"
```

> Không sửa file trực tiếp trên VPS (trừ `.env`) để `git pull` không bị xung đột.

---

## 5. Xử lý lỗi thường gặp

| Lỗi | Nguyên nhân / cách xử lý |
|---|---|
| **500** | Xem `tail -n 30 storage/logs/laravel.log` |
| `Unknown column ...` | Chưa chạy `php artisan migrate --force` |
| `Vite manifest not found` | Chưa build JS/CSS: `npm run build` |
| `Unable to create a directory` / `Permission denied` | Sai quyền: chạy lại `chown -R` + `chmod -R 775 storage bootstrap/cache` |
| **502** khi import | Request quá lâu → import bằng dòng lệnh (mục 3) |
| `git pull`: *local changes would be overwritten* | `git config core.fileMode false`, hoặc `git checkout -- .` rồi pull lại |
| `index.lock: File exists` | `rm -f .git/index.lock` |
