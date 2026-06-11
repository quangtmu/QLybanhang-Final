# Foundation & Database

Du an nay dung PHP 8.1+ va MySQL 8.0 theo SRS.

## Cau truc da tao

- `public/`: entry point va assets public.
- `app/api/`: endpoint JSON noi bo cho admin, store, user.
- `app/controllers/`: logic nghiep vu.
- `app/models/`: query database bang PDO.
- `app/views/`: template HTML theo portal.
- `app/middleware/`: auth va permission checks.
- `config/`: `config.php`, `db.php`, `constants.php`.
- `database/`: schema va seed ban dau.
- `uploads/`: file upload.
- `exports/`: file xuat PDF/bao cao.
- `includes/`: header, footer, nav dung chung.

## Tao database

Chay file:

```bash
mysql -u root -p < database/schema.sql
```

## Tao admin dau tien

Cach tot nhat la chay script PHP, vi script se tao bcrypt hash moi:

```bash
ADMIN_EMAIL=admin@example.com ADMIN_PASSWORD='Admin@123456' php scripts/seed_admin.php
```

Neu muon dung SQL truc tiep:

```bash
mysql -u root -p sales_system < database/seed_admin.sql
```

Mat khau mac dinh cua `seed_admin.sql` la `password`. Hay doi ngay sau lan dang nhap dau tien.

## Chay web local

```bash
php -S 127.0.0.1:8000 -t public
```

## Auth URLs

- `http://127.0.0.1:8000/login.php`: dang nhap chung cho admin, store, buyer.
- `http://127.0.0.1:8000/register.php`: dang ky buyer.
- `http://127.0.0.1:8000/change-password.php`: doi mat khau, bat buoc khi `is_first_login = 1`.
- `http://127.0.0.1:8000/logout.php`: dang xuat.
- `http://127.0.0.1:8000/api/auth.php?action=login`: API dang nhap tra JWT.
- `http://127.0.0.1:8000/api/auth.php?action=me`: API lay user hien tai bang session/JWT.

## Admin User Management URLs

- `http://127.0.0.1:8000/admin/users.php`: man hinh admin quan ly user/sub-admin.
- `GET /api/admin/users.php?action=list`: danh sach user, ho tro `search`, `user_type`, `page`, `limit`.
- `POST /api/admin/users.php?action=create-sub-admin`: tao sub-admin.
- `GET /api/admin/users.php?action=permissions&id=USER_ID`: xem quyen sub-admin.
- `PUT /api/admin/users.php?action=permissions&id=USER_ID`: cap nhat quyen sub-admin.
- `POST /api/admin/users.php?action=lock&id=USER_ID`: khoa tai khoan.
- `POST /api/admin/users.php?action=unlock&id=USER_ID`: mo khoa tai khoan.

## Category & Tag URLs

- `http://127.0.0.1:8000/admin/categories.php`: CRUD danh muc 3 cap, tree view, active/inactive.
- `http://127.0.0.1:8000/admin/tags.php`: CRUD tag theo nhom `large`, `medium`, `small`.
- `GET /api/admin/categories.php?action=list`: danh sach category.
- `GET /api/admin/categories.php?action=tree`: tree view category.
- `POST /api/admin/categories.php?action=create`: tao category.
- `PUT /api/admin/categories.php?action=update&id=CATEGORY_ID`: sua category.
- `POST /api/admin/categories.php?action=activate&id=CATEGORY_ID`: active category.
- `POST /api/admin/categories.php?action=deactivate&id=CATEGORY_ID`: inactive category.
- `DELETE /api/admin/categories.php?action=delete&id=CATEGORY_ID`: xoa category neu chua co con/san pham.
- `GET /api/admin/tags.php?action=list`: danh sach tag.
- `POST /api/admin/tags.php?action=create`: tao tag.
- `PUT /api/admin/tags.php?action=update&id=TAG_ID`: sua tag.
- `POST /api/admin/tags.php?action=activate&id=TAG_ID`: active tag.
- `POST /api/admin/tags.php?action=deactivate&id=TAG_ID`: inactive tag.
- `DELETE /api/admin/tags.php?action=delete&id=TAG_ID`: xoa tag neu chua gan san pham.

## Store Registration URLs

- `http://127.0.0.1:8000/user/store-registration.php`: buyer gui va xem trang thai don mo shop.
- `http://127.0.0.1:8000/admin/store-registrations.php`: admin duyet/tu choi don mo shop.
- `GET /api/user/store-registration.php?action=my`: buyer xem cac don cua minh.
- `POST /api/user/store-registration.php?action=submit`: buyer gui don mo shop.
- `GET /api/admin/store-registrations.php?action=list`: admin xem danh sach don.
- `GET /api/admin/store-registrations.php?action=detail&id=REQUEST_ID`: admin xem chi tiet don.
- `POST /api/admin/store-registrations.php?action=approve&id=REQUEST_ID`: admin duyet, tao account shop va store profile.
- `POST /api/admin/store-registrations.php?action=reject&id=REQUEST_ID`: admin tu choi va gui ly do.
- Email local duoc ghi vao `storage/mail_logs`. Neu muon gui mail that, cau hinh `MAIL_ENABLED=true`, `MAIL_FROM_EMAIL`, `MAIL_FROM_NAME`.

## User Order URLs

- `http://127.0.0.1:8000/user/orders.php`: buyer checkout tu gio hang va xem lich su don.
- `GET /api/user/orders.php?action=list`: lich su don hang cua buyer.
- `GET /api/user/orders.php?action=detail&id=ORDER_ID`: chi tiet don hang.
- `POST /api/user/orders.php?action=checkout`: dat hang tu gio hang hien tai.
- `POST /api/user/orders.php?action=create`: dat hang bang payload `items` truc tiep.
- `POST /api/user/orders.php?action=cancel&id=ORDER_ID`: huy don khi `pending`.
- `POST /api/user/orders.php?action=received&id=ORDER_ID`: xac nhan da nhan khi don `shipped` hoac `delivering`.

## Admin Order & DB URLs

- `http://127.0.0.1:8000/admin/orders.php`: admin xem tat ca don, filter va huy can thiep.
- `GET /api/admin/orders.php?action=list`: danh sach tat ca don, ho tro `search`, `status`, `store_id`, `buyer_id`, `date_from`, `date_to`.
- `GET /api/admin/orders.php?action=detail&id=ORDER_ID`: chi tiet don.
- `POST /api/admin/orders.php?action=cancel&id=ORDER_ID`: admin huy don chua delivered/refunded/cancelled.
- `http://127.0.0.1:8000/admin/db.php`: xem database read-only trong app.

## Shipment URLs

- `http://127.0.0.1:8000/admin/shipments.php`: admin tao van don, cap nhat trang thai va xem timeline van chuyen.
- `GET /api/admin/shipments.php?action=list`: danh sach van don, ho tro `search`, `status`, `order_status`, `store_id`, `date_from`, `date_to`.
- `GET /api/admin/shipments.php?action=detail&id=SHIPMENT_ID`: chi tiet van don kem timeline.
- `GET /api/admin/shipments.php?action=orders`: danh sach don chua co van don.
- `POST /api/admin/shipments.php?action=create`: tao van don cho don hang.
- `POST /api/admin/shipments.php?action=update&id=SHIPMENT_ID`: cap nhat thong tin van don.
- `POST /api/admin/shipments.php?action=status&id=SHIPMENT_ID`: cap nhat trang thai van don va ghi log timeline.

## Invoice URLs

- `http://127.0.0.1:8000/admin/invoices.php`: admin xuat va tai PDF hoa don.
- `http://127.0.0.1:8000/store/invoices.php`: shop xuat va tai hoa don cua shop.
- `http://127.0.0.1:8000/user/invoices.php`: buyer tai hoa don cua minh.
- `GET /api/admin/invoices.php?action=list`: admin xem tat ca hoa don.
- `GET /api/store/invoices.php?action=list`: shop xem hoa don cua shop.
- `GET /api/user/invoices.php?action=list`: buyer xem hoa don cua minh.
- `GET /api/admin/invoices.php?action=orders`: don hang chua co hoa don.
- `POST /api/admin/invoices.php?action=generate`: admin xuat PDF hoa don theo `order_id`.
- `POST /api/store/invoices.php?action=generate`: shop xuat PDF hoa don cho don cua shop.
- `GET /invoice-download.php?id=INVOICE_ID`: tai PDF, tu kiem tra quyen admin/store/user.

## Chat URLs

- `http://127.0.0.1:8000/chat.php`: chat theo don hang cho admin, shop va buyer.
- `GET /api/chat.php?action=rooms`: danh sach room chat theo quyen hien tai.
- `GET /api/chat.php?action=orders`: danh sach don hang co the mo room chat.
- `POST /api/chat.php?action=room`: tao/lay room chat theo `order_id`.
- `GET /api/chat.php?action=messages&room_id=ROOM_ID&after_id=MESSAGE_ID`: polling tin nhan moi.
- `POST /api/chat.php?action=send&room_id=ROOM_ID`: gui tin nhan text.



## Product Management Screens

- Store tao/sua/gui duyet san pham: `http://localhost:8888/store/products.php`.
- Admin duyet/tu choi san pham: `http://localhost:8888/admin/products.php`.
- Luong dung: buyer mo shop -> admin duyet shop -> store dang nhap -> store tao san pham va gui duyet -> admin duyet san pham -> buyer thay san pham approved.
- Neu shop da duyet nhung khong biet mat khau, xem `users.password_dev` hoac import `database/migrations/20260608_reset_store_passwords_dev.sql` de reset shop ve `Store@123456`.

## Product Management APIs

- `GET /api/store/products.php?action=list`: shop xem san pham cua minh.
- `GET /api/store/products.php?action=detail&id=PRODUCT_ID`: shop xem chi tiet san pham.
- `POST /api/store/products.php?action=create`: shop tao san pham draft hoac gui duyet neu `submit_for_review=true`.
- `PUT /api/store/products.php?action=update&id=PRODUCT_ID`: shop sua san pham draft/rejected.
- `POST /api/store/products.php?action=submit&id=PRODUCT_ID`: shop gui san pham draft/rejected cho admin duyet.
- `POST /api/store/products.php?action=archive&id=PRODUCT_ID`: shop an/luu tru san pham.
- `GET /api/admin/products.php?action=list`: admin xem san pham toan he thong, loc `status=pending_review` de duyet.
- `GET /api/admin/products.php?action=detail&id=PRODUCT_ID`: admin xem chi tiet san pham.
- `POST /api/admin/products.php?action=approve&id=PRODUCT_ID`: admin duyet san pham.
- `POST /api/admin/products.php?action=reject&id=PRODUCT_ID`: admin tu choi san pham, body `{ "reason": "..." }`.
- `GET /api/health.php`: kiem tra app, DB va storage truoc khi test Postman.

## Store Employee URLs

- `http://127.0.0.1:8000/store/employees.php`: shop tao nhan vien, khoa/mo va phan quyen module shop.
- `GET /api/store/employees.php?action=list`: danh sach nhan vien shop.
- `GET /api/store/employees.php?action=modules`: module/action co the phan quyen.
- `POST /api/store/employees.php?action=create`: tao tai khoan `store_employee`.
- `POST /api/store/employees.php?action=permissions&id=EMPLOYEE_ID`: cap nhat quyen nhan vien.
- `POST /api/store/employees.php?action=activate&id=EMPLOYEE_ID`: mo nhan vien.
- `POST /api/store/employees.php?action=deactivate&id=EMPLOYEE_ID`: khoa nhan vien.

## Banner URLs

- `http://127.0.0.1:8000/admin/banners.php`: admin upload, validate, sap xep, bat/tat va xoa banner.
- `GET /api/admin/banners.php?action=list`: danh sach banner admin.
- `POST /api/admin/banners.php?action=create`: tao banner bang multipart field `image`.
- `POST /api/admin/banners.php?action=update&id=BANNER_ID`: cap nhat banner.
- `POST /api/admin/banners.php?action=activate&id=BANNER_ID`: bat banner.
- `POST /api/admin/banners.php?action=deactivate&id=BANNER_ID`: tat banner.
- `POST /api/admin/banners.php?action=sort`: cap nhat thu tu banner.
- `DELETE /api/admin/banners.php?action=delete&id=BANNER_ID`: xoa banner va file anh.
- `GET /api/user/banners.php?action=active`: banner active cho user portal hien thi.

## Notification URLs

- `http://127.0.0.1:8000/notifications.php`: xem, loc va danh dau da doc thong bao theo tai khoan dang nhap.
- `GET /api/notifications.php?action=list`: danh sach thong bao cua user hien tai, ho tro `search`, `notification_type`, `is_read`, `page`.
- `GET /api/notifications.php?action=unread-count`: dem thong bao chua doc.
- `POST /api/notifications.php?action=mark-read&id=NOTIFICATION_ID`: danh dau mot thong bao da doc.
- `POST /api/notifications.php?action=mark-all-read`: danh dau tat ca thong bao da doc.

## Dashboard URLs

- `http://127.0.0.1:8000/admin/dashboard.php`: dashboard admin voi doanh thu, don hang, san pham, user va top shop.
- `http://127.0.0.1:8000/store/dashboard.php`: dashboard shop voi doanh thu, don hang, san pham, nhan vien va top san pham.
- `GET /api/admin/dashboard.php?action=summary`: thong ke tong quan admin.
- `GET /api/store/dashboard.php?action=summary`: thong ke tong quan shop theo tai khoan store/store_employee dang nhap.

## Bien moi truong ho tro

- `APP_ENV`
- `APP_DEBUG`
- `APP_TIMEZONE`
- `BASE_URL`
- `DB_HOST`
- `DB_PORT`
- `DB_NAME`
- `DB_USER`
- `DB_PASS`
- `DB_CHARSET`
- `JWT_SECRET`
- `SESSION_NAME`
- `SESSION_SECURE`


## Review backend va Postman

- Doc nhan xet backend/DB/storage: `docs/BACKEND_REVIEW.md`.
- Huong dan PHP/MySQL tren Mac: `docs/MAC_LOCAL_SETUP.md`.
- Postman collection: `postman/QLybanhang.postman_collection.json`.
- Import collection vao Postman, chay `Auth / Login` truoc de luu token, sau do test cac API buyer/admin/store.

## Storage upload

Mac dinh file upload public luu tai `public/uploads`. Co the doi base URL bang `.env`:

```env
STORAGE_PROVIDER=local
STORAGE_LOCAL_PUBLIC_DIR=public/uploads
STORAGE_PUBLIC_URL=/uploads
```

Neu dung Cloudflare R2:

```env
STORAGE_PROVIDER=r2
STORAGE_PUBLIC_URL=https://pub-309aa43ab7414948a1e66726694eda95.r2.dev
R2_ENDPOINT=https://b872682004e34e1ea06180cff75771c4.r2.cloudflarestorage.com
R2_BUCKET=qlydonhang
R2_ACCESS_KEY_ID=...
R2_SECRET_ACCESS_KEY=...
```

Sau khi them anh van don, chay migration:

```bash
mysql -u root -p sales_system < database/migrations/20260608_add_shipment_proof_image.sql
```

Neu dung MAMP mac dinh:

```bash
/Applications/MAMP/Library/bin/mysql -u root -p -P 8889 -h 127.0.0.1 sales_system < database/migrations/20260608_add_shipment_proof_image.sql
```

Tao view lien ket don hang voi buyer/shop:

```bash
/Applications/MAMP/Library/bin/mysql -u root -p -P 8889 -h 127.0.0.1 sales_system < database/migrations/20260608_create_order_user_store_views.sql
```

Seed danh muc 3 cap va tag mau:

```bash
/Applications/MAMP/Library/bin/mysql -u root -p -P 8889 -h 127.0.0.1 sales_system < database/migrations/20260608_seed_catalog_taxonomy.sql
```

Neu trien khai len server/CDN, giu duong dan file trong DB la public URL. Google Drive co the dung tam cho file ho so noi bo, nhung banner/anh san pham nen dung S3/R2/Supabase Storage/Firebase Storage de on dinh hon.


## Reset admin local neu login Postman khong dung

Neu da import seed nhieu lan va login van sai, import migration nay trong phpMyAdmin, chon database `sales_system` truoc:

```text
database/migrations/20260608_add_password_dev_and_reset_admin.sql
```

Sau do admin local la:

```text
login: admin@example.com
password: password
```

Cot `password_dev` chi de debug local, khong dung cho production.


## Store Order & Shipment Screens

- `http://localhost:8888/store/orders.php`: shop xem/loc don, tao don thu cong, xac nhan don, chuyen sang dong goi va huy don khi con cho phep.
- `http://localhost:8888/store/shipments.php`: shop tao van don cho don chua co van don, cap nhat trang thai giao hang va xem timeline.
- Luong van hanh de test: buyer checkout hoac shop tao don thu cong -> shop xac nhan -> shop dong goi -> tao van don -> `picked_up` -> `in_transit` -> `out_for_delivery` -> `delivered`. Trang thai order se duoc dong bo theo trang thai van don.
- Admin dung `http://localhost:8888/admin/orders.php` va `http://localhost:8888/admin/shipments.php` de xem/toan quyen can thiep tren tat ca shop.

## Store Order & Shipment APIs

- `GET /api/store/orders.php?action=list`: shop xem don cua minh.
- `GET /api/store/orders.php?action=detail&id=ORDER_ID`: shop xem chi tiet don.
- `POST /api/store/orders.php?action=create-manual`: shop tao don thu cong cho buyer da dang ky.
- `POST /api/store/orders.php?action=confirm&id=ORDER_ID`: shop xac nhan don pending.
- `POST /api/store/orders.php?action=processing&id=ORDER_ID`: shop chuyen don sang dang xu ly/dong goi.
- `POST /api/store/orders.php?action=cancel&id=ORDER_ID`: shop huy don pending/confirmed kem `reason`.
- `GET /api/store/shipments.php?action=orders`: don cua shop chua co van don.
- `POST /api/store/shipments.php?action=create`: shop tao van don cho don cua minh.
- `POST /api/store/shipments.php?action=status&id=SHIPMENT_ID`: shop cap nhat trang thai van don; he thong tu dong dong bo order status.

Luong test backend nen chay: buyer checkout hoac store create-manual -> store confirm -> store processing -> store create shipment -> store shipment status picked_up/out_for_delivery/delivered -> buyer xem order delivered.
