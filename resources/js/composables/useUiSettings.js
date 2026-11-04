import { reactive, watch } from 'vue';

const KEY = 'almira.ui';
const defaults = { sidebarMode: 'pinned', background: 'theme' };

export const BACKGROUNDS = [
    { key: 'theme', label: 'Theo màu chủ đề', preview: 'linear-gradient(135deg, var(--p-primary-200), var(--p-primary-100) 55%, var(--p-primary-300))' },
    { key: 'aurora', label: 'Cực quang', preview: 'linear-gradient(135deg, #99f6e4, #c7d2fe 55%, #fde68a)' },
    { key: 'dusk', label: 'Hoàng hôn', preview: 'linear-gradient(135deg, #ddd6fe, #fbcfe8 55%, #bae6fd)' },
    { key: 'sand', label: 'Cát', preview: 'linear-gradient(135deg, #fde68a, #fed7aa 55%, #d9f99d)' },
];

function load() {
    try {
        const saved = JSON.parse(localStorage.getItem(KEY) ?? '{}');
        return {
            sidebarMode: ['pinned', 'hover'].includes(saved.sidebarMode) ? saved.sidebarMode : defaults.sidebarMode,
            background: BACKGROUNDS.some((b) => b.key === saved.background) ? saved.background : defaults.background,
        };
    } catch {
        return { ...defaults };
    }
}

// Trạng thái dùng chung cho cả app, nhớ lại ở lần mở sau
const settings = reactive(load());

const apply = () => {
    document.documentElement.dataset.bg = settings.background;
};
apply();

watch(settings, () => {
    apply();
    try {
        localStorage.setItem(KEY, JSON.stringify({ sidebarMode: settings.sidebarMode, background: settings.background }));
    } catch {
        // trình duyệt chặn lưu trữ: bỏ qua
    }
});

export function useUiSettings() {
    const toggleSidebarMode = () => {
        settings.sidebarMode = settings.sidebarMode === 'pinned' ? 'hover' : 'pinned';
    };
    return { settings, toggleSidebarMode, backgrounds: BACKGROUNDS };
}
