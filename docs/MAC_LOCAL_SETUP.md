# Mac Local Setup

## PHP de xem giao dien

May hien tai can co PHP CLI de chay server local:

```bash
php -v
php -S 127.0.0.1:8000 -t public
```

Neu `php` chua co, cach it dung terminal nhat la cai MAMP, sau do chay Apache/MySQL trong MAMP va tro document root ve thu muc `public`. Cach khac la cai Homebrew roi `brew install php mysql`, nhung Homebrew se can Command Line Tools cua Apple cai xong truoc.

## Xem MySQL ma khong doi noi luu

Khong can doi database. Chi can dung tool client ket noi vao MySQL hien tai:

- Sequel Ace: nhe, hop voi Mac.
- TablePlus: de nhin schema/data, co ban free.
- DBeaver: mien phi, day du.
- phpMyAdmin: neu dung MAMP/XAMPP.

Thong tin ket noi mac dinh theo `.env.example`:

```env
Host: localhost
Port: 3306
Database: sales_system
User: root
Password: de trong neu dung mac dinh cua project, hoac mat khau MySQL cua ban
```

Trong app cung co man read-only `http://127.0.0.1:8000/admin/db.php`, nhung man nay chi nen dung xem nhanh. Khi can sua/xem quan he bang, dung Sequel Ace/TablePlus/DBeaver se thoang hon nhieu.
