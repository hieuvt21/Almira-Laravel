import { onBeforeUnmount, reactive, ref, watch } from 'vue';

const NORMALIZE = {
    username: (v) => String(v ?? '').trim().toLowerCase(),
    email: (v) => String(v ?? '').trim().toLowerCase(),
    phone: (v) => String(v ?? '').replace(/\D/g, ''),
    code: (v) => String(v ?? '').trim(),
    barcode: (v) => String(v ?? '').trim(),
};

// Kiểm tra định dạng ngay trên trình duyệt (khớp với quy tắc ở server)
const FORMAT = {
    username: (v) => {
        if (v.length < 3) return 'Tên đăng nhập tối thiểu 3 ký tự.';
        if (!/^[a-z0-9._-]+$/.test(v)) return 'Chỉ dùng chữ thường không dấu, số, dấu chấm, gạch dưới hoặc gạch ngang.';
        if (/^\d{10}$/.test(v)) return 'Không dùng dãy 10 số làm tên đăng nhập (dễ nhầm với số điện thoại).';
        return null;
    },
    email: (v) => (/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(v) ? null : 'Email chưa đúng định dạng.'),
    phone: (v) => (/^\d{10}$/.test(v) ? null : 'Số điện thoại gồm đúng 10 số.'),
    code: (v) => (/^[A-Za-z0-9._-]{1,50}$/.test(v) ? null : 'Chỉ gồm chữ, số, dấu chấm, gạch dưới hoặc gạch ngang (tối đa 50 ký tự).'),
    barcode: (v) => (/^[A-Za-z0-9._-]{1,50}$/.test(v) ? null : 'Chỉ gồm chữ, số, dấu chấm, gạch dưới hoặc gạch ngang (tối đa 50 ký tự).'),
};

const DEBOUNCE_MS = 450;

/**
 * Kiểm tra trùng khi đang nhập.
 *
 * @param {'username'|'email'|'phone'|'code'|'barcode'} field
 * @param {() => string} getValue      giá trị đang nhập
 * @param {{ ignoreId?: () => number|null, original?: () => string|null, url?: () => string }} options
 *        ignoreId: bỏ qua bản ghi này (khi sửa); original: giá trị cũ, không cần kiểm tra lại;
 *        url: địa chỉ kiểm tra (mặc định route('users.check'); khách hàng dùng route('customers.check'), sản phẩm dùng route('products.check'))
 *
 * status: 'idle' | 'checking' | 'ok' | 'taken' | 'invalid'
 */
export function useUniqueCheck(field, getValue, { ignoreId = () => null, original = () => null, url = () => route('users.check') } = {}) {
    const status = ref('idle');
    const message = ref('');

    let timer = null;
    let seq = 0;
    let controller = null;

    const normalize = NORMALIZE[field];

    function setState(s, m = '') {
        status.value = s;
        message.value = m;
    }

    function cancel() {
        clearTimeout(timer);
        controller?.abort();
        controller = null;
        seq++;
    }

    async function run() {
        cancel();
        const value = normalize(getValue());

        if (!value || value === normalize(original())) {
            setState('idle');
            return;
        }

        const invalid = FORMAT[field](value);
        if (invalid) {
            setState('invalid', invalid);
            return;
        }

        setState('checking', 'Đang kiểm tra...');
        const mine = ++seq;
        controller = new AbortController();

        try {
            const params = new URLSearchParams({ field, value });
            const ignore = ignoreId();
            if (ignore) params.set('ignore', String(ignore));

            const res = await fetch(`${url()}?${params}`, {
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin',
                signal: controller.signal,
            });
            if (!res.ok) throw new Error(String(res.status));
            const data = await res.json();
            if (mine !== seq) return; // đã có lần nhập mới hơn

            if (data.valid === false) setState('invalid', data.message);
            else if (data.available) setState('ok', 'Có thể sử dụng.');
            else setState('taken', data.message);
        } catch (e) {
            if (e.name === 'AbortError' || mine !== seq) return;
            setState('idle'); // lỗi mạng: bỏ qua, server vẫn kiểm tra khi lưu
        }
    }

    function schedule() {
        cancel();
        setState('idle');
        if (!normalize(getValue())) return;
        timer = setTimeout(run, DEBOUNCE_MS);
    }

    watch(getValue, schedule);
    onBeforeUnmount(cancel);

    return reactive({
        status,
        message,
        check: run, // gọi ngay (ví dụ khi rời khỏi ô nhập)
        reset: () => {
            cancel();
            setState('idle');
        },
    });
}
