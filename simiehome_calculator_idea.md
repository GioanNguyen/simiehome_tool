# Ý tưởng triển khai Calculator báo giá nội thất – Simie Home

## 1. Mục tiêu

Xây dựng một website riêng `calculator.simiehome` bằng Laravel để nhân viên tư vấn có thể tạo nhanh một **bộ nội thất tùy chọn** cho khách hàng, thay vì chỉ lấy giá của một sản phẩm có sẵn trên website.

Luồng tổng quát:

> Chọn/tìm sản phẩm → nhập kích thước → chọn sản phẩm phụ → nhập số lượng → cộng các phụ phí → tính giá bộ → lưu/in/gửi báo giá.

Mục tiêu của hệ thống là **hỗ trợ báo giá**, không thay thế hệ thống bán hàng hiện tại trên Simie Home.

---

## 2. Hiện trạng website Simie Home

Qua khảo sát website hiện tại, Simie Home đang có nhiều nhóm sản phẩm như bàn, ghế, giường, gương, kệ, tủ... và nhiều sản phẩm có biến thể.

Một số sản phẩm trên website có:

- Tên sản phẩm.
- Danh mục.
- Giá bán/giá khuyến mãi.
- Biến thể màu sắc/kích thước.
- SKU ở một số sản phẩm.
- Mô tả.
- Hình ảnh.
- Sản phẩm có thể điều chỉnh kích thước/màu sắc theo nhu cầu.

Ví dụ hiện trạng website cho thấy có sản phẩm có SKU cụ thể như `T01`, `SO45`, nhưng cũng có sản phẩm hiển thị `SKU: N/A`.

**Điểm cần chú ý:** Calculator muốn tính giá theo SKU nên dữ liệu export cần có một mã định danh ổn định cho từng sản phẩm/đơn giá. Việc dùng tên sản phẩm làm mã chính không nên mặc định nếu tên có thể thay đổi.

---

## 3. Ý tưởng giao diện Calculator

### 3.1. Màn hình tạo báo giá

Đề xuất bố cục:

```text
┌─────────────────────────────────────────────────────────────┐
│ SIMIE HOME – CALCULATOR                                     │
├─────────────────────────────────────────────────────────────┤
│ Thông tin khách hàng                                        │
│ Tên khách: [________________]  SĐT: [______________]       │
│ Mã báo giá: [___________]      Ngày: [__/__/____]           │
├─────────────────────────────────────────────────────────────┤
│ SẢN PHẨM CHÍNH                                               │
│                                                             │
│ Tìm sản phẩm: [ Bàn ăn Min________________ ]                │
│                                                             │
│ Sản phẩm: Bàn ăn Min                                        │
│ SKU: _________                                              │
│                                                             │
│ Kích thước:                                                 │
│ Dài [140] cm   Rộng [80] cm   Cao [75] cm                  │
│                                                             │
│ Số lượng: [1]                                               │
│                                                             │
│ Đơn giá cơ sở: __________ đ                                 │
│ Phụ phí:       __________ đ                                 │
│ Thành tiền:    __________ đ                                 │
│                                                             │
├─────────────────────────────────────────────────────────────┤
│ SẢN PHẨM PHỤ                                                 │
│                                                             │
│ [+ Thêm sản phẩm phụ]                                       │
│                                                             │
│ 1. Ghế Hoti       SL [6]     Đơn giá ____     Thành tiền ___│
│ 2. __________________       SL [ ]           _______________│
│                                                             │
├─────────────────────────────────────────────────────────────┤
│ PHỤ PHÍ / CHI PHÍ KHÁC                                      │
│                                                             │
│ Phí ship       [____________]                               │
│ Phí lắp đặt    [____________]                               │
│ Phụ phí khác   [____________]                               │
│                                                             │
├─────────────────────────────────────────────────────────────┤
│ TỔNG GIÁ BỘ:                              00.000.000 đ       │
│                                                             │
│ [Lưu báo giá] [In PDF] [Xuất Excel] [Gửi khách]             │
└─────────────────────────────────────────────────────────────┘
```

Đây là wireframe ý tưởng, chưa phải thiết kế UI cuối cùng.

---

## 4. Công thức theo sơ đồ được cung cấp

Sơ đồ hiện tại đề xuất cấu trúc:

```text
Tên bàn
+
Kích thước
+
Tên ghế loại 1
+
Số lượng ghế loại 1
+
Tên ghế loại 2
+
Số lượng ghế loại 2
+
Giá trị riêng
=
Giá bộ
```

Trong đó phần giá sản phẩm chính theo sơ đồ đang minh họa:

```text
Giá = Đơn giá × Kích thước / 100 + Phụ phí
```

Ví dụ trong sơ đồ:

```text
Đơn giá = 4.000.000
Kích thước = 140 cm
Phụ phí = 500.000

Giá = 4.000.000 × 140 / 100 + 500.000
    = 6.100.000
```

**Lưu ý:** công thức trên mới được xem là công thức minh họa theo sơ đồ. Chưa nên lập trình cứng cho toàn bộ sản phẩm trước khi xác nhận quy tắc tính giá thực tế của từng nhóm sản phẩm.

---

# 5. Kiến trúc dữ liệu đề xuất

## 5.1. Bảng `products`

Lưu dữ liệu sản phẩm được import từ Simie Home.

Các trường dự kiến:

```text
id
external_id
sku
name
slug
category_id
description
image
status
source_updated_at
created_at
updated_at
```

Trong đó:

- `external_id`: ID sản phẩm ở hệ thống nguồn nếu có.
- `sku`: mã sản phẩm dùng để mapping.
- `name`: tên hiển thị.
- `category_id`: nhóm sản phẩm.
- `status`: active/inactive.
- `source_updated_at`: thời điểm dữ liệu nguồn được cập nhật.

---

## 5.2. Bảng `product_prices`

Không nên chỉ lưu một giá duy nhất trong `products`.

Nên tách bảng giá để sau này có thể quản lý nhiều quy tắc giá.

Dự kiến:

```text
id
product_id
price_type
base_price
unit
formula_type
formula_config
effective_from
effective_to
status
created_at
updated_at
```

Ví dụ:

```text
product_id = 123
price_type = custom_size
base_price = 4000000
unit = 100cm
formula_type = length_ratio
```

---

## 5.3. Bảng `product_options`

Dùng cho các lựa chọn đi kèm sản phẩm:

```text
id
product_id
option_type
option_name
option_value
price_adjustment
status
```

Có thể dùng cho:

- Màu gỗ.
- Màu vải.
- Loại mặt đá.
- Kích thước.
- Loại chân.
- Loại phụ kiện.
- Các lựa chọn khác.

---

## 5.4. Bảng `pricing_rules`

Đây là bảng quan trọng nếu hệ thống có nhiều công thức tính giá.

Ví dụ:

```text
id
name
code
product_category_id
formula_type
formula_config
status
```

Các loại công thức có thể phát triển:

```text
fixed
quantity
length_ratio
area
volume
base_plus_fee
custom
```

Ví dụ:

### Công thức theo chiều dài

```text
base_price × length / 100 + fee
```

### Công thức theo số lượng

```text
unit_price × quantity
```

### Công thức diện tích

```text
unit_price × width × length / 10000
```

### Công thức giá cố định

```text
fixed_price × quantity
```

**Các công thức trên chỉ là khả năng kiến trúc. Chỉ triển khai công thức nào Simie xác nhận thực tế.**

---

# 6. Cấu trúc báo giá

Nên có khái niệm `quotes`.

## Bảng `quotes`

```text
id
quote_code
customer_name
customer_phone
customer_email
note
subtotal
shipping_fee
installation_fee
other_fee
discount
total
status
created_by
created_at
updated_at
```

## Bảng `quote_items`

```text
id
quote_id
product_id
product_name_snapshot
sku_snapshot
quantity
width
length
height
unit_price
formula_type
formula_snapshot
extra_fee
subtotal
note
```

Việc lưu `snapshot` rất quan trọng.

Ví dụ hôm nay báo giá:

```text
Bàn X
Đơn giá: 4.000.000
Kích thước: 140
```

Sau này admin thay đơn giá thành:

```text
4.500.000
```

thì báo giá cũ vẫn phải giữ nguyên giá 4.000.000.

---

# 7. Mô hình sản phẩm chính + sản phẩm phụ

Nên thiết kế Calculator theo mô hình:

```text
Quote
│
├── Main Item
│   ├── Product
│   ├── Size
│   ├── Quantity
│   ├── Formula
│   └── Extra Fee
│
├── Add-on Item
│   ├── Product
│   ├── Quantity
│   └── Price
│
├── Add-on Item
│   ├── Product
│   ├── Quantity
│   └── Price
│
└── Other Fees
    ├── Shipping
    ├── Installation
    └── Other
```

Điều này giúp sau này không bị giới hạn chỉ cho **bàn + ghế**.

Có thể sử dụng cho:

```text
Bàn + ghế
Giường + tab
Sofa + bàn
Tủ + phụ kiện
Kệ + phụ kiện
Combo nội thất
```

---

# 8. Tìm kiếm sản phẩm

Khi nhân viên nhập:

```text
"Bàn Mi..."
```

hệ thống hiển thị:

```text
Bàn Min
SKU: ...
Bàn Mino
SKU: ...
Bàn ...
```

Nên hỗ trợ:

- Tìm theo tên.
- Tìm theo SKU.
- Tìm theo danh mục.
- Tìm không dấu.
- Autocomplete.
- Hiển thị hình ảnh nhỏ.
- Hiển thị giá cơ sở.
- Chỉ hiển thị sản phẩm đang active.

---

# 9. Nhập kích thước

Không nên mặc định tất cả sản phẩm đều có cùng một kiểu kích thước.

Nên có cấu hình theo sản phẩm:

```text
dimension_type

length
width
height
length_width
length_width_height
custom
none
```

Ví dụ:

### Bàn

```text
Dài: 140
Rộng: 80
Cao: 75
```

### Tủ

```text
Rộng: 200
Sâu: 60
Cao: 240
```

### Ghế

```text
Không cần nhập kích thước
```

### Sản phẩm đặc biệt

```text
Kích thước theo cấu hình riêng
```

**Cần xác nhận quy tắc này trước khi triển khai database và UI.**

---

# 10. Import dữ liệu từ Simie Home

Có thể thiết kế hệ thống theo hướng:

```text
Simie Home
     │
     │ Export
     ▼
CSV / Excel / API
     │
     ▼
Laravel Import
     │
     ▼
Validation
     │
     ▼
Mapping SKU
     │
     ▼
Products
     │
     ▼
Pricing Rules
     │
     ▼
Calculator
```

## Nên có trang Admin Import

Ví dụ:

```text
Admin
└── Import sản phẩm

File: [ products.csv ]

[Kiểm tra dữ liệu]

Tổng dòng: 500
Hợp lệ: 485
Lỗi: 15

[Import]
```

Các lỗi cần cảnh báo:

```text
- SKU trống
- SKU trùng
- Giá không hợp lệ
- Sản phẩm không có tên
- Danh mục không tồn tại
- Công thức giá chưa được mapping
```

Không nên cho import trực tiếp vào dữ liệu chính mà không qua bước validation.

---

# 11. Mapping SKU

Sơ đồ người dùng cung cấp đề cập đến việc:

> file rời do admin cung cấp có mã liên kết với tên trên website, mã SKU dùng để mapping sản phẩm và lấy đơn giá.

Vì vậy nên có một lớp mapping rõ ràng:

```text
Website Product
       │
       │ SKU
       ▼
Calculator Product
       │
       ▼
Pricing Rule
       │
       ▼
Calculated Price
```

Ví dụ:

```text
SKU: BAN-MIN
Tên: Bàn ăn Min
Đơn giá cơ sở: 4.000.000
Công thức: length_ratio
Phụ phí mặc định: 500.000
```

**Không nên mapping chỉ bằng tên sản phẩm nếu có SKU hoặc external ID ổn định.**

---

# 12. Admin quản lý công thức giá

Đây nên là một chức năng riêng.

Ví dụ:

```text
Danh sách sản phẩm

Bàn Min
SKU: BAN-MIN
Công thức: Theo chiều dài
Đơn giá: 4.000.000
Phụ phí mặc định: 500.000

[Chỉnh sửa]
```

Admin có thể thay đổi:

```text
Đơn giá
Phụ phí
Công thức
Đơn vị
Trạng thái
```

Mục tiêu là **không phải sửa code Laravel khi thay đổi giá**.

---

# 13. Giá trị riêng / phụ phí

Theo sơ đồ có:

```text
Giá trị riêng
(phí ship, ghế, bàn, sp rời...)
```

Nên thiết kế thành danh sách phí linh hoạt:

```text
Tên phí              Số tiền
--------------------------------
Phí ship              300.000
Phí lắp đặt            500.000
Phí sản phẩm riêng     800.000
Phụ phí khác            200.000
```

Tổng:

```text
Subtotal
+ Shipping
+ Installation
+ Other Fees
- Discount
= Total
```

Cần xác nhận thứ tự tính và cách áp dụng chiết khấu trước khi code.

---

# 14. Giao diện kết quả báo giá

Sau khi tính xong:

```text
==================================================
                  SIMIE HOME
                  BÁO GIÁ
==================================================

Khách hàng: Nguyễn Văn A
SĐT: 090xxxxxxx
Mã báo giá: Q-20260820-001

--------------------------------------------------
STT | Sản phẩm | Kích thước | SL | Đơn giá | Thành tiền
--------------------------------------------------
1   | Bàn Min  | 140x80x75  | 1  | ...     | ...
2   | Ghế Hoti |            | 6  | ...     | ...

--------------------------------------------------

Tạm tính:                         xx.xxx.xxx đ
Phí ship:                            xxx.xxx đ
Phí lắp đặt:                         xxx.xxx đ
Chiết khấu:                          xxx.xxx đ

==================================================
TỔNG CỘNG:                       xx.xxx.xxx đ
==================================================
```

---

# 15. Xuất PDF

Nên có PDF báo giá chuyên nghiệp:

- Logo Simie Home.
- Thông tin khách hàng.
- Mã báo giá.
- Ngày báo giá.
- Hình sản phẩm.
- Tên sản phẩm.
- Kích thước.
- Số lượng.
- Đơn giá.
- Thành tiền.
- Phụ phí.
- Tổng tiền.
- Ghi chú.
- Điều khoản nếu cần.

PDF nên lấy dữ liệu từ `quote` snapshot, không tính lại theo giá hiện tại.

---

# 16. Lịch sử báo giá

Nhân viên có thể xem:

```text
Báo giá
├── Q-20260820-001
├── Q-20260820-002
├── Q-20260820-003
```

Trạng thái:

```text
Draft
Sent
Approved
Rejected
Expired
Cancelled
```

Các chức năng:

```text
Xem
Sửa
Nhân bản
Xuất PDF
In
```

Nên có chức năng **Nhân bản báo giá** để nhân viên nhanh chóng tạo phương án B từ phương án A.

---

# 17. Phân quyền

Đề xuất ban đầu:

## Admin

Có quyền:

- Import sản phẩm.
- Quản lý sản phẩm.
- Quản lý SKU.
- Quản lý công thức.
- Quản lý giá.
- Quản lý phụ phí.
- Xem tất cả báo giá.
- Quản lý người dùng.

## Sales / Tư vấn viên

Có quyền:

- Tìm sản phẩm.
- Tạo báo giá.
- Sửa báo giá của mình.
- Xem lịch sử báo giá được phép xem.
- Xuất PDF.
- Nhân bản báo giá.

## Manager

Có thể:

- Xem tất cả báo giá.
- Duyệt báo giá.
- Xem báo cáo.

Phần phân quyền này cần xác nhận trước khi triển khai.

---

# 18. Công nghệ đề xuất

## Backend

```text
Laravel
PHP
MySQL / MariaDB
Laravel Eloquent
Laravel Validation
Laravel Queue nếu cần import lớn
```

## Frontend

Có thể dùng:

```text
Blade + Livewire
```

hoặc:

```text
Laravel + React
```

hoặc:

```text
Laravel + Vue
```

### Đề xuất cho phiên bản đầu

Nếu Calculator chủ yếu dùng nội bộ bởi nhân viên:

```text
Laravel
+
Livewire
+
Tailwind CSS
+
Alpine.js
```

sẽ giúp giảm độ phức tạp so với xây dựng SPA hoàn toàn.

Nếu sau này Calculator trở thành một ứng dụng lớn có nhiều tương tác realtime, có thể chuyển sang React/Vue.

---

# 19. API nội bộ

Nên thiết kế service/API ở backend ngay từ đầu.

Ví dụ:

```text
GET /api/products/search
GET /api/products/{id}

POST /api/quotes/calculate
POST /api/quotes
GET /api/quotes/{id}

GET /api/pricing-rules
```

Quan trọng nhất:

```text
POST /api/quotes/calculate
```

Backend nhận:

```json
{
  "main_product": {},
  "dimensions": {},
  "quantity": 1,
  "addons": [],
  "fees": []
}
```

và trả:

```json
{
  "items": [],
  "subtotal": 0,
  "fees": 0,
  "discount": 0,
  "total": 0
}
```

**Không nên tin giá do frontend gửi lên. Backend phải tự tính lại.**

---

# 20. Quy tắc bảo mật

Calculator có thể chứa giá nội bộ nên cần:

- Login bắt buộc.
- HTTPS.
- Phân quyền.
- Không expose toàn bộ bảng giá qua public API.
- Backend tự kiểm tra giá.
- Backend tự tính lại tổng tiền.
- Log thay đổi giá.
- Log người tạo/sửa báo giá.
- Không cho user thường sửa công thức giá.
- Validate toàn bộ dữ liệu import.
- CSRF protection.
- Rate limit API nếu mở endpoint.

---

# 21. Versioning giá

Đây là chức năng nên cân nhắc ngay từ đầu.

Ví dụ:

```text
Bàn Min

01/08/2026
Đơn giá: 4.000.000

01/10/2026
Đơn giá: 4.500.000
```

Báo giá cũ vẫn giữ:

```text
4.000.000
```

Báo giá mới sử dụng:

```text
4.500.000
```

Không nên update giá cũ trực tiếp nếu Simie cần tra cứu lịch sử.

---

# 22. Quy trình nghiệp vụ đề xuất

```text
1. Admin export sản phẩm từ Simie Home
            ↓
2. Upload file vào Calculator
            ↓
3. Validate
            ↓
4. Mapping SKU
            ↓
5. Cập nhật sản phẩm / giá
            ↓
6. Sales đăng nhập
            ↓
7. Tạo báo giá
            ↓
8. Chọn sản phẩm chính
            ↓
9. Nhập kích thước
            ↓
10. Hệ thống áp dụng công thức
            ↓
11. Thêm ghế / sản phẩm phụ
            ↓
12. Nhập số lượng
            ↓
13. Thêm phí ship / lắp đặt / phí khác
            ↓
14. Hệ thống tính tổng
            ↓
15. Lưu báo giá
            ↓
16. Xuất PDF
            ↓
17. Gửi khách
```

---

# 23. Giai đoạn triển khai

## Phase 1 – MVP

Mục tiêu: chạy được nghiệp vụ báo giá cơ bản.

- Login.
- Quản lý sản phẩm.
- Import CSV/Excel.
- Mapping SKU.
- Tạo báo giá.
- Chọn sản phẩm chính.
- Nhập kích thước.
- Thêm sản phẩm phụ.
- Nhập số lượng.
- Phụ phí.
- Tính tổng.
- Lưu báo giá.
- Xuất PDF.

## Phase 2

- Lịch sử báo giá.
- Nhân bản báo giá.
- Phân quyền.
- Version giá.
- Quản lý công thức bằng Admin.
- Dashboard.

## Phase 3

Có thể mở rộng:

- Gửi báo giá qua email.
- Gửi link báo giá cho khách.
- Khách xem báo giá online.
- Xác nhận báo giá online.
- Tích hợp CRM.
- Tích hợp đơn hàng.
- Tích hợp website Simie Home.
- API đồng bộ sản phẩm tự động.

---

# 24. Những điểm KHÔNG nên tự đoán khi lập trình

Đây là phần cần Simie xác nhận trước khi bắt đầu thiết kế database chính thức.

## A. Nguồn dữ liệu export

1. Website hiện tại dùng WordPress/WooCommerce hay hệ thống khác?
2. File export sẽ là CSV, Excel hay JSON?
3. Có thể lấy trực tiếp qua WooCommerce REST API không?
4. File export hiện tại có những cột nào?

## B. SKU

5. SKU nào là mã chính để Calculator mapping?
6. Sản phẩm đang `SKU: N/A` sẽ xử lý thế nào?
7. Có SKU riêng cho từng biến thể kích thước/màu sắc không?
8. Nếu một sản phẩm có nhiều biến thể thì Calculator lấy SKU của product hay variation?

## C. Công thức kích thước

9. Công thức trong sơ đồ:

```text
Đơn giá × kích thước / 100 + phụ phí
```

có áp dụng cho tất cả sản phẩm hay chỉ một số nhóm?

10. `kích thước` ở đây chính xác là:
- chiều dài?
- chiều rộng?
- chiều cao?
- tổng một chiều?
- diện tích?
- hay mỗi sản phẩm có công thức riêng?

11. Với sản phẩm cần 2 hoặc 3 kích thước, công thức chính xác là gì?

Ví dụ:

```text
D140 × R80 × C75
```

tính theo chiều nào?

## D. Đơn giá

12. `Đơn giá 4.000.000` trong sơ đồ nghĩa là giá cho:
- 1 mét?
- 100 cm chiều dài?
- một kích thước chuẩn?
- hay một đơn vị khác?

13. Calculator sử dụng giá niêm yết hay giá khuyến mãi trên website?

14. Khi website thay đổi giá, Calculator có tự đồng bộ không?

## E. Phụ phí

15. Phụ phí 500.000 là:
- cố định theo SKU?
- theo kích thước?
- theo vật liệu?
- theo loại sản phẩm?
- hay nhân viên tự nhập?

16. Phí ship tính như thế nào?

17. Phí lắp đặt tính như thế nào?

18. Có chiết khấu không? Nếu có:
- theo %?
- số tiền?
- từng sản phẩm?
- toàn bộ báo giá?

## F. Sản phẩm phụ

19. Sản phẩm phụ có lấy trực tiếp giá từ website không?

20. Ví dụ:

```text
Bàn + 6 ghế
```

giá ghế có phải:

```text
giá SKU ghế × 6
```

hay có giá combo riêng?

21. Có cho phép nhiều loại ghế trong cùng một bộ không?

Ví dụ:

```text
Bàn
+ 4 Ghế Hoti
+ 2 Ghế Bia
```

## G. Báo giá

22. Có cần lưu thông tin khách hàng không?

23. Báo giá có thời hạn không?

24. Có cần mã báo giá tự động không?

25. Có cần PDF không?

26. Có cần gửi link báo giá cho khách không?

27. Khách có cần xem báo giá mà không cần đăng nhập không?

## H. Người dùng

28. Calculator chỉ dùng nội bộ hay khách hàng cũng được sử dụng?

29. Có bao nhiêu nhóm user?

30. Sales có được sửa giá thủ công không?

31. Ai được thay đổi công thức và đơn giá?

---

# 25. Kiến trúc đề xuất sau khi các câu hỏi được xác nhận

```text
                       SIMIE HOME
                           │
                           │ Export / API
                           ▼
                  ┌──────────────────┐
                  │ Product Importer │
                  └────────┬─────────┘
                           │
                           ▼
                  ┌──────────────────┐
                  │    Products      │
                  │      + SKU       │
                  └────────┬─────────┘
                           │
             ┌─────────────┴─────────────┐
             ▼                           ▼
      Pricing Rules                 Product Options
             │                           │
             └─────────────┬─────────────┘
                           ▼
                  ┌──────────────────┐
                  │    Calculator    │
                  └────────┬─────────┘
                           │
              ┌────────────┼────────────┐
              ▼            ▼            ▼
           Main Item     Add-ons       Fees
              │            │            │
              └────────────┼────────────┘
                           ▼
                    Pricing Engine
                           │
                           ▼
                         Quote
                           │
                 ┌─────────┴─────────┐
                 ▼                   ▼
                PDF              History
```

---

# 26. Khuyến nghị quan trọng về Pricing Engine

Không nên viết công thức trực tiếp trong Controller kiểu:

```php
if ($product->sku === 'ABC') {
    ...
}

if ($product->sku === 'XYZ') {
    ...
}
```

Cách này sẽ nhanh chóng khó bảo trì khi có hàng trăm sản phẩm.

Nên xây dựng:

```text
PricingEngine
```

Ví dụ:

```text
PricingEngine
    ├── FixedPriceCalculator
    ├── QuantityCalculator
    ├── LengthCalculator
    ├── AreaCalculator
    └── CustomCalculator
```

Mỗi sản phẩm được cấu hình sử dụng một pricing rule.

Như vậy khi thêm sản phẩm mới, Admin chỉ cần cấu hình:

```text
SKU
+
Giá
+
Công thức
+
Phụ phí
```

thay vì phải sửa code.

---

# 27. Đề xuất MVP thực tế

Để tránh làm hệ thống quá lớn ngay từ đầu, nên bắt đầu với một nhóm nghiệp vụ cụ thể.

Ví dụ MVP:

```text
Bàn
+
Ghế
+
Phụ phí
```

Trong đó:

```text
Bàn:
Tên
SKU
Kích thước
Đơn giá
Phụ phí

Ghế:
Tên
SKU
Số lượng
Đơn giá

Phí:
Ship
Lắp đặt
Khác
```

Sau khi công thức và quy trình chạy ổn mới mở rộng sang:

```text
Giường
Tủ
Kệ
Sofa
Phụ kiện
Combo khác
```

---

# 28. Kết luận

Ý tưởng cốt lõi nên là:

> **Calculator không chỉ là một form cộng tiền, mà là một hệ thống Pricing Engine có khả năng lấy dữ liệu sản phẩm từ Simie Home, mapping bằng SKU, áp dụng công thức theo từng loại sản phẩm và lưu lại báo giá theo phiên bản giá tại thời điểm báo giá.**

Điểm quan trọng nhất trước khi bắt đầu code Laravel là **chốt quy tắc tính giá**.

Đặc biệt cần xác nhận:

```text
1. Cấu trúc file export thực tế.
2. SKU dùng để mapping.
3. Ý nghĩa chính xác của "đơn giá".
4. Công thức kích thước.
5. Công thức riêng của từng nhóm sản phẩm.
6. Cách tính sản phẩm phụ.
7. Cách tính phí ship/lắp đặt/phí khác.
8. Giá niêm yết hay giá khuyến mãi.
9. Quyền chỉnh giá của nhân viên.
10. Có cần PDF và lịch sử báo giá hay không.
```

Sau khi có 10 thông tin này, có thể chuyển sang bước tiếp theo:

```text
Database Schema
        ↓
ERD
        ↓
Laravel Migration
        ↓
Model / Relationship
        ↓
Pricing Engine
        ↓
Import Product
        ↓
Calculator UI
        ↓
Quote / PDF
```
