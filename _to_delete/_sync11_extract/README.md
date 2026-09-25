# Quotation Tool

Laravel tool noi bo de sales/admin tinh nhanh gia bo ban an, import san pham tu file CSV, va luu lich su bao gia.

## Requirements

- PHP 8.3
- Composer
- Node.js + npm
- MySQL cho production; SQLite co the dung cho local/test nhanh
- Goi `barryvdh/laravel-dompdf` (dung de xuat PDF bao gia) — xem buoc cai o phan Local Setup

## Features

- Dang nhap noi bo bang Laravel Breeze
- Import san pham tu file CSV export (Admin upload hoac Artisan command), khong can goi API
- Tim san pham theo `name` hoac `sku`
- Pricing Engine linh hoat theo nhom san pham (catalog price, length ratio, fixed price, quantity), quan ly cong thuc trong admin
- Bao gia gom 1 san pham chinh + so luong tuy y san pham phu + phu phi
- Hinh anh san pham (tu cot `image_url` cua catalog, co san tu CSV import) hien thi khi chon san pham, o man hinh xac nhan, chi tiet bao gia, va trong file PDF
- Man hinh xac nhan truoc khi luu: co the "Quay lai" sua ma khong mat du lieu da nhap, nhap thong tin khach hang (ten/SDT/email)
- Xuat file PDF bao gia tu snapshot da luu (khong tinh lai theo gia hien tai)
- Danh sach bao gia tim kiem theo ngay / nguoi bao gia / ten hoac SDT khach hang, co phan trang (10/20/50)
- Quan ly pricing rules va nguoi dung trong admin
- Dashboard thong ke bao gia (hom nay / thang nay / nam nay) va hoat dong nhan vien sale — xem muc "Dashboard" ben duoi
- Phan quyen theo vai tro (Admin / Sales / Manager) — xem muc "Phan quyen (RBAC)" ben duoi

## Local Setup

1. `composer install`
2. `composer require barryvdh/laravel-dompdf` (can cho tinh nang xuat PDF bao gia)
3. `cp .env.example .env`
4. `php artisan key:generate`
5. `touch database/database.sqlite`
6. `php artisan migrate`
7. `php artisan storage:link` (can cho anh san pham duoc cache local — xem
   muc "Hinh anh san pham" ben duoi)
8. `npm install`
9. `npm run build`
10. `php artisan serve`

## Cap nhat khi pull code moi (khong phai cai lai tu dau)

Sau khi pull nhung thay doi ve man hinh xac nhan / xuat PDF / danh sach bao gia
(va cac ban fix sau do), chay theo thu tu sau. Du lieu san pham / bao gia / quy
tac gia dang co se **khong bi mat** — cac migration moi chi them cot, khong
migrate:fresh.

1. `composer require barryvdh/laravel-dompdf` — chi can chay 1 lan neu chua
   cai (kiem tra nhanh: mo `composer.json`, neu da co dong
   `"barryvdh/laravel-dompdf"` trong `require` thi bo qua buoc nay, chi can
   `composer install`).
2. `php artisan migrate` — ap dung cac migration moi: them cot
   `customer_name`, `customer_phone`, `customer_email` vao bang `quotes`,
   them cot `role` vao bang `users` (xem muc "Phan quyen (RBAC)" —
   migration nay **tu dong gan role `admin` cho user dau tien** trong bang
   `users` de luon co it nhat 1 tai khoan vao duoc `/admin/users`, cac user
   con lai mac dinh la `sales`), **doi ten bang `sync_runs` thanh
   `import_runs`** (giu nguyen du lieu lich su import CSV — xem ghi chu ben
   duoi), va them cot `image_url` vao bang `quote_items` (luu lai anh san
   pham tai thoi diem lap bao gia — xem muc "Hinh anh san pham" ben duoi).
3. `php artisan storage:link` — chi can chay 1 lan neu chua co (kiem tra
   nhanh: neu thu muc `public/storage` da ton tai thi bo qua buoc nay).
   Can cho tinh nang cache anh san pham local (xem muc "Hinh anh san pham"
   ben duoi) — thieu buoc nay thi anh se khong hien duoc du da tai ve.
4. `php artisan products:cache-images` — **chay 1 lan** de tai va luu local
   toan bo anh san pham dang con tro thang ra nguon ben ngoai (vd
   simiehome.com) tu truoc khi co tinh nang nay. Lenh nay co the mat vai
   phut neu catalog co nhieu san pham co anh — chay lai bao nhieu lan cung
   an toan, anh da tai roi se duoc bo qua.
5. `npm run build` — build lai file JS (`quote-calculator.js` thay doi qua
   moi ban cap nhat: man hinh xac nhan, prefill khi "Quay lai", fix mat
   kich thuoc tuy chinh, hien thi anh san pham khi chon).
6. (Tuy chon nhung nen lam) `php artisan view:clear` — xoa cache Blade cu
   neu thay man hinh khong len giao dien moi sau khi pull.
7. (Tuy chon) `php artisan test` — chay lai bo test de xac nhan moi thu
   van hoat dong dung tren may minh.
8. `php artisan serve` — chay lai server nhu binh thuong.

Neu tung chay `php artisan serve` truoc do, nen tat process cu (Ctrl+C hoac
`pkill -f "artisan serve"`) roi chay lai o buoc 8 de chac chan dang dung code
moi nhat.

Sau khi migrate xong, dang nhap bang tai khoan vua duoc gan `admin`, vao
`/admin/users` de tao/gan role dung cho tung nguoi (xem muc ben duoi).

**Da go bo tinh nang dong bo WooCommerce** (`/admin/sync-runs`, job
`SyncWooCommerceProductsJob`, `WooCommerceClient`) — CSV import
(`/admin/product-imports`) la cach duy nhat de nap san pham. Neu `.env` cua
ban con cac bien `WOOCOMMERCE_BASE_URL` / `WOOCOMMERCE_KEY` /
`WOOCOMMERCE_SECRET`, co the xoa, khong con noi nao doc toi nua.

Bang `sync_runs` **khong bi xoa** vi no cung duoc dung de luu lich su import
CSV (khong chi WooCommerce sync) — migration doi ten no thanh `import_runs`
(model `App\Models\ImportRun`) de khong con gan voi ten tinh nang da go bo.
Lich su import cu (neu co) van con nguyen, chi doi ten bang.

## Phan quyen (RBAC)

3 vai tro, gan qua cot `users.role` — dung theo de xuat trong tai lieu y
tuong (`§17. Phan quyen`):

| Vai tro   | Duoc lam gi |
|-----------|-------------|
| `admin`   | Toan quyen: import/quan ly san pham, cong thuc gia, xem **va xoa** tat ca bao gia, tao tai khoan moi va doi vai tro nguoi dung tai `/admin/users`. |
| `sales`   | Tao bao gia; chi xem/xuat PDF **bao gia do chinh minh tao** (`/quotes` chi liet ke bao gia cua ho, mo bao gia cua nguoi khac se bi chan 403). Khong xoa duoc bao gia nao, ke ca cua chinh minh. Khong vao duoc man hinh `/admin/*` nao. |
| `manager` | Xem **tat ca** bao gia (giong admin), nhung **chi doc** — khong xoa duoc bao gia, khong vao duoc `/admin/*` (khong quan ly catalog/cong thuc gia/nguoi dung). |

Xoa bao gia: nut **"Xoá"** chi hien voi `admin`, o ca trang danh sach
(`/quotes`) va trang chi tiet 1 bao gia — co hop thoai xac nhan truoc khi
xoa. Xoa vinh vien (khong phai an/luu tru), kem xoa toan bo dong san
pham/phu phi da luu trong bao gia do.

Cach tao tai khoan / gan vai tro: dang nhap bang tai khoan admin → menu →
**Người dùng** (`/admin/users`). Form "Thêm người dùng mới" tao tai khoan
(ten/email/mat khau/vai tro) — tai khoan tao ra dang nhap duoc ngay, khong
can xac thuc email vi he thong chua cau hinh gui mail. Voi user da co san,
chon vai tro trong dropdown o bang ben duoi → **Lưu**. Mot admin khong the
tu doi vai tro cua chinh minh (tranh tu khoa minh khoi he thong) — nho mot
admin khac doi giup, hoac sua truc tiep trong database.

Tai khoan seed mac dinh (`test@example.com`) la `admin`. Neu nang cap tu
database da co san (khong chay seeder), migration
`2026_08_20_100000_add_role_to_users_table` se tu dong gan `admin` cho user
co `id` nho nhat, con lai mac dinh `sales`.

**Chua lam** (tai lieu `§17` co nhac nhung chua co tinh nang goc de gan
quyen vao — se can khi tinh nang tuong ung duoc xay):

- Sua bao gia da luu (hien tai bao gia chi tao 1 lan, khong co man hinh sua).
- Nhan ban bao gia.
- Quy trinh duyet bao gia (can them cot trang thai: Draft/Sent/Approved...).
- Man hinh bao cao rieng cho Manager.

## Dashboard

Trang `/dashboard` (trang vao mac dinh sau khi dang nhap) hien 3 the thong
ke: **so luong + tong gia tri bao gia** trong ngay hom nay, thang nay
(tinh tu dau thang), va nam nay (tinh tu dau nam).

- **Sales**: chi thay so lieu cua chinh minh (dung logic voi trang danh
  sach bao gia — Sales khong bao gio thay du lieu cua nguoi khac).
- **Admin/Manager**: thay so lieu toan bo he thong, **cong them bang "Hoat
  dong nhan vien sale (thang nay)"** — so bao gia hom nay/thang nay, tong
  gia tri thang nay, va thoi diem bao gia gan nhat cua tung nhan vien co
  vai tro Sales, sap xep theo tong gia tri thang giam dan.

## Import san pham tu CSV

Chi import cac dong co cot **`Visibility in catalog` = `visible`** (dinh dang
export chuan cua WooCommerce: `visible` | `catalog` | `search` | `hidden`).
Dong nao khac `visible` se bi bo qua hoan toan, khong tao/cap nhat san pham
trong catalog. Cot nay thuong chi co gia tri o dong san pham cha (`simple`
hoac `variable`) — dong `variation` khong co gia tri rieng se ke thua trang
thai cua san pham cha; neu san pham cha khong `visible` thi variation cua no
cung bi loai theo. Neu file CSV khong co cot nay, hanh vi giu nguyen nhu cu
(import tat ca).

Sau khi import, man hinh admin (hoac terminal voi lenh
`php artisan products:import`) se bao so luong san pham da bo qua vi ly do
nay.

**Cot `Base price`** (tuy chon, khong phai cot chuan cua WooCommerce — tu
them vao file export neu can): luu vao `products.base_price`, dung lam gia
goc cho cong thuc tinh gia theo kich thuoc tuy chinh — xem phan **"Cong thuc
tinh gia tuy chinh"** ben duoi.

## Danh sach san pham (sua gia tay)

Trang **`/admin/products`** liet ke toan bo san pham dang co trong database,
co o tim theo ten hoac SKU va phan trang. Muc dich chinh: sua nhanh 1-2 gia
bi sai ma khong can import lai toan bo file CSV.

Chi sua duoc **3 truong gia**: `regular_price` (gia niem yet), `sale_price`
(gia khuyen mai), `base_price` (gia goc cho cong thuc tuy chinh). Cac thong
tin khac (ten, SKU, nhom san pham, hinh anh, trang thai...) khong sua duoc
o day — sua o file CSV nguon roi import lai, tranh truong hop sua tay mot
noi con CSV mot noi khac, lan import sau se ghi de mat thay doi da sua tay.

## Cong thuc tinh gia tuy chinh (custom_formula)

Ngoai 4 loai cong thuc co san (`catalog_price`, `length_ratio`, `fixed`,
`quantity`), trang **`/admin/pricing-rules`** co them loai **`custom_formula`**
cho phep admin tu go bieu thuc toan de tinh gia khi khach chon kich thuoc
tuy chinh chua co san trong catalog.

Bieu thuc chi duoc dung phep `+ - * /`, dau ngoac `()`, so, va cac **bien
lay tu cot trong database**:

- `base_price` — cot moi them o tren, gia goc rieng cho cong thuc.
- `regular_price`, `sale_price` — gia niem yet/gia khuyen mai co san cua
  san pham.
- `length_cm` — kich thuoc (cm) khach nhap o man hinh tao bao gia (`0` neu
  khach khong nhap).

Vi du: `base_price + length_cm * 5000` — gia = gia goc cong them 5.000d cho
moi cm chieu dai khach chon.

Khi luu rule tren giao dien admin, he thong se **kiem tra cu phap ngay khi
bam Luu** (dung bien la, thieu dau ngoac, chia cho 0 voi so cu the...) va
bao loi bang tieng Viet neu sai — rule se khong duoc luu cho den khi cong
thuc hop le. Luu y: he thong khong the kiem tra truoc moi truong hop chia
cho 0 co the xay ra voi du lieu that (vd cong thuc chia cho `length_cm` ma
khach lai nhap dung 0) — nen tranh viet cong thuc chia cho mot bien co the
bang 0.

`custom_formula` co the gan theo nhom san pham (category) hoac rieng 1 SKU,
giong het cach hoat dong cua 3 loai cong thuc con lai.

San pham loai "variable" (dong cha nhom cac bien the, vd "Ban Borna" nhom
nhieu kich thuoc/mau con) khong co gia rieng nen truoc day khong hien trong o
tim san pham o trang **Bao gia** — chi cac bien the (variation) co gia cu the
moi chon duoc. Neu dong cha nay co gia tri o cot `base_price`, no se **van
hien trong tim kiem o Bao gia**, dung `base_price` lam gia mac dinh — de sales
co the chon thang san pham cha khi khach can mot kich thuoc tuy chinh chua co
san trong danh sach bien the, ket hop voi mot pricing rule `custom_formula`
gan cho category/SKU do de tinh gia theo `length_cm`. Dong cha khong co
`base_price` van bi loai khoi tim kiem vi khong co gia nao de dung.

O trang **Bao gia** (`/quotes/create`), o "Don gia" cua san pham chinh gio se
**tu dong tinh lai theo dung Pricing Engine** (rule cua category/SKU do —
length_ratio, fixed, quantity, custom_formula...) moi khi doi so luong hoac
kich thuoc tuy chinh, thay vi chi hien gia catalog tinh nhu truoc (chi cap
nhat khi qua man hinh Xac nhan). Neu sales sua tay o "Don gia" thi gia do
duoc giu nguyen — moi thay doi so luong/kich thuoc sau do se khong ghi de len
gia da sua tay nua, dung nhu hanh vi luu bao gia.

(Ky thuat: neu go kich thuoc roi xoa lai rat nhanh, co the co 2 request xem
truoc gia cung dang bay — he thong luon lay ket qua cua request **moi nhat**,
bo qua ket qua den tre cua request cu, de gia hien thi khong bao gio bi "tut
lai" gia sai sau khi xoa.)

Tren trang `/admin/pricing-rules`, dropdown chon cong thuc hien nhan tieng
Viet (vd "Theo ty le chieu dai" thay vi `length_ratio`) de de hieu hon. Khi
chon 1 trong 4 loai cong thuc co san, giao dien se hien mot doan mo ta tieng
Viet **chi xem, khong sua duoc** giai thich chinh xac cong thuc do tinh gia
nhu the nao — de admin doi chieu lai xem co dung y minh muon khong. Rieng
`custom_formula` van giu nguyen o nhap bieu thuc tu do nhu truoc.

## Hinh anh san pham

Anh san pham lay tu cot `products.image_url` (co san neu file CSV import co
cot `Images` — dinh dang export chuan cua WooCommerce). Khi chon san pham o
`/quotes/create`, anh hien ngay trong danh sach goi y va o san pham da chon.
Khi luu bao gia, anh duoc **luu snapshot** vao `quote_items.image_url` (giong
cach `product_name`/`sku`/`category` da duoc snapshot) — de neu san pham
trong catalog sau nay bi doi anh hoac bi xoa, bao gia da luu van hien dung
anh tai thoi diem lap.

**Anh duoc tai ve va luu local, khong hotlink truc tiep tu nguon** (vd
simiehome.com). Ly do: mot so trang nguon chan yeu cau tai anh tu domain
khac (hotlink/Referer protection) — URL van mo binh thuong khi dan thang
vao thanh dia chi trinh duyet, nhung se **khong hien** khi nhung nhu the
`<img>` tren mot trang web khac (vi trinh duyet gui kem Referer cua trang
dang xem). Vi vay khi import CSV (ca qua `/admin/product-imports` lan lenh
`php artisan products:import`), sau khi luu san pham xong he thong se tai
anh tu URL trong CSV ve va luu vao `storage/app/public/products/`, roi doi
`image_url` cua san pham thanh duong dan local (`/storage/products/...`) —
anh se luon duoc trinh duyet tai tu chinh domain cua app, khong con phu
thuoc vao viec nguon co chan hotlink hay khong. File PDF cung doc thang tu
file local nay (khong con goi mang) khi in bao gia.

Neu URL nguon khong tai duoc luc import (mat mang, anh bi xoa...), san pham
van giu nguyen URL cu — se tu dong thu lai o lan import ke tiep, hoac chay
tay lenh `php artisan products:cache-images` bat cu luc nao (an toan chay
lai nhieu lan, anh da tai roi se duoc bo qua, khong tai lai).

Man hinh xac nhan va chi tiet bao gia hien anh truc tiep tu `image_url` da
luu (co the la duong dan local hoac URL ngoai, tuy thoi diem quote duoc
tao). File PDF: neu la duong dan local thi doc thang tu dia, neu van con la
URL ngoai (bao gia cu, luu truoc khi co tinh nang cache local) thi server
van tai ve va nhung vao PDF dang base64 nhu truoc — neu khong tai duoc,
dong san pham do trong PDF chi don gian khong co anh, khong lam loi ca file
PDF.

San pham chua co anh (`image_url` rong) van hien thi binh thuong, chi khong
co thumbnail.

## Main Routes

- `/quotes`: danh sach bao gia (tim kiem theo ngay / nguoi bao gia / khach hang, phan trang; Sales chi thay bao gia cua minh)
- `/quotes/create`: man hinh tinh gia (chon san pham chinh + phu + phu phi)
- `/quotes/confirm`: man hinh xac nhan (nhap thong tin khach hang, "Quay lai" de sua)
- `/quotes/{quote}`: chi tiet 1 bao gia da luu (chi chu bao gia hoac admin/manager moi xem duoc)
- `/quotes/{quote}/pdf`: xuat PDF bao gia tu snapshot da luu (cung gioi han nhu tren)
- `/products/search`: endpoint JSON cho goi y san pham
- `/admin/product-imports`: upload file CSV de import/cap nhat san pham (Admin)
- `/admin/products`: danh sach san pham trong database, tim theo ten/SKU, sua tay gia (Admin) — xem chi tiet ben duoi
- `/admin/pricing-rules`: chinh cong thuc gia theo nhom san pham hoac tung SKU (Admin)
- `/admin/users`: tao tai khoan moi, doi vai tro nguoi dung (Admin)

## Verification

- `php artisan test`
- `npm run build`
