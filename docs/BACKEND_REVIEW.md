# Backend Review & Huong Toi Uu

## Ket luan nhanh

Du an hien tai dang dung PHP thuan + MySQL theo SRS. Huong nay phu hop cho ban MVP va de hoc/kiem soat luong nghiep vu. Chua nen doi sang MongoDB cho database chinh vi he thong co nhieu quan he can rang buoc: user, shop, san pham, don hang, van don, hoa don, phan quyen.

Laravel la huong nang cap tot neu muon backend bai ban hon: routing, validation, migration, queue mail, storage disk, policy permission, test. Tuy nhien migration sang Laravel nen tach thanh phase rieng, khong nen chen vao luc dang hoan thien function.

## DB nen dung gi?

- Nen giu MySQL hoac nang len PostgreSQL neu can bao cao/transaction tot hon.
- Khong nen thay bang MongoDB cho order/invoice/shipment vi se mat foreign key, transaction va truy van bao cao phuc tap hon.
- MongoDB co the dung phu cho chat log, event log, audit trail hoac cache noi dung linh hoat.
- Neu muon xem DB thoang hon, dung TablePlus, DBeaver, Sequel Ace hoac phpMyAdmin. Man `admin/db.php` trong app chi nen de read-only nhanh, khong nen la cong cu DB chinh.

## Storage / file upload

SRS ghi local filesystem, nhung ban co nhu cau day file len moi truong thu ba nhu Drive/CDN. Code da duoc tach them `StorageService` de banner upload khong bi khoa vao `/public/uploads`.

Cau hinh hien tai trong `.env`:

```env
STORAGE_PROVIDER=local
STORAGE_LOCAL_PUBLIC_DIR=public/uploads
STORAGE_PUBLIC_URL=/uploads
```

Huong mo rong hop ly:

- Local dev: luu `public/uploads`.
- Production nho: luu local server + domain static rieng qua `STORAGE_PUBLIC_URL`.
- Production tot hon: chuyen sang S3/R2/Supabase Storage/Firebase Storage.
- Google Drive chi nen dung tam cho file noi bo, khong nen dung lam CDN anh san pham/banner vi quyen public link va toc do khong on dinh bang object storage.

## Khoang trong chuc nang so voi SRS

- Store Portal da co CRUD san pham, quan ly don shop va tao/cap nhat van don de test luong thuc te.
- Admin da co man duyet shop, duyet san pham, xem don va theo doi/tac dong van don toan he thong.
- Buyer da co xem san pham/gio hang/dat hang; san pham co bien the bat buoc chon bien the truoc khi them vao gio.
- Dang ky mo shop hien nhap URL giay to, chua upload file CCCD/giay phep truc tiep.
- API helper da gom bot cho cac nhom moi, nhung mot so endpoint cu van con response helper rieng; nen gom tiep khi refactor lon hon.

## Luong man hinh nen chinh

1. Buyer dang ky/dang nhap.
2. Buyer mo shop va nhap/upload ho so.
3. Admin duyet shop, he thong tao account shop.
4. Shop dang nhap lan dau, doi mat khau.
5. Shop tao san pham + bien the + ton kho.
6. Admin duyet san pham.
7. Buyer xem san pham, them gio hang, checkout.
8. Admin/shop xu ly don, tao van don, cap nhat trang thai.
9. Buyer xac nhan nhan hang, hoa don/xuat PDF.

Luong tren da co duong test chinh: store tao san pham -> admin duyet -> buyer dat hang -> store xu ly don -> tao van don -> cap nhat trang thai giao hang. Phan nen lam tiep la lam min UI buyer/store, them upload ho so mo shop va viet test tu dong cho cac API quan trong.

## Postman test nhanh

Import file `postman/QLybanhang.postman_collection.json` vao Postman. Chay `Auth / Login Admin`, neu admin moi seed thi chay tiep `Auth / Change First Password`, sau do test nhom Admin. Chay `Auth / Login Buyer` de test nhom Buyer. Token se duoc luu vao bien collection `token`. Cac API con lai dung `Authorization: Bearer {{token}}`.

Tai khoan admin tao bang:

```bash
ADMIN_EMAIL=admin@example.com ADMIN_PASSWORD='Admin@123456' php scripts/seed_admin.php
```
