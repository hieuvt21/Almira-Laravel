import '../css/app.css';
import '../css/almira.css';
import './bootstrap';
import '@fontsource/be-vietnam-pro/400.css';
import '@fontsource/be-vietnam-pro/500.css';
import '@fontsource/be-vietnam-pro/600.css';
import '@fontsource/be-vietnam-pro/700.css';
import 'primeicons/primeicons.css';
import './composables/useUiSettings'; // áp dụng ảnh nền đã lưu ngay khi mở app
import { getBrandName, setBrandName } from './composables/useBranding';

import { createInertiaApp } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { createApp, h } from 'vue';
import { ZiggyVue } from '../../vendor/tightenco/ziggy';

import PrimeVue from 'primevue/config';
import ToastService from 'primevue/toastservice';
import ConfirmationService from 'primevue/confirmationservice';
import { definePreset } from '@primeuix/themes';
import Aura from '@primeuix/themes/aura';

// Aura với màu chủ đạo teal. Nút/điểm nhấn dùng sắc đậm hơn để chữ trắng đủ tương phản.
const Almira = definePreset(Aura, {
    semantic: {
        primary: {
            50: '{teal.50}',
            100: '{teal.100}',
            200: '{teal.200}',
            300: '{teal.300}',
            400: '{teal.400}',
            500: '{teal.500}',
            600: '{teal.600}',
            700: '{teal.700}',
            800: '{teal.800}',
            900: '{teal.900}',
            950: '{teal.950}',
        },
        colorScheme: {
            light: {
                primary: {
                    color: '{primary.700}',
                    inverseColor: '#ffffff',
                    hoverColor: '{primary.800}',
                    activeColor: '{primary.900}',
                },
            },
        },
    },
});

createInertiaApp({
    // Tiêu đề tab: "Trang - Tên cửa hàng" (tên lấy từ cài đặt Giao diện)
    title: (title) => (title ? `${title} - ${getBrandName()}` : getBrandName()),
    resolve: (name) =>
        resolvePageComponent(
            `./Pages/${name}.vue`,
            import.meta.glob('./Pages/**/*.vue'),
        ),
    setup({ el, App, props, plugin }) {
        setBrandName(props.initialPage?.props?.branding?.name);

        return createApp({ render: () => h(App, props) })
            .use(plugin)
            .use(ZiggyVue)
            .use(PrimeVue, {
                theme: {
                    preset: Almira,
                    options: { darkModeSelector: false },
                },
            })
            .use(ToastService)
            .use(ConfirmationService)
            .mount(el);
    },
    progress: {
        color: '#0F766E',
    },
});
