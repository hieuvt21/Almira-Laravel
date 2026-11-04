<script setup>
import { computed, ref, watch } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import { useToast } from 'primevue/usetoast';
import Button from 'primevue/button';
import ConfirmDialog from 'primevue/confirmdialog';
import Drawer from 'primevue/drawer';
import Menu from 'primevue/menu';
import Toast from 'primevue/toast';
import SidebarNav from '@/Components/SidebarNav.vue';
import UserAvatar from '@/Components/UserAvatar.vue';
import { useBranding } from '@/composables/useBranding';
import { usePermission } from '@/composables/usePermission';
import { useUiSettings } from '@/composables/useUiSettings';
import { roleLabel } from '@/utils/labels';

defineProps({ title: { type: String, default: '' } });

const page = usePage();
const toast = useToast();
const { user, roles, can } = usePermission();
const { settings } = useUiSettings();
useBranding(); // áp dụng màu chủ đề của cửa hàng

const drawerOpen = ref(false);
const userMenu = ref();

const roleText = computed(() => roles.value.map(roleLabel).join(', '));
const showPos = computed(() => can('sales.pos') && route().has('pos.index'));

const userItems = [
    { label: 'Hồ sơ cá nhân', icon: 'pi pi-user', command: () => router.visit(route('profile.edit')) },
    { separator: true },
    { label: 'Đăng xuất', icon: 'pi pi-sign-out', command: () => router.post(route('logout')) },
];

// Hiện thông báo flash từ server (with('success') / with('error'))
watch(
    () => page.props.flash,
    (flash) => {
        if (flash?.success) toast.add({ severity: 'success', summary: flash.success, life: 3000 });
        if (flash?.error) toast.add({ severity: 'error', summary: flash.error, life: 5000 });
    },
    { immediate: true },
);
</script>

<template>
    <div class="al-shell" :data-sidebar="settings.sidebarMode">
        <div class="al-rail">
            <aside class="al-aside glass">
                <SidebarNav pinnable />
            </aside>
        </div>

        <div class="al-main">
            <header class="al-top glass">
                <Button
                    class="al-burger"
                    icon="pi pi-bars"
                    text
                    rounded
                    severity="secondary"
                    aria-label="Mở menu"
                    @click="drawerOpen = true"
                />
                <h1 class="al-title">{{ title }}</h1>

                <Link v-if="showPos" :href="route('pos.index')" class="al-pos">
                    <i class="pi pi-desktop" aria-hidden="true" />
                    <span>POS</span>
                </Link>

                <button type="button" class="al-userbtn" aria-haspopup="true" @click="(e) => userMenu.toggle(e)">
                    <UserAvatar :name="user?.name" :url="user?.avatar_url" />
                    <span class="al-userinfo">
                        <strong>{{ user?.name }}</strong>
                        <small>{{ roleText }}</small>
                    </span>
                    <i class="pi pi-chevron-down al-chev" aria-hidden="true" />
                </button>
                <Menu ref="userMenu" :model="userItems" popup />
            </header>

            <main class="al-content">
                <slot />
            </main>
        </div>

        <!-- Menu cho điện thoại -->
        <Drawer
            v-model:visible="drawerOpen"
            position="left"
            :pt="{ root: { style: 'width:16rem' }, header: { style: 'display:none' }, content: { style: 'padding:0' } }"
        >
            <SidebarNav @navigate="drawerOpen = false" />
        </Drawer>

        <Toast position="top-right" />
        <ConfirmDialog />
    </div>
</template>
