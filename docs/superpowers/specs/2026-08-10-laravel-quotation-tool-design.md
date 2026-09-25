# Laravel Quotation Tool Design

## Overview

Xay dung mot web noi bo bang Laravel de sales/admin nhap nhanh thong tin bo ban an va tinh gia bao gia ngay lap tuc. He thong lay du lieu san pham tu WooCommerce, dong bo dinh ky ve database Laravel, sau do Laravel tu tra cuu de goi y san pham va tinh gia.

MVP uu tien:

- Don gian, nhanh dung, de van hanh
- Khong phu thuoc WordPress trong luc bao gia
- Co the mo rong them cong thuc, vai tro, va dau ra PDF/API sau nay

## Goals

- Cho phep admin/sales dang nhap va tao bao gia nhanh
- Goi y ten ban va ghe theo du lieu da dong bo tu WooCommerce
- Tinh gia bo ban an theo cong thuc da xac nhan
- Luu lich su bao gia de xem lai
- Co co che dong bo dinh ky va theo doi tinh trang dong bo

## Non-Goals For MVP

- Khach ngoai tu bao gia tren web cong khai
- Phan quyen phuc tap nhieu cap
- Dong bo 2 chieu nguoc lai WordPress
- Quan ly don hang, thanh toan, CRM
- PDF mau dep hoac xuat file nang cao

## Users

### Primary Users

- Admin noi bo
- Sales noi bo

### Usage Context

- Nhan vien dang nhap vao he thong noi bo
- Mo mot man hinh bao gia chinh
- Nhap ten ban, kich thuoc, ten ghe, so luong, va phi rieng
- Xem tong gia ngay lap tuc
- Luu lai bao gia de tham khao hoac gui khach

## Product Data Source

Nguon du lieu san pham la WooCommerce product chuan, co:

- SKU
- Gia
- Ten san pham
- Thuoc tinh san pham

Laravel se dong bo dinh ky du lieu nay ve database rieng. Toan bo autocomplete va tinh gia trong MVP se doc tu database Laravel, khong goi WordPress realtime khi nguoi dung thao tac.

## Recommended Tech Stack

- Laravel 12
- PHP 8.3+
- Blade
- Alpine.js
- MySQL
- Laravel Scheduler + Queue
- Laravel Breeze hoac auth scaffold toi gian

Ly do:

- Blade + Alpine.js du cho form dong va autocomplete co ban, nhe hon Livewire
- De ba tri, de tim dev Laravel thong thuong
- De mo rong thanh API-first sau nay neu can

## High-Level Architecture

He thong gom 4 khoi chinh:

1. `Authentication`
Chi quan ly dang nhap noi bo cho admin/sales.

2. `Catalog Sync`
Dong bo san pham WooCommerce ve Laravel theo lich.

3. `Quotation Engine`
Nhap lieu, tra cuu san pham, tinh gia, luu bao gia.

4. `Admin Support`
Theo doi log dong bo va quan ly phu phi/rule gia bo sung.

## Core User Flow

### Login

- Nguoi dung dang nhap bang tai khoan noi bo

### Create Quote

- Nguoi dung mo man hinh bao gia
- Nhap ten ban
- He thong hien danh sach goi y theo ten da sync
- Nguoi dung chon ban
- Nhap kich thuoc theo cm
- Nhap ten ghe loai 1 va so luong
- Nhap ten ghe loai 2 va so luong
- Nhap chi phi rieng nhu ship, phu thu, ghe bang, hoac muc cong them
- He thong tinh tong theo realtime
- Nguoi dung bam luu bao gia

### Review Past Quotes

- Nguoi dung xem danh sach bao gia da tao
- Mo lai chi tiet bao gia de tham khao

## Pricing Rules

### Confirmed Formula

Gia ban:

`table_total = (table_base_price * table_size_cm / 100) + table_sku_surcharge`

Gia ghe moi loai:

`chair_total = chair_base_price * quantity`

Tong gia bo:

`set_total = table_total + chair_type_1_total + chair_type_2_total + manual_extra_fee`

### Notes

- Tat ca truong nhap deu co the de trong, nhung he thong chi tinh phan co du du lieu
- Neu khong chon ghe loai 2 thi phan do bang 0
- `manual_extra_fee` la tong phi nhap tay bo sung
- SKU surcharge la phu phi rieng theo SKU, luu tai Laravel de admin dieu chinh duoc

## Data Model

### 1. products

Muc dich: luu ban sao san pham tu WooCommerce.

Truong toi thieu:

- `id`
- `wordpress_product_id`
- `sku`
- `name`
- `product_type`
- `base_price`
- `status`
- `attributes_json`
- `last_synced_at`
- `created_at`
- `updated_at`

Ghi chu:

- `product_type` trong MVP nen map don gian thanh `table`, `chair`, `other`
- `attributes_json` giu du lieu goc de mo rong ve sau

### 2. product_pricing_rules

Muc dich: luu phu phi bo sung va rule override don gian theo SKU.

Truong toi thieu:

- `id`
- `product_id`
- `sku`
- `applies_to`
- `surcharge_amount`
- `active`
- `notes`
- `created_at`
- `updated_at`

Ghi chu:

- `applies_to` nhan gia tri `table`, `chair`, `all`
- MVP chi can `surcharge_amount`
- Co the them cac rule nang cao sau nay ma khong pha bang nay

### 3. quotes

Muc dich: luu phien bao gia.

Truong toi thieu:

- `id`
- `quote_code`
- `user_id`
- `table_product_id`
- `table_sku`
- `table_name`
- `table_size_cm`
- `manual_extra_fee`
- `table_total`
- `chair_1_total`
- `chair_2_total`
- `grand_total`
- `notes`
- `created_at`
- `updated_at`

### 4. quote_items

Muc dich: luu chi tiet tung thanh phan ghe va co the mo rong them item sau nay.

Truong toi thieu:

- `id`
- `quote_id`
- `item_role`
- `product_id`
- `sku`
- `product_name`
- `unit_price`
- `quantity`
- `line_total`
- `meta_json`
- `created_at`
- `updated_at`

Ghi chu:

- `item_role` trong MVP dung `chair_type_1`, `chair_type_2`, `manual_fee`
- Cach nay giup mo rong thanh nhieu dong item ve sau ma khong doi cong thuc tong quat

### 5. sync_runs

Muc dich: theo doi moi lan dong bo.

Truong toi thieu:

- `id`
- `source`
- `status`
- `started_at`
- `finished_at`
- `processed_count`
- `success_count`
- `failed_count`
- `message`
- `created_at`
- `updated_at`

## WooCommerce Synchronization Design

### Sync Strategy

- Chay theo lich bang Laravel Scheduler
- Scheduler kich hoat Job dong bo
- Moi lan sync doc du lieu WooCommerce qua REST API
- Upsert vao bang `products`
- Ghi log vao `sync_runs`

### MVP Sync Scope

Dong bo:

- Product ID
- SKU
- Ten san pham
- Gia hien tai
- Thuoc tinh
- Trang thai

Chua can dong bo:

- Anh
- Mo ta dai
- Bien the phuc tap neu chua can cho bao gia

### Failure Handling

- Neu sync loi, he thong luu log loi trong `sync_runs`
- Du lieu cu trong Laravel van duoc giu de sales tiep tuc bao gia
- Admin co the bam chay lai sync thu cong tu man admin

## Application Screens

### 1. Login Screen

- Dang nhap email/password

### 2. Quote Calculator Screen

Day la man hinh chinh cua MVP.

Thanh phan:

- O nhap ten ban co goi y
- O nhap kich thuoc cm
- O nhap ten ghe loai 1 co goi y
- O nhap so luong ghe loai 1
- O nhap ten ghe loai 2 co goi y
- O nhap so luong ghe loai 2
- O nhap phi rieng
- Khu vuc hien chi tiet cach tinh
- Khu vuc hien tong gia cuoi
- Nut luu bao gia

### 3. Quotes History Screen

- Danh sach bao gia da tao
- Loc co ban theo nguoi tao va ngay tao
- Xem chi tiet bao gia

### 4. Sync Admin Screen

- Xem lan sync gan nhat
- Xem trang thai sync
- Chay sync thu cong
- Xem so luong ban ghi dong bo

### 5. Pricing Rules Screen

- Danh sach SKU co phu phi
- Sua phu phi theo SKU
- Bat/tat rule

## Autocomplete Behavior

Autocomplete phai uu tien toc do va don gian:

- Tim theo `name` va `sku`
- Debounce nhe o frontend
- Gioi han so ket qua tra ve
- Hien `name + sku + base_price`

MVP khong can search fuzzy phuc tap. Chi can search prefix/contains o muc co ban va co index phu hop trong database.

## Validation Rules

- `table_name` khong bat buoc, nhung neu nguoi dung muon tinh gia ban thi phai chon duoc mot san pham hop le
- `table_size_cm` neu nhap thi phai la so duong
- `chair quantities` neu nhap thi phai la so nguyen khong am
- `manual_extra_fee` neu nhap thi phai la so khong am

Validation can theo huong "cho phep nhap thieu" de phu hop nhu cau nhap nhanh, nhung khong duoc tinh sai.

## Business Rules

- Bao gia duoc tinh dua tren snapshot gia tai thoi diem tao quote
- Neu gia san pham trong WooCommerce thay doi sau do, bao gia cu khong bi thay doi
- Bao gia luu ca ten va SKU text de tranh mat lich su khi product bi doi ten hoac xoa
- Chi user da dang nhap moi duoc truy cap he thong

## Security

- Tat ca route bao gia va admin deu can auth
- Thong tin ket noi WooCommerce luu trong env
- Ghi log loi khong de lo credentials
- MVP co the dung 1 role noi bo chung, chua can RBAC day du

## Performance Requirements

- Tra ket qua autocomplete trong muc nhanh cho nhu cau noi bo
- Tinh tong gia realtime khong can reload trang
- Dong bo chay nen, khong anh huong man hinh sales dang dung

## Extensibility Path

Kien truc MVP phai ho tro mo rong:

- Them nhieu loai ghe hon 2 loai
- Them rule gia theo khoang kich thuoc
- Them xuat PDF bao gia
- Them role dai ly/cong tac vien
- Them API cho he thong khac goi den

De dat muc tieu nay, phan quote item duoc tach bang rieng, va product sync duoc luu noi bo thay vi phu thuoc WordPress realtime.

## Error Handling

- Neu khong tim thay product, khong tinh phan lien quan va hien thong bao ro
- Neu WooCommerce tam thoi loi, he thong van dung du lieu sync cu
- Neu user nhap gia tri sai dinh dang, hien loi ngay tai truong nhap

## Testing Strategy

MVP nen co test cho cac nhom sau:

- Unit test cong thuc tinh gia
- Feature test cho login va route bao ve
- Feature test cho autocomplete API
- Feature test cho luu bao gia
- Feature test cho sync job voi du lieu WooCommerce mock

## Delivery Phases

### Phase 1: Foundation

- Khoi tao Laravel
- Auth noi bo
- Database schema

### Phase 2: Catalog Sync

- Tich hop WooCommerce REST API
- Job dong bo
- Log sync

### Phase 3: Quote Calculator

- Form bao gia
- Autocomplete
- Tinh gia realtime
- Luu quote

### Phase 4: Admin Support

- History quotes
- Sync dashboard
- Pricing rules

## Recommended MVP Decisions

- Dung Blade + Alpine.js thay vi SPA
- Dung sync dinh ky thay vi goi WordPress realtime
- Dung bang `quote_items` ngay tu dau de tranh bi khoa cung voi 2 loai ghe
- Dung 1 role auth noi bo chung cho MVP
- Dung pricing rules toi gian theo SKU de bao toan tinh mo rong

## Open Decisions Closed For This Spec

Nhung diem sau da duoc xac nhan va khoa trong spec nay:

- Nguoi dung MVP la admin/sales noi bo
- Nguon du lieu la WooCommerce product chuan
- Dong bo dinh ky tu WordPress ve Laravel
- Cong thuc gia ban va ghe nhu muc Pricing Rules
- Uu tien kien truc toi gian, de mo rong ve sau

## Success Criteria

MVP duoc xem la dat khi:

- Sales co the dang nhap va tao bao gia trong 1 man hinh chinh
- He thong goi y duoc ban/ghe tu du lieu WooCommerce da sync
- Tong gia tinh dung theo cong thuc da chot
- Bao gia luu lai duoc voi lich su ro rang
- Admin xem duoc tinh trang dong bo va cap nhat phu phi SKU
