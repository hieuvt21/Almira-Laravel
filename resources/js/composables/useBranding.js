import { computed, watch } from 'vue';
import { usePage } from '@inertiajs/vue3';

const SHADES = [50, 100, 200, 300, 400, 500, 600, 700, 800, 900, 950];
const FALLBACK = { name: 'Almira', theme: 'teal', logo: { type: 'icon', icon: 'letter', image_url: null } };

// ---- Tên cửa hàng cho tiêu đề tab ----
let brandName = import.meta.env.VITE_APP_NAME || 'Almira';
export const getBrandName = () => brandName;
export const setBrandName = (name) => {
    if (name) brandName = name;
};

// ---- Màu chủ đề ----
// Đổi màu chủ đạo của toàn bộ giao diện (PrimeVue + CSS riêng) bằng cách trỏ
// các biến --p-primary-* sang bảng màu được chọn.
export function applyTheme(key) {
    const root = document.documentElement;
    SHADES.forEach((shade) => root.style.setProperty(`--p-primary-${shade}`, `var(--p-${key || 'teal'}-${shade})`));
}

// ---- Favicon theo logo ----
let faviconKey = '';

const cssVar = (name, fallback) =>
    getComputedStyle(document.documentElement).getPropertyValue(name).trim() || fallback;

// Vẽ favicon từ logo dạng chữ cái / biểu tượng: nền màu chủ đề + ký hiệu trắng
async function drawFavicon(branding) {
    const size = 64;
    const canvas = document.createElement('canvas');
    canvas.width = size;
    canvas.height = size;
    const ctx = canvas.getContext('2d');

    const gradient = ctx.createLinearGradient(0, 0, size, size);
    gradient.addColorStop(0, cssVar('--p-primary-500', '#14b8a6'));
    gradient.addColorStop(1, cssVar('--p-primary-700', '#0f766e'));
    ctx.fillStyle = gradient;
    ctx.beginPath();
    ctx.roundRect(0, 0, size, size, 15);
    ctx.fill();

    ctx.fillStyle = '#ffffff';
    ctx.textAlign = 'center';
    ctx.textBaseline = 'middle';

    const icon = branding.logo?.icon;
    if (icon && icon !== 'letter') {
        // Lấy đúng ký tự của biểu tượng PrimeIcons từ CSS
        const probe = document.createElement('i');
        probe.className = `pi ${icon}`;
        probe.style.cssText = 'position:absolute;visibility:hidden';
        document.body.appendChild(probe);
        const glyph = getComputedStyle(probe, '::before').content.replace(/["']/g, '');
        probe.remove();

        await document.fonts.load('36px primeicons');
        ctx.font = '36px primeicons';
        ctx.fillText(glyph, size / 2, size / 2 + 2);
    } else {
        await document.fonts.load('700 38px "Be Vietnam Pro"');
        ctx.font = '700 38px "Be Vietnam Pro", system-ui, sans-serif';
        ctx.fillText((branding.name || 'A').trim().charAt(0).toUpperCase() || 'A', size / 2, size / 2 + 3);
    }

    return canvas.toDataURL('image/png');
}

async function updateFavicon(branding) {
    const key = JSON.stringify([branding.name, branding.theme, branding.logo]);
    if (key === faviconKey) return;
    faviconKey = key;

    try {
        const useImage = branding.logo?.type === 'image' && branding.logo?.image_url;
        const href = useImage ? branding.logo.image_url : await drawFavicon(branding);
        if (key !== faviconKey) return; // logo đã đổi trong lúc đang vẽ

        let link = document.querySelector('link[rel~="icon"]');
        if (!link) {
            link = document.createElement('link');
            link.rel = 'icon';
            document.head.appendChild(link);
        }
        link.removeAttribute('type');
        link.href = href;
    } catch {
        faviconKey = ''; // thử lại ở lần sau
    }
}

export function useBranding() {
    const page = usePage();
    const branding = computed(() => page.props.branding ?? FALLBACK);

    watch(() => branding.value.theme, applyTheme, { immediate: true });

    // Tên cửa hàng đổi -> cập nhật luôn tiêu đề tab đang hiển thị
    watch(
        () => branding.value.name,
        (name, old) => {
            setBrandName(name);
            if (old && old !== name) {
                if (document.title === old) document.title = name;
                else if (document.title.endsWith(` - ${old}`)) document.title = document.title.slice(0, -old.length) + name;
            }
        },
        { immediate: true },
    );

    watch(
        () => [branding.value.name, branding.value.theme, branding.value.logo],
        () => updateFavicon(branding.value),
        { immediate: true, deep: true },
    );

    return { branding };
}
