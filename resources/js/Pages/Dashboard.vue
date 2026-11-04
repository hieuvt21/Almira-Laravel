<script setup>
import { computed } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { usePermission } from '@/composables/usePermission';
import { useNavigation } from '@/composables/useNavigation';
import { roleLabel } from '@/utils/labels';

// Trang chung cho mọi tài khoản sau khi đăng nhập.
// Phần "Báo cáo & thông tin" sẽ được bổ sung khi các module có dữ liệu.
const { user, roles } = usePermission();
const { groups } = useNavigation();

const roleText = computed(() => roles.value.map(roleLabel).join(', '));
const shortcuts = computed(() =>
    groups.value.flatMap((g) => g.items).filter((i) => i.route !== 'dashboard'),
);
</script>

<template>
    <Head title="Bảng điều khiển" />

    <AppLayout title="Bảng điều khiển">
        <div class="al-panel" style="margin-bottom: 1rem">
            <div class="dash-head">
                <h2 class="dash-hello">Xin chào, {{ user?.name }}</h2>
                <p class="dash-sub">Bạn đang đăng nhập với vai trò {{ roleText }}.</p>
            </div>
        </div>

        <div class="al-panel" style="margin-bottom: 1rem">
            <div class="al-toolbar">
                <p class="al-toolbar-note">Lối tắt</p>
            </div>
            <div v-if="shortcuts.length" class="dash-grid">
                <Link v-for="item in shortcuts" :key="item.route" :href="route(item.route)" class="dash-tile">
                    <span class="dash-ico"><i :class="item.icon" aria-hidden="true" /></span>
                    <span>{{ item.label }}</span>
                </Link>
            </div>
            <p v-else class="dash-empty">Chưa có lối tắt nào cho tài khoản này.</p>
        </div>

        <div class="al-panel al-fill">
            <div class="al-toolbar">
                <p class="al-toolbar-note">Báo cáo &amp; thông tin</p>
            </div>
            <div class="dash-slot">
                <i class="pi pi-chart-line" aria-hidden="true" />
                <strong>Khu vực báo cáo</strong>
                <span>Biểu đồ doanh thu, đơn hàng, tồn kho... sẽ hiển thị tại đây khi các module tương ứng được thêm.</span>
            </div>
        </div>
    </AppLayout>
</template>
