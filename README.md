# Quản lý tài khoản MU88 (PHP)

Thêm tài khoản → tự đăng nhập `mu.sng22.vip` (mật khẩu MD5) → hiện số dư. Quản lý nhiều tài khoản.

## File
- `index.php`  — giao diện quản lý.
- `lib88.php`  — gọi API đăng nhập/số dư.
- `update.php` — cập nhật code từ GitHub (cần `config.local.php`).

## Cài trên hosting
1. Upload cả 3 file vào thư mục web (cùng 1 chỗ).
2. Cấp quyền ghi cho thư mục (để lưu `mu88-data.php`).
3. Mở `index.php`, thêm tài khoản.

## API đã biết
- Đăng nhập: `POST https://mu.sng22.vip/api/front/wl/index/login`
  body `{account, password: md5(mật_khẩu), loginSrc: 0}` → `t.token`, `t.nickName`...
- Số dư: cấu hình ở mục "Cài đặt" khi có API.

## Lưu ý
Hosting phải vào được `mu.sng22.vip`. Nếu bị "Truy cập bị cấm" (403), cần proxy dân cư/4G Việt Nam ở mục Cài đặt.
