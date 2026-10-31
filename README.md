# Quản lý tài khoản MU88

Trang tĩnh (1 file `index.html`) chạy ngay trong trình duyệt, không cần máy chủ/PHP.

- Thêm tài khoản → tự đăng nhập `mu.sng22.vip` → hiện số dư.
- Dữ liệu (mã băm MD5 của mật khẩu + kết quả) lưu tại máy bằng localStorage, không gửi đi đâu ngoài chính `mu.sng22.vip`.

## Dùng
- Cách 1: mở thẳng file `index.html` trong trình duyệt (nháy đúp).
- Cách 2: upload `index.html` lên hosting rồi mở qua URL.

Phải chạy trên trình duyệt ở Việt Nam (IP vào được web). API số dư sẽ cấu hình trong phần "Cài đặt API" khi có.

## API đã biết
- Đăng nhập: `POST https://mu.sng22.vip/api/front/wl/index/login`
  body JSON `{account, password: md5(mật_khẩu), loginSrc: 0}` → trả `t.token`, `t.nickName`, ...
