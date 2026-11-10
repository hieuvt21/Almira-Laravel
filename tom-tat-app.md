# Almira POS – tóm tắt cho AI

POS cho **cửa hàng tạp hóa** (1 cửa hàng, 1 kho). Người dùng nói tiếng Việt: **trả lời và viết giao diện bằng tiếng Việt**. Trả lời ngắn gọn, nói rõ phần nào đã kiểm tra, phần nào chưa.

## Công nghệ và môi trường
- Laravel 13, PHP ^8.3, MySQL. Inertia 2 + Vue 3 (`<script setup>`) + PrimeVue **4** (Aura, KHÔNG dùng v5 vì cần license) + PrimeIcons + Vite + Tailwind 3. Spatie `laravel-permission`.
- Dev: Windows + Laragon (`D:\laragon\www\Almira`, `http://almira.test`). Chạy thật: **cPanel** (không Node, không Redis, không SSH): `SESSION_DRIVER=database`, `CACHE_STORE=file`, `QUEUE_CONNECTION=database`.
- **Deploy tự động**: push vào `main` → GitHub Actions (PHP 8.4, Node 22) cài vendor, build Vite, nén `deploy.zip`, tải qua FTP → `deploy-helper.php` giải nén, chạy `migrate --force` + `RolePermissionSeeder`, xóa cache. `public/build` không commit. Vì push `main` là lên bản thật, thay đổi phải đã kiểm tra.
- Ảnh tải lên lưu ở `public/uploads/{products,avatars,branding}` (không dùng `storage:link`).
- Không dùng `nwidart/laravel-modules` (đã gỡ): code module nằm trong `app/` và `resources/js/Pages/<Module>/`.
- Giao diện kính mờ, font Be Vietnam Pro, CSS riêng `resources/css/almira.css` (tiền tố `al-`), alias `@` = `resources/js`.

## Tiến độ
**Đã có:** đăng nhập (tên đăng nhập/SĐT/email), người dùng, vai trò & quyền, giao diện, cửa hàng (thông tin, tiền tệ & thuế, đánh số chứng từ), hồ sơ, Dashboard (khu báo cáo còn trống), Catalog (danh mục, đơn vị tính, bảng giá), Sản phẩm, Khách hàng (+ nhập/xuất Excel chỉ admin), Hạng khách hàng.
**Chưa làm, theo thứ tự:** Kho → Nhập hàng → Bán hàng/POS (hóa đơn, thanh toán, hoàn trả; kèm cài đặt mẫu hóa đơn, phương thức thanh toán, chính sách giảm giá/hoàn trả) → Ca làm việc → Báo cáo. Chưa có nhật ký hoạt động. Menu đã khai báo sẵn các mục này, tự hiện khi route tồn tại.

## Quy tắc nghiệp vụ cho Kho/POS
- **Giá**: mỗi sản phẩm một đơn vị tính (`unit_id`), thuế chung (`tax_rate`); giá ở `product_prices` (mỗi bảng giá một dòng; bán lẻ = bảng giá mặc định, bắt buộc). POS luôn chọn thủ công được bảng giá bất kể số lượng.
- **Giá theo số lượng** (`products.qty_pricing`, mặc định tắt): mỗi bảng giá khác bán lẻ có `min_qty` (trống = chỉ chọn thủ công; trong một sản phẩm không được trùng). Khi bật và số lượng dòng >= `min_qty` thì POS hiện popup hỏi áp dụng giá đó (nhiều mức: lấy `min_qty` lớn nhất đã đạt); đồng ý thì dòng dùng giá bảng giá ấy. Khi tắt, `min_qty` cũ được giữ nhưng không áp dụng.
- **Tồn kho** (`products.track_stock`, mặc định tắt) + `opening_stock` theo đơn vị tính: chỉ sản phẩm bật mới bị trừ kho khi bán và **không cho tồn âm**; sản phẩm tắt thì bỏ qua kho. Module Kho lấy `opening_stock` làm phiếu nhập tồn đầu kỳ.
- `products.cost_price` (mặc định 0) dành cho module Nhập hàng; form sản phẩm không nhập giá vốn. Không có đơn vị quy đổi, không có biến thể (đã bỏ).
- **Khách hàng**: `customers.total_spent` do POS cộng dồn; giảm giá theo hạng: `CustomerTiers::discountFor($total_spent)` (trả %). Hạng cấu hình ở Setting `customer_tiers`.

## Phân quyền
- Vai trò mặc định `admin`, `manager`, `cashier`, `warehouse` (không xóa/đổi tên được); admin có toàn quyền qua `Gate::before`. Seeder chỉ gán quyền mặc định khi vai trò mới tạo, không ghi đè phần đã chỉnh. Admin tạo thêm vai trò ở giao diện.
- Quyền `module.action`: `users.*`, `roles.manage`, `products.*`, `inventory.view|adjust`, `sales.pos|view|refund`, `customers.*`, `purchases.view|create|update`, `shifts.own|manage`, `reports.view`, `settings.manage`. Khai báo ở `database/seeders/RolePermissionSeeder.php`.
- Route bảo vệ bằng `can:<quyền>` (hoặc `role:admin`) trong `routes/web.php`; giao diện dùng `usePermission().can('...')`. Mọi vai trò sau đăng nhập vào `/dashboard`. Không có đăng ký công khai.
- Seeder chỉ tạo tài khoản **`admin` / mật khẩu `admin`** (đổi ngay); tài khoản khác tạo ở giao diện.

## Dữ liệu dùng chung
- Inertia shared props: `auth.user {id,name,username,email,avatar_url,roles,permissions}`, `branding`, `finance`, `flash {success,error}` (controller dùng `->with('success'|'error', ...)`, layout tự hiện Toast).
- Bảng: `users`, bảng Spatie, `settings` (key/value), `document_sequences`, `categories`, `units` (một đơn vị mặc định), `price_lists` (một bảng mặc định), `products` (code, barcode unique, category_id, unit_id, tax_rate, qty_pricing, track_stock, opening_stock, cost_price, image, description, is_active), `product_prices` (product_id+price_list_id unique, price, min_qty), `customers` (code, name, phone unique 10 số, address, occupation, note, birth_date/birth_year, total_spent, sort_name tự sinh, avatar).

## Thành phần dùng lại
**Backend**
- `Setting` (`read/write/json/putJson/storeInfo/finance/branding`, có cache).
- `DocumentNumber::next('invoice')` cấp mã chứng từ an toàn song song. Loại: `invoice` (HD), `sale_return` (TH), `purchase` (PN), `stock_check` (KK), `cash_in` (PT), `cash_out` (PC), `shift` (CA), `product` (SP), `customer` (KH), `supplier` (NCC).
- `Money::round()/format()` theo cài đặt cửa hàng. `Xlsx` (đọc/ghi Excel). Trait `ManagesIdentity` (chuẩn hóa username/email/phone, avatar), `HasUploadedImage` (ảnh sản phẩm).

**Frontend**
- `Layouts/AppLayout.vue` (`<AppLayout title="...">`); `config/navigation.js` (mục `{label, icon, route, match, can}`).
- Composables: `usePermission`, `useMoney` (`money()`, `round()`), `useUniqueCheck` (kiểm tra trùng khi nhập, endpoint dạng `*.check`), `useBranding`, `useUiSettings`.
- Components: `AppDialog` (dùng thay `Dialog`, tự focus ô đầu), `PhoneInput`, `FieldStatus`, `ImageInput`, `AvatarPicker`, `UserAvatar`, `TierBadge`, `BrandMark`.
- Tiện ích: `utils/{money,numbering,phone,labels}.js`.

## Quy ước viết module mới
1. Migration + Model (dùng `#[Fillable([...])]`, thêm cột mới vào đó).
2. Controller kiểu `UserController`: validate trong controller, lỗi tiếng Việt, `redirect()->route(...)->with('success', ...)`.
3. Route trong `routes/web.php`, nhóm `auth`, mỗi route có `can:<quyền>`; route cố định (`/x/check`) đặt trước route có `{id}`.
4. Thêm quyền vào `RolePermissionSeeder` (+ gán vai trò) và nhãn vào `utils/labels.js` (`MODULE_LABELS`, `ACTION_LABELS`).
5. Trang `Pages/<Module>/Index.vue` theo mẫu `Pages/Users/Index.vue`: `<Head>` + `<AppLayout>`, panel `al-panel al-fill`, `DataTable`, thêm/sửa trong `AppDialog`, `useForm`, xóa bằng `useConfirm`.
6. Thêm mục vào `config/navigation.js`.
7. Mã tự sinh dùng `DocumentNumber::next(...)`; tiền luôn qua `useMoney`/`Money`, không viết cứng `₫`.
8. Form: ô đầu tự focus, lỗi hiện dưới ô, hỗ trợ bàn phím và `aria-label`; ô bắt buộc đánh dấu sao đỏ (class `al-req`).

## Lưu ý đã gặp lỗi
- `bootstrap/app.php` phải có `$middleware->web(append: [HandleInertiaRequests::class, ...])`, thiếu thì mất toàn bộ shared props.
- Mật khẩu: model đã cast `hashed`, truyền chuỗi thường, không `bcrypt()`.
- Seeder dùng `WithoutModelEvents` nên phải gọi `forgetCachedPermissions()` sau khi tạo quyền.
- Email cho phép rỗng; username 10 chữ số bị cấm (tránh nhầm số điện thoại).

## Làm việc với AI
- Chạy `node scripts/bundle.mjs <module...>` để gom file đúng module (kèm phụ thuộc) thành `bundles/bundle-<tên>.md`; bản đồ ở `docs/module-map.json` (làm xong module nào thì điền file của nó vào đó).
- Giao code: **zip giữ nguyên đường dẫn** từ thư mục gốc dự án (giải nén đè), kèm lệnh cần chạy (`php artisan migrate`, `npm install ...`), danh sách file cần xóa, lưu ý chạy thử.
