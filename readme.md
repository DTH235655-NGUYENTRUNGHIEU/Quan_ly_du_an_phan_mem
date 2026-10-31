# HỆ THỐNG QUẢN LÝ LỮ HÀNH & ĐẶT TOUR TRỰC TUYẾN (DU LỊCH VIỆT NAM)
> **Đồ án môn học:** Quản lý Dự án Phần mềm (Software Project Management)  
> **Chuyên ngành:** Công nghệ Thông tin - Khóa 2022 - 2026  
> **Phiên bản:** v1.0.0 (Production Ready)  
> **Cập nhật lần cuối:** Tháng 10/2026  

---

## MỤC LỤC
1. [Tổng Quan & Ngữ Cảnh Dự Án](#1-tổng-quan--ngữ-cảnh-dự-án)
2. [Môi Trường Phát Triển & Công Nghệ (Tech Stack)](#2-môi-trường-phát-triển--công-nghệ-tech-stack)
3. [Cấu Trúc Thư Mục Dự Án](#3-cấu-trúc-thư-mục-dự-án)
4. [Thiết Kế Cơ Sở Dữ Liệu (Database Schema)](#4-thiết-kế-cơ-sở-dữ-liệu-database-schema)
5. [Các Luồng Xử Lý Dữ Liệu & Nghiệp Vụ Cốt Lõi](#5-các-luồng-xử-lý-dữ-liệu--nghiệp-vụ-cốt-lõi)
6. [Ma Trận Phân Quyền (RBAC & RACI)](#6-ma-trận-phân-quyền-rbac--raci)
7. [Hướng Dẫn Cài Đặt Môi Trường](#7-hướng-dẫn-cài-đặt-môi-trường)
8. [Tài Khoản Kiểm Thử & Hướng Dẫn Sử Dụng Theo Vai Trò](#8-tài-khoản-kiểm-thử--hướng-dẫn-sử-dụng-theo-vai-trò)
9. [Biện Pháp An Toàn Thông Tin & Quản Trị Rủi Ro](#9-biện-pháp-an-toàn-thông-tin--quản-trị-rủi-ro)

---

## 1. TỔNG QUAN & NGỮ CẢNH DỰ ÁN

### 1.1. Bối cảnh bài toán
Ngành du lịch lữ hành tại Việt Nam (đặc biệt là các tuyến du lịch sinh thái Miền Tây, miền Trung di sản và miền Bắc) đang chuyển dịch mạnh mẽ sang hình thức chuyển đổi số. Tuy nhiên, nhiều doanh nghiệp lữ hành vừa và nhỏ (SME) vẫn gặp các nút thắt lớn:
* Quá trình điều hành tour và phân công hướng dẫn viên (HDV) thủ công dẫn đến xung đột lịch trình (trùng lịch dẫn đoàn).
* Khâu tiếp nhận đặt chỗ phân tán, dễ dẫn đến tình trạng bán vượt số chỗ thực tế (overbooking) hoặc không tính toán chính xác chi phí phụ thu (như phụ thu phòng đơn, giá vé trẻ em/em bé).
* Đối soát thanh toán và xuất hóa đơn giá trị gia tăng (VAT) tách rời giữa bộ phận kinh doanh và kế toán.

### 1.2. Mục tiêu của hệ thống
Hệ thống **Du Lịch Việt Nam** được xây dựng như một giải pháp phần mềm quản trị tích hợp (All-in-one ERP & Booking Platform) nhằm:
1. **Dành cho Du khách:** Tra cứu danh mục tour, kiểm tra lịch khởi hành theo thời gian thực, đặt vé theo đoàn có cơ cấu đối tượng rõ ràng, chọn phụ thu phòng đơn, áp dụng mã voucher và thanh toán chuyển khoản qua mã chuẩn quốc gia VietQR.
2. **Dành cho Ban Điều Hành & Sales:** Kiểm soát danh sách đặt chỗ, duyệt cọc, tự động hoàn/giữ chỗ đợt khởi hành, phân công hướng dẫn viên với thuật toán chống trùng lịch.
3. **Dành cho Hướng dẫn viên:** Quản lý lịch phân công cá nhân, theo dõi danh sách hành khách đoàn (Passenger Manifest), tra cứu thông tin CCCD phục vụ bảo hiểm du lịch, ghi chú dị ứng/ăn chay và điểm danh lên xe.
4. **Dành cho Kế toán:** Quản lý dòng tiền nhiều đợt (Đặt cọc $\rightarrow$ Tất toán), tự động tính thuế suất VAT 8%, phát hành hóa đơn điện tử bóc tách phòng đơn và chốt sổ doanh thu tháng.

---

## 2. MÔI TRƯỜNG PHÁT TRIỂN & CÔNG NGHỆ (TECH STACK)

### 2.1. Hạ tầng Backend & Database
* **Ngôn ngữ lập trình:** PHP 8.2+ / 8.3+ (Tương thích tốt với PHP 8.3.28 và 8.4.x).
* **Kiến trúc phần mềm:** Modular Architecture kết hợp mô hình Hướng thủ tục an toàn (Functional/Modular PHP) với lớp truy xuất CSDL trừu tượng qua **PHP Data Objects (PDO)**.
* **Hệ quản trị CSDL:** MySQL 8.0+ / 8.4+ (Lưu trữ bảng chuẩn `InnoDB`, bảng mã ký tự `utf8mb4`, collation `utf8mb4_unicode_ci`).
* **Máy chủ web:** Apache 2.4.x / Nginx (Hỗ trợ cấu hình Rewrite URL qua `.htaccess`).

### 2.2. Giao diện Người dùng (Frontend)
* **CSS Framework:** Tailwind CSS (Nạp qua CDN/Tailwind Play Engine) kết hợp bố cục linh hoạt Responsive Design (Mobile - Tablet - Desktop).
* **Bộ Icon vector:** Lucide Icons (`data-lucide`) và FontAwesome 6.
* **Xử lý bất đồng bộ:** Vanilla JavaScript (ES6+), Fetch API cho các module lọc tour động, kiểm tra mã Voucher và sao chép thông tin tài khoản ngân hàng một chạm.
* **Trực quan hóa dữ liệu:** Chart.js hỗ trợ vẽ biểu đồ doanh thu và phân tích hiệu suất bán vé.

### 2.3. Tích hợp bên thứ ba (Third-party Integrations)
* **Cổng thanh toán tự động VietQR:** Sinh mã QR thanh toán động theo tiêu chuẩn chuyển mạch NAPAS 24/7 (kết nối ngân hàng Quân Đội - MBBank), tự động gán chính xác số tiền cọc và cú pháp nội dung là mã đơn `BKxxxxxx`.

---

## 3. CẤU TRÚC THƯ MỤC DỰ ÁN

Mã nguồn được phân tách module rõ ràng, phân định độc lập giữa mã nghiệp vụ Client-side, Cổng Quản trị Admin và API bất đồng bộ:

```text
tour_dulich_vietnam/
├── .htaccess                     # Cấu hình bảo mật thư mục và Rewrite URL
├── index.php                     # Trang chủ giới thiệu tour, banner, tìm kiếm
├── config/                       # Thư mục cấu hình hệ thống
│   ├── constants.php             # Khai báo hằng số hệ thống (BASE_URL, PATH, tỉ lệ thuế)
│   └── database.php              # Kết nối CSDL MySQL qua PDO với cờ bảo mật cao
├── includes/                     # Các thành phần giao diện & tiện ích dùng chung
│   ├── admin_sidebar.php         # Menu dọc phân quyền thông minh theo Role
│   ├── auth.php                  # Module xác thực phiên, kiểm tra đăng nhập & RBAC
│   ├── business_helpers.php      # [Nghiệp vụ mới] Tính tiền, đồng bộ chỗ & cộng điểm
│   ├── footer.php                # Chân trang giao diện du khách
│   ├── header.php                # Đầu trang nạp CSS Tailwind & Meta tags
│   └── navbar.php                # Thanh điều hướng trên cùng, hiển thị lịch HDV
├── api/                          # Xử lý các yêu cầu AJAX bất đồng bộ
│   ├── check_voucher.php         # API xác thực mã giảm giá, tính tiền chiết khấu
│   ├── filter_tours.php          # API lọc tour theo vùng miền, mức giá, thời gian
│   └── get_chart_data.php        # API cung cấp dữ liệu biểu đồ doanh thu kế toán
├── assets/                       # Tài nguyên tĩnh
│   ├── css/
│   │   └── custom.css            # Tùy biến CSS nâng cao, hiệu ứng chuyển động
│   └── js/
│       ├── booking.js            # Xử lý Form đặt vé, tính tổng tiền, validate CCCD
│       └── main.js               # Khởi tạo giao diện, dropdown menu, icon Lucide
├── modules/                      # Các phân hệ chức năng nghiệp vụ (Modules)
│   ├── accounting/               # Phân hệ Tài chính - Kế toán
│   │   ├── export_excel.php      # Kết xuất dữ liệu hóa đơn, doanh thu ra bảng tính
│   │   ├── invoice_detail.php    # Xem chi tiết & in mẫu hóa đơn điện tử VAT 8%
│   │   ├── invoices_list.php     # Tất toán đơn, quản lý và lập hóa đơn VAT
│   │   └── revenue_report.php    # Báo cáo doanh thu tháng/năm, chốt sổ kế toán
│   ├── admin/                    # Phân hệ Quản trị & Điều hành lữ hành
│   │   ├── booking_manager.php   # Duyệt đơn, duyệt cọc, quản lý khách mua bảo hiểm
│   │   ├── dashboard.php         # Bảng điều khiển tổng hợp thống kê điều hành
│   │   ├── guide_assign.php      # Phân công HDV, xem danh sách đoàn, chặn trùng lịch
│   │   ├── staff_manager.php     # Quản lý hồ sơ nhân sự, tài khoản và vai trò
│   │   └── tour_manager.php      # Quản lý tour mẫu, lịch khởi hành & bảng giá
│   ├── auth/                     # Phân hệ Xác thực tài khoản
│   │   ├── login.php             # Đăng nhập hệ thống (Bcrypt verified)
│   │   ├── logout.php            # Hủy phiên làm việc an toàn
│   │   └── register.php          # Đăng ký tài khoản khách hàng mới
│   ├── booking/                  # Phân hệ Đặt tour dành cho Khách hàng
│   │   ├── booking_step1.php     # Bước 1: Chọn ngày, số lượng khách, thông tin đoàn
│   │   ├── booking_step2.php     # Bước 2: Quét mã VietQR chuyển khoản cọc 50%
│   │   ├── booking_confirm.php   # Xác nhận đơn thành công & in phiếu hẹn
│   │   └── my_bookings.php       # Lịch sử chuyến đi cá nhân của du khách
│   ├── reviews/                  # Phân hệ Đánh giá & Hậu mãi
│   │   ├── review_manager.php    # Quản trị viên kiểm duyệt đánh giá của khách
│   │   └── submit_review.php     # Gửi nhận xét sao (chỉ cho phép sau khi hoàn tất tour)
│   └── tours/                    # Phân hệ Hiển thị sản phẩm du lịch
│       ├── detail.php            # Chi tiết tour, lịch trình từng ngày, dịch vụ đi kèm
│       └── index.php             # Danh mục tất cả tour có bộ lọc nâng cao
└── tour_dulich_vietnam.sql       # Kịch bản khởi tạo CSDL hoàn chỉnh
```

---

## 4. THIẾT KẾ CƠ SỞ DỮ LIỆU (DATABASE SCHEMA)

Cơ sở dữ liệu gồm **16 bảng thực thể chuẩn hóa 3NF** và **2 View báo cáo** được thiết kế chặt chẽ, đảm bảo tính toàn vẹn tham chiếu thông qua các khóa ngoại (`FOREIGN KEY`):

### 4.1. Bảng phân quyền & Người dùng
1. `roles`: Lưu danh mục vai trò hệ thống (`role_id`, `role_name`, `description`). Gồm 6 vai trò: *Administrator, Sales Staff, Tour Operator, Tour Guide, Accountant, Customer*.
2. `users`: Tài khoản đăng nhập nội bộ và khách hàng (`user_id`, `role_id`, `username`, `password_hash`, `fullname`, `email`, `phone`, `status`, `created_at`). Khóa ngoại tham chiếu `roles(role_id)`.
3. `customers`: Hồ sơ khách hàng lữ hành (`customer_id`, `user_id`, `fullname`, `email`, `phone`, `id_card`, `address`, `reward_points`). Cho phép lưu thông tin cả khách vãng lai (`user_id = NULL`) lẫn khách có tài khoản.

### 4.2. Bảng sản phẩm tour & Lịch khởi hành
4. `categories`: Danh mục tour theo loại hình (`category_id`, `category_name`, `slug`, `description`).
5. `destinations`: Điểm đến du lịch (`destination_id`, `destination_name`, `region`, `description`).
6. `tours`: Thông tin tour mẫu/cố định (`tour_id`, `category_id`, `destination_id`, `tour_code`, `title`, `slug`, `duration_days`, `duration_nights`, `start_location`, `transportation`, `included_services`, `excluded_services`, `thumbnail`, `status`).
7. `tour_itineraries`: Chi tiết hoạt động từng ngày (`itinerary_id`, `tour_id`, `day_number`, `day_title`, `description`, `meals`, `accommodation`). Khóa ngoại cascade theo `tours(tour_id)`.
8. `tour_images`: Album ảnh chi tiết của tour (`image_id`, `tour_id`, `image_url`, `caption`, `is_featured`).
9. `tour_departures`: Lịch khởi hành thực tế của từng tour (`departure_id`, `tour_id`, `departure_date`, `return_date`, `max_seats`, `booked_seats`, `tour_guide_id`, `status`). Khóa ngoại tham chiếu `tours(tour_id)` và `users(user_id)` làm hướng dẫn viên.
10. `tour_prices`: Bảng giá cấu thành theo từng đợt khởi hành (`price_id`, `departure_id`, `price_adult`, `price_child`, `price_infant`, `single_supplement`). Ràng buộc `UNIQUE KEY uq_departure_price (departure_id)`.

### 4.3. Bảng nghiệp vụ Đặt tour & Khách đoàn
11. `promotions`: Danh mục mã giảm giá/voucher (`promotion_id`, `promo_code`, `discount_type`, `discount_value`, `min_order_amount`, `max_discount_amount`, `start_date`, `end_date`, `usage_limit`, `used_count`, `status`).
12. `bookings`: Đơn đặt tour (`booking_id`, `booking_code`, `departure_id`, `customer_id`, `promotion_id`, `num_adults`, `num_children`, `num_infants`, `subtotal`, `discount_amount`, `total_amount`, `paid_amount`, `payment_status`, `booking_status`, `special_notes`, `is_rewarded`).
13. `booking_passengers`: Danh sách hành khách trong đoàn phục vụ bảo hiểm du lịch (`passenger_id`, `booking_id`, `fullname`, `gender`, `birth_date`, `passenger_type`, `id_card`, `single_room`). Ràng buộc `single_room = 1` kích hoạt phụ thu phòng đơn.

### 4.4. Bảng Kế toán, Thu chi & Hóa đơn VAT
14. `payments`: Lịch sử giao dịch thu tiền (`payment_id`, `booking_id`, `payment_code`, `payment_method`, `payment_phase`, `amount`, `transaction_ref`, `status`, `processed_by`).
15. `invoices`: Hóa đơn điện tử VAT (`invoice_id`, `booking_id`, `invoice_number`, `invoice_series`, `buyer_name`, `company_name`, `tax_code`, `company_address`, `buyer_email`, `payment_method`, `subtotal_amount`, `vat_rate`, `vat_amount`, `total_amount`, `status`, `created_by`).
16. `invoice_details`: Bảng kê chi tiết từng dòng hóa đơn (`detail_id`, `invoice_id`, `item_type`, `item_name`, `unit`, `quantity`, `unit_price`, `discount_amount`, `amount`).
17. `monthly_revenue_reports`: Sổ tổng hợp doanh thu tháng đã chốt khóa sổ (`report_id`, `report_month`, `report_year`, `total_bookings`, `confirmed_bookings`, `cancelled_bookings`, `gross_revenue`, `discount_total`, `net_revenue`, `status`, `closed_at`, `closed_by`).

### 4.5. Bảng đánh giá & Hệ thống Views
18. `reviews`: Đánh giá phản hồi của du khách (`review_id`, `tour_id`, `customer_id`, `booking_id`, `rating`, `comment`, `status`).
19. `v_monthly_revenue` & `v_yearly_revenue`: View tổng hợp doanh thu gộp, thuế VAT thu hộ và doanh thu thực theo tháng/năm từ các hóa đơn đã phát hành.

---

## 5. CÁC LUỒNG XỬ LÝ DỮ LIỆU & NGHIỆP VỤ CỐT LÕI

### 5.1. Luồng 1: Đặt tour & Tính chi phí phụ thu phòng đơn
```text
Du khách chọn Tour & Đợt đi
    │
    ▼
Khai báo cơ cấu đoàn: Người lớn, Trẻ em, Em bé & Checkbox "Phòng đơn riêng"
    │
    ▼
Hàm calculate_booking_pricing() tính toán:
    Subtotal = (Adults * Price_Adult) + (Children * Price_Child) 
               + (Infants * Price_Infant) + (Single_Rooms * Single_Supplement)
    │
    ▼
Áp dụng Voucher (nếu có): Kiểm tra CURDATE() BETWEEN start AND end, Min_Amount, Limit
    │
    ▼
Tạo mã đơn BKxxxxxx ──► Lưu bảng bookings & booking_passengers
```

### 5.2. Luồng 2: Thanh toán chuyển khoản tự động qua VietQR
1. Khi đơn được khởi tạo ở bước 1, hệ thống chuyển hướng sang `booking_step2.php`.
2. Hệ thống tự động trích xuất: Số tiền cọc cần thanh toán (quy định chuẩn $50\%$ tổng giá trị đơn: `total_amount * 0.5`).
3. Tạo bản ghi thanh toán giai đoạn `Đặt cọc` ở trạng thái `Đang xử lý` trong bảng `payments`.
4. Gọi API VietQR sinh ảnh QR động theo cú pháp:
   $$\text{URL} = \text{https://img.vietqr.io/image/MB-0912345678-compact2.png?amount=}\{\text{deposit\_amount}\}\&addInfo=\{\text{booking\_code}\}$$
5. Khách hàng quét mã qua ứng dụng ngân hàng, dữ liệu chuyển khoản hiển thị chính xác mà không cần nhập tay.

### 5.3. Luồng 3: Tự động tính số chỗ (Seats Auto-sync) & Đổi trạng thái tour
Nghiệp vụ được đóng gói trong hàm `sync_departure_seats(PDO $pdo, int $departure_id)`:
* Tính tổng số ghế:
  $$\text{Booked\_Seats} = \sum (\text{num\_adults} + \text{num\_children} + \text{num\_infants})$$
  của tất cả các đơn hàng thuộc đợt đi mà có `booking_status != 'Đã hủy'`.
* Tự động điều chỉnh trạng thái đợt khởi hành (`tour_departures.status`):
  * Nếu $\text{Booked\_Seats} \ge \text{Max\_Seats}$: Chuyển sang **"Đã khóa sổ"**.
  * Nếu $(\text{Max\_Seats} - \text{Booked\_Seats}) \le 5$ (và $> 0$): Chuyển sang **"Sắp hết chỗ"**.
  * Ngược lại (khi hủy đơn nhả lại chỗ): Tự động mở bán lại với trạng thái **"Mở bán"**.

### 5.4. Luồng 4: Phân công Hướng dẫn viên & Chống xung đột lịch trình
Tại `modules/admin/guide_assign.php`, khi Quản trị viên/Điều hành tour phân bổ HDV cho một đợt khởi hành (`departure_id`), hệ thống chạy thuật toán kiểm tra giao thoa khoảng thời gian:
* Chuyến cần gán có thời gian từ ngày $D_{\text{start}}$ đến $D_{\text{end}}$.
* Câu truy vấn kiểm tra xem HDV đó đã nhận đợt khởi hành nào khác ($D'_{\text{start}}, D'_{\text{end}}$) chưa hoàn tất mà:
  $$D'_{\text{start}} \le D_{\text{end}} \quad \text{VÀ} \quad D'_{\text{end}} \ge D_{\text{start}}$$
* **Kết quả:** Nếu phát hiện trùng, hệ thống từ chối cập nhật và hiển thị cảnh báo xung đột lịch trình chi tiết; nếu không trùng, tiến hành lưu `tour_guide_id`.

### 5.5. Luồng 5: Kế toán Tất toán, Phát hành hóa đơn VAT 8% & Tích điểm
Khi đoàn hoàn tất hoặc làm thủ tục xuất hóa đơn tại `invoices_list.php`:
1. **Thu tiền tất toán:** Hệ thống tạo bản ghi thanh toán đợt 2 (Tất toán số tiền $50\%$ còn lại). Đơn hàng chuyển sang `payment_status = 'Đã thanh toán đủ'` và `booking_status = 'Hoàn tất'`.
2. **Tự động tích điểm thưởng:** Hàm `reward_points_on_booking_complete()` tự động tính:
   $$\text{Points} = \left\lfloor \frac{\text{paid\_amount}}{100.000} \right\rfloor$$
   Cộng trực tiếp vào `customers.reward_points` và đánh dấu cờ `bookings.is_rewarded = 1` để chống tích điểm trùng lặp.
3. **Phát hành Hóa đơn VAT:**
   * Tính doanh thu trước thuế: $\text{Subtotal} = \text{round}\left(\frac{\text{Total}}{1 + 0.08}\right)$.
   * Tiền thuế: $\text{VAT} = \text{Total} - \text{Subtotal}$.
   * Sinh số hóa đơn tự tăng theo năm: `HD2026-xxxx`, ký hiệu mẫu `C26TAA`.
   * Ghi nhận chi tiết vào `invoice_details`: Tự động tách riêng dòng vé người lớn, vé trẻ em và **dòng phụ thu phòng đơn** tương ứng với số khách chọn `single_room`.

---

## 6. MA TRẬN PHÂN QUYỀN (RBAC & RACI)

Hệ thống triển khai kiểm soát truy cập dựa trên vai trò (Role-Based Access Control) nghiêm ngặt thông qua file `includes/auth.php` với hàm `require_roles([...])`:

| Phân hệ / Chức năng | Admin | Sales Staff | Tour Operator | Tour Guide | Accountant | Customer |
|---|:---:|:---:|:---:|:---:|:---:|:---:|
| Xem danh mục & Đặt tour trực tuyến | **I** | **I** | **I** | **I** | **I** | **R / A** |
| Quản lý Đơn đặt tour (`booking_manager.php`) | **A** | **R** | **I** | **-** | **R** | **-** |
| Quản lý Tour & Lịch khởi hành (`tour_manager.php`) | **A** | **I** | **R** | **-** | **-** | **-** |
| Phân công Hướng dẫn viên (`guide_assign.php`) | **A** | **I** | **R** | **-** | **-** | **-** |
| Xem lịch dẫn đoàn & Danh sách khách (Manifest) | **A** | **-** | **A** | **R** | **-** | **-** |
| Lập Hóa đơn VAT & Tất toán (`invoices_list.php`) | **A** | **-** | **-** | **-** | **R** | **-** |
| Báo cáo doanh thu & Khóa sổ kế toán | **A** | **-** | **-** | **-** | **R** | **-** |
| Quản trị Tài khoản & Nhân sự (`staff_manager.php`) | **R / A** | **-** | **-** | **-** | **-** | **-** |
| Đánh giá chất lượng sau tour (`submit_review.php`) | **A** | **I** | **-** | **-** | **-** | **R** |

*Chú thích:* **R** - Responsible (Người thực hiện chính); **A** - Accountable (Người chịu trách nhiệm duyệt cao nhất); **I** - Informed (Được thông báo/Xem thông tin); **-** - Bị hệ thống chặn truy cập.

---

## 7. HƯỚNG DẪN CÀI ĐẶT MÔI TRƯỜNG

### 7.1. Yêu cầu hệ thống
* Máy chủ cục bộ: Khuyến nghị sử dụng **Laragon** (hoặc XAMPP 8.2 trở lên).
* Phiên bản PHP: Tối thiểu PHP 8.2.0 (Bật các extension: `pdo_mysql`, `mbstring`, `openssl`).
* MySQL: 8.0 trở lên.

### 7.2. Các bước triển khai chi tiết
1. **Sao chép mã nguồn vào thư mục Web Root:**
   * Đối với Laragon: Sao chép thư mục dự án vào `C:\laragon\www\Quan_ly_du_an_phan_mem`
   * Đối với XAMPP: Sao chép thư mục dự án vào `C:\xampp\htdocs\Quan_ly_du_an_phan_mem`

2. **Khởi tạo Cơ sở Dữ liệu:**
   * Mở trình duyệt truy cập `http://localhost/phpmyadmin/`.
   * Tạo cơ sở dữ liệu mới có tên: `tour_dulich_vietnam` với bảng mã `utf8mb4_unicode_ci`.
   * Chọn tab **Nhập (Import)**, chọn tệp tin `tour_dulich_vietnam.sql` trong thư mục gốc của dự án và nhấn **Thực hiện (Go)**.

3. **Cấu hình kết nối CSDL (`config/database.php`):**
   Kiểm tra thông số kết nối phù hợp với máy của bạn:
   ```php
   <?php
   // config/database.php
   $host = '127.0.0.1';
   $dbname = 'tour_dulich_vietnam';
   $username = 'root';
   $password = ''; // Để trống nếu dùng XAMPP / Laragon mặc định
   $charset = 'utf8mb4';

   $dsn = "mysql:host=$host;dbname=$dbname;charset=$charset";
   $options = [
       PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
       PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
       PDO::ATTR_EMULATE_PREPARES   => false,
   ];

   try {
       $pdo = new PDO($dsn, $username, $password, $options);
   } catch (PDOException $e) {
       die("Lỗi kết nối cơ sở dữ liệu: " . $e->getMessage());
   }
   ```

4. **Cấu hình đường dẫn cơ sở (`config/constants.php`):**
   Đảm bảo `BASE_URL` trỏ đúng tên thư mục chứa mã nguồn:
   ```php
   <?php
   // config/constants.php
   define('BASE_URL', 'http://localhost/Quan_ly_du_an_phan_mem/');
   ```

5. **Khởi chạy ứng dụng:**
   * Mở trình duyệt và truy cập: `http://localhost/Quan_ly_du_an_phan_mem/index.php`.

---

## 8. TÀI KHOẢN KIỂM THỬ & HƯỚNG DẪN SỬ DỤNG THEO VAI TRÒ

Mọi tài khoản dưới đây đã được mã hóa Bcrypt trong CSDL mẫu với **mật khẩu chung là: `123456`**.

| Vai trò | Tên đăng nhập | Mật khẩu | Họ và tên | Kịch bản kiểm thử đề xuất |
|---|:---:|:---:|---|---|
| **Quản trị viên (Admin)** | `hieu_pm` | `123456` | Nguyễn Trung Hiếu | Đăng nhập $\rightarrow$ Vào **Cổng Quản Trị** $\rightarrow$ Quản lý nhân sự, xem biểu đồ doanh thu toàn cơ quan, can thiệp duyệt mọi quy trình. |
| **Điều hành Tour (Operator)** | `huy_dev` | `123456` | Huỳnh Công Huy | Vào menu **Phân công Hướng dẫn viên** $\rightarrow$ Thử phân công HDV `guide_nam` vào chuyến trùng lịch để kiểm tra thông báo lỗi xung đột. |
| **Bán hàng (Sales Staff)** | `khai_qa` | `123456` | Trương Quốc Khải | Vào **Quản lý Đặt tour** $\rightarrow$ Duyệt thanh toán cọc $50\%$ cho các đơn mới đặt $\rightarrow$ Đổi trạng thái hoặc hủy đơn để kiểm tra tự động nhả lại ghế. |
| **Hướng dẫn viên (Tour Guide)** | `guide_nam` | `123456` | Trần Văn Nam | Đăng nhập $\rightarrow$ Thấy menu nổi bật **"Lịch phân công của tôi"** (kèm số lượng chuyến) $\rightarrow$ Bấm xem **Danh sách đoàn**, kiểm tra danh sách khách phòng đơn và in điểm danh. |
| **Kế toán viên (Accountant)** | `ketoan_hoa` | `123456` | Trần Thị Lan Hoa | Vào **Hóa đơn điện tử VAT** $\rightarrow$ Thực hiện **Tất toán & Xuất Hóa Đơn** cho đơn `#BK202611001` $\rightarrow$ Kiểm tra tự động tính thuế 8% và dòng phụ thu phòng đơn. |
| **Du khách (Customer)** | `huy_khach` | `123456` | Nguyễn Văn Huy | Đăng nhập $\rightarrow$ Chọn tour An Giang $\rightarrow$ Đặt tour cho 2 người (chọn 1 phòng đơn) $\rightarrow$ Áp mã `DUXUAN2026` $\rightarrow$ Xem mã VietQR tự động. |

---

## 9. BIỆN PHÁP AN TOÀN THÔNG TIN & QUẢN TRỊ RỦI RO

1. **Phòng chống SQL Injection:** Toàn bộ các thao tác truy vấn động (SELECT, INSERT, UPDATE, DELETE) đều sử dụng cú pháp **Prepared Statements** thông qua PDO (`$stmt->prepare()` và `$stmt->execute()`), tuyệt đối không nối chuỗi trực tiếp dữ liệu từ `$_GET` hoặc `$_POST`.
2. **Phòng chống tấn công XSS (Cross-Site Scripting):** Mọi dữ liệu xuất ra màn hình HTML từ cơ sở dữ liệu đều được bao bọc qua hàm xử lý `htmlspecialchars($data, ENT_QUOTES, 'UTF-8')`.
3. **Bảo mật mật khẩu:** Ứng dụng áp dụng giải pháp băm mật khẩu chuẩn công nghiệp PHP `password_hash($password, PASSWORD_BCRYPT)` với độ phức tạp cao, ngăn ngừa nguy cơ lộ lọt mật khẩu qua bảng tra cứu Rainbow Table.
4. **Kiểm soát truy cập trực tiếp (IDOR Prevention):** Phân hệ khách hàng (`my_bookings.php`) và kế toán đều đối soát quyền sở hữu thông qua `user_id` trong `$_SESSION`, người dùng không thể đổi ID trên thanh địa chỉ URL để xem trộm thông tin hành khách hoặc số CCCD của đoàn khác.
5. **Giao dịch an toàn CSDL (ACID Transactions):** Tất cả các nghiệp vụ có tính chất liên hoàn (như Đặt tour - Trừ số ghế - Ghi nhận thanh toán - Xuất hóa đơn) đều được bọc trong khối giao dịch `$pdo->beginTransaction()` và `$pdo->rollBack()`, đảm bảo tính nhất quán tuyệt đối của dữ liệu khi xảy ra sự cố.

---
*Bản quyền tài liệu thuộc về Nhóm Đồ án Quản lý Dự án Phần mềm - 2026.*