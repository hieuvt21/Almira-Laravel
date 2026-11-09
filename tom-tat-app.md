# Almira POS – tóm tắt cho AI

Ứng dụng bán hàng (POS) cho **cửa hàng tạp hóa** (1 cửa hàng, 1 kho). Người dùng nói tiếng Việt: **trả lời và viết giao diện bằng tiếng Việt**.

## Công nghệ và môi trường
- Laravel 13, PHP 8.3, MySQL. Inertia.js (v3) + Vue 3 (`<script setup>`) + PrimeVue **4** (Aura, KHÔNG dùng v5 vì cần license) + PrimeIcons + Vite.
- Dev: Windows + Laragon (`D:\laragon\www\Almira`, `http://almira.test`). Chạy thật: **cPanel** (không Node, không Redis).
- cPanel: build ở máy local rồi upload `public/build`; `SESSION_DRIVER=database`, `CACHE_STORE=file`, `QUEUE_CONNECTION=database`; ảnh tải lên lưu vào `public/uploads/` (không dùng `storage:link`).
- Chưa dùng `nwidart/laravel-modules`: code module nằm trong `app/` và `resources/js/Pages/<Module>/`.
- Font Be Vietnam Pro (`@fontsource`), giao diện kính mờ, CSS riêng ở `resources/css/almira.css` (tiền tố `al-`), alias `@` = `resources/js`.

## Đã làm
Đăng nhập (tên đăng nhập / SĐT / email), phân quyền, Người dùng (avatar, kiểm tra trùng khi nhập), Vai trò & quyền, Giao diện (màu chủ đề, logo, tên cửa hàng, thanh bên), Cửa hàng (thông tin, tiền tệ & thuế, đánh số chứng từ), Hồ sơ cá nhân, Dashboard (trang chung sau đăng nhập, khu báo cáo còn trống), Catalog: Danh mục, Đơn vị tính, Bảng giá (trang `/catalog`, 3 tab) và Sản phẩm (tìm kiếm/lọc/phân trang ở server, kiểm tra trùng mã & mã vạch khi nhập qua `products.check`; mỗi sản phẩm có một đơn vị tính (`products.unit_id`, chọn từ tab Đơn vị tính, có một đơn vị mặc định), một ảnh, thuế chung; giá lưu ở `product_prices` (mỗi bảng giá một dòng, giá bán lẻ = bảng giá mặc định, bắt buộc). **Giá theo số lượng** (`products.qty_pricing`): khi bật, các bảng giá khác bán lẻ (ví dụ Giá sỉ) có thêm `min_qty`; khi tắt thì dữ liệu cũ được giữ nhưng không áp dụng. Quy tắc cho POS: khi số lượng của một dòng >= `min_qty` của một bảng giá thì hiện popup hỏi có áp dụng giá đó không (nhiều mức thì lấy mức có `min_qty` lớn nhất đã đạt); đồng ý thì dòng đó dùng giá của bảng giá ấy. **Quản lý tồn kho** (`products.track_stock`, mặc định tắt) kèm `opening_stock` tính theo đơn vị tính; module Kho/POS phải tuân thủ: chỉ sản phẩm bật mới bị trừ kho khi bán và không cho tồn âm, sản phẩm tắt thì bỏ qua kho; module Kho sẽ lấy `opening_stock` làm phiếu nhập tồn đầu kỳ. Không có đơn vị quy đổi và không có biến thể (đã bỏ); `products.cost_price` còn trong DB (mặc định 0) cho module Nhập hàng, form sản phẩm không nhập giá vốn. Ô bắt buộc (tên, danh mục, đơn vị tính, giá bán lẻ) đánh dấu sao đỏ class `al-req`).

**Chưa làm (dự kiến theo thứ tự):** Kho → Nhập hàng → Bán hàng/POS (hóa đơn, thanh toán, hoàn trả) → Ca làm việc → Báo cáo. Cài đặt bán hàng (mẫu hóa đơn, phương thức thanh toán, chính sách giảm giá/hoàn trả) làm cùng module bán hàng. Chưa có nhật ký hoạt động.

## Xác thực và phân quyền
- Spatie `laravel-permission`. Vai trò mặc định: `admin`, `manager`, `cashier`, `warehouse` (không xóa/đổi tên được; admin có toàn quyền qua `Gate::before`). Admin tạo thêm vai trò ở giao diện.
- Quyền dạng `module.action`: `users.*`, `roles.manage`, `products.*`, `inventory.view|adjust`, `sales.pos|view|refund`, `customers.*`, `purchases.*`, `shifts.own|manage`, `reports.view`, `settings.manage`. Khai báo trong `database/seeders/RolePermissionSeeder.php`.
- Route bảo vệ bằng middleware `can:<quyền>` trong `routes/web.php`; giao diện ẩn/hiện bằng `usePermission().can('...')`. Mọi vai trò sau đăng nhập đều vào `/dashboard`.
- Không có đăng ký công khai; admin tạo tài khoản.

## Dữ liệu dùng chung (Inertia shared props)
`auth.user {id,name,username,avatar_url,roles,permissions}`, `branding {name,theme,logo}`, `finance {...}`, `flash {success,error}` (controller dùng `->with('success'|'error', ...)`, layout tự hiện Toast).

## Bảng chính
`users` (username unique bắt buộc; phone 10 số unique, email, avatar: đều tùy chọn), bảng Spatie, `settings` (key/value), `document_sequences`, `categories`, `units` (một đơn vị mặc định), `price_lists` (một bảng mặc định), `products` (code, barcode unique, unit_id, category_id, tax_rate, qty_pricing, track_stock, opening_stock, cost_price, image), `product_prices` (product_id+price_list_id unique, price, min_qty chỉ dùng cho giá theo số lượng).

## Thành phần dùng lại
**Backend**
- `Setting` (`read/write/json/putJson/storeInfo/finance/branding`) – lưu cài đặt, có cache.
- `App\Support\DocumentNumber::next('invoice')` – cấp mã chứng từ an toàn song song. Loại: `invoice, sale_return, purchase, stock_check, cash_in, cash_out, shift, product, customer, supplier`.
- `App\Support\Money::round()/format()` – làm tròn và định dạng tiền theo cài đặt.
- Trait `Controllers\Concerns\ManagesIdentity` (chuẩn hóa username/email/phone, avatar).

**Frontend**
- `Layouts/AppLayout.vue` (`<AppLayout title="...">`), `config/navigation.js` (thêm mục menu: `{label, icon, route, match, can}`; mục chỉ hiện khi có quyền và route đã tồn tại).
- `composables`: `usePermission`, `useMoney` (`money()`, `round()`), `useUniqueCheck` (kiểm tra trùng khi nhập), `useBranding`, `useUiSettings`.
- Components: `AppDialog` (dùng thay `Dialog`, tự focus ô đầu tiên), `PhoneInput`, `FieldStatus`, `AvatarPicker`, `UserAvatar`, `BrandMark`.
- Tiện ích: `utils/money.js`, `utils/numbering.js`, `utils/phone.js`, `utils/labels.js`.

## Quy ước viết module mới
1. Migration + Model (model dùng thuộc tính `#[Fillable([...])]`, nhớ thêm cột mới vào đó).
2. Controller kiểu `UserController` (validate trong controller, thông báo lỗi tiếng Việt, `redirect()->route(...)->with('success', ...)`).
3. Route trong `routes/web.php`, nhóm `auth`, mỗi route có `can:<quyền>`.
4. Thêm quyền vào `RolePermissionSeeder` (+ gán cho vai trò) và nhãn tiếng Việt vào `utils/labels.js` (`MODULE_LABELS`, `ACTION_LABELS`).
5. Trang Vue `Pages/<Module>/Index.vue` theo mẫu `Pages/Users/Index.vue`: `<Head>` + `<AppLayout>`, panel `al-panel al-fill`, `DataTable`, thêm/sửa trong `AppDialog`, `useForm`, xóa bằng `useConfirm`.
6. Thêm mục vào `config/navigation.js`.
7. Mã tự sinh dùng `DocumentNumber::next(...)`; tiền luôn qua `useMoney`/`Money`, không viết cứng `₫`.
8. Form: ô đầu tiên được tự focus; lỗi hiện dưới ô nhập; hỗ trợ bàn phím và `aria-label`.

## Khi nhờ AI làm module mới, hãy gửi kèm
- File này
- `routes/web.php`, `resources/js/config/navigation.js`, `database/seeders/RolePermissionSeeder.php`, `resources/js/utils/labels.js`
- Mẫu tham chiếu: `app/Http/Controllers/UserController.php` và `resources/js/Pages/Users/Index.vue`
- Nếu module dùng tiền, mã hoặc cài đặt: `app/Models/Setting.php`, `app/Support/DocumentNumber.php`
- Nếu cần đổi layout hoặc CSS: `resources/js/Layouts/AppLayout.vue`, `resources/css/almira.css`
- Model/migration của các bảng liên quan (ví dụ module Bán hàng cần Catalog, Khách hàng)

## Lưu ý đã gặp lỗi
- `bootstrap/app.php` phải có `$middleware->web(append: [HandleInertiaRequests::class, ...])`, thiếu thì mất toàn bộ shared props.
- Mật khẩu: model đã cast `hashed`, truyền chuỗi thường, không `bcrypt()`.
- Seeder có `WithoutModelEvents` nên phải gọi `forgetCachedPermissions()` sau khi tạo quyền.
- Cột email cho phép rỗng; username 10 chữ số bị cấm (tránh nhầm số điện thoại).
- Tài khoản mẫu (mật khẩu `password`): `admin`, `manager`, `cashier`, `warehouse` (`@almira.test`).

## Cách giao file
Gửi gói **zip giữ nguyên đường dẫn** từ thư mục gốc dự án (người dùng giải nén đè), kèm: lệnh cần chạy (`php artisan migrate`, `npm install ...`), danh sách file cần xóa, và lưu ý chạy thử. Trả lời ngắn gọn, nói rõ phần nào đã kiểm tra, phần nào chưa.
