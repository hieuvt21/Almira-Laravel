<script setup>
import { computed, onBeforeUnmount, ref } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import Button from 'primevue/button';
import InputText from 'primevue/inputtext';
import SelectButton from 'primevue/selectbutton';
import AppLayout from '@/Layouts/AppLayout.vue';
import BrandMark from '@/Components/BrandMark.vue';
import { applyTheme, useBranding } from '@/composables/useBranding';
import { usePermission } from '@/composables/usePermission';
import { useUiSettings } from '@/composables/useUiSettings';

defineProps({
    themes: { type: Array, default: () => [] },
    icons: { type: Array, default: () => [] },
});

const THEME_LABELS = {
    teal: 'Xanh ngọc',
    emerald: 'Lục bảo',
    sky: 'Xanh da trời',
    blue: 'Xanh dương',
    indigo: 'Chàm',
    violet: 'Tím',
    rose: 'Hồng',
    orange: 'Cam',
};

const { can } = usePermission();
const { branding } = useBranding();
const { settings, backgrounds } = useUiSettings();

const canEdit = computed(() => can('settings.manage'));

const form = useForm({
    shop_name: branding.value.name,
    theme: branding.value.theme,
    logo_type: branding.value.logo.type,
    logo_icon: branding.value.logo.icon,
    logo_file: null,
});

const modes = [
    { label: 'Cố định', value: 'pinned' },
    { label: 'Tự động', value: 'hover' },
];

// ---- logo ----
const fileInput = ref();
const filePreview = ref(null);
const fileError = ref('');

const logoPreview = computed(() => ({
    type: form.logo_type,
    icon: form.logo_icon,
    image_url: filePreview.value ?? branding.value.logo.image_url,
}));

function revokePreview() {
    if (filePreview.value) URL.revokeObjectURL(filePreview.value);
    filePreview.value = null;
}

function pickFile(e) {
    const f = e.target.files?.[0];
    e.target.value = '';
    if (!f) return;
    if (!['image/jpeg', 'image/png', 'image/webp'].includes(f.type)) {
        fileError.value = 'Logo phải là ảnh JPG, PNG hoặc WebP.';
        return;
    }
    if (f.size > 1024 * 1024) {
        fileError.value = 'Ảnh logo tối đa 1 MB.';
        return;
    }
    fileError.value = '';
    revokePreview();
    filePreview.value = URL.createObjectURL(f);
    form.logo_file = f;
    form.logo_type = 'image';
}

function chooseIcon(key) {
    form.logo_icon = key;
    form.logo_type = 'icon';
}

function chooseTheme(key) {
    form.theme = key; // đổi màu xem thử ngay, lưu mới có hiệu lực cho mọi người
    applyTheme(key);
}

function save() {
    form.post(route('appearance.branding'), {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => {
            form.logo_file = null;
            revokePreview();
        },
    });
}

onBeforeUnmount(revokePreview);
</script>

<template>
    <Head title="Giao diện" />

    <AppLayout title="Giao diện">
        <div class="ap-grid">
            <!-- Thương hiệu cửa hàng: dùng chung cho mọi người -->
            <section class="al-panel">
                <div class="al-toolbar">
                    <p class="al-toolbar-note"><strong>Thương hiệu cửa hàng</strong> · áp dụng cho mọi tài khoản</p>
                </div>
                <form class="ap-body" @submit.prevent="save">
                    <p v-if="!canEdit" class="ap-note">Chỉ tài khoản có quyền Cài đặt mới thay đổi được phần này.</p>

                    <fieldset class="ap-fieldset" :disabled="!canEdit">
                        <div class="field">
                            <label for="shop-name">Tên cửa hàng</label>
                            <div class="ap-name-row">
                                <BrandMark :logo="logoPreview" :name="form.shop_name" large />
                                <InputText id="shop-name" v-model="form.shop_name" maxlength="60" autocomplete="off" />
                            </div>
                            <small v-if="form.errors.shop_name" class="field-error">{{ form.errors.shop_name }}</small>
                        </div>

                        <div class="set-block">
                            <h3 class="set-title">Logo</h3>
                            <div class="icon-grid" role="group" aria-label="Biểu tượng logo có sẵn">
                                <button
                                    v-for="key in icons"
                                    :key="key"
                                    type="button"
                                    class="icon-opt"
                                    :class="{ 'is-on': form.logo_type === 'icon' && form.logo_icon === key }"
                                    :aria-pressed="form.logo_type === 'icon' && form.logo_icon === key"
                                    :aria-label="key === 'letter' ? 'Chữ cái đầu của tên cửa hàng' : `Biểu tượng ${key.replace('pi-', '')}`"
                                    @click="chooseIcon(key)"
                                >
                                    <span v-if="key === 'letter'">{{ (form.shop_name || 'A').trim().charAt(0).toUpperCase() }}</span>
                                    <i v-else :class="['pi', key]" aria-hidden="true" />
                                </button>
                            </div>

                            <div class="logo-upload">
                                <Button
                                    type="button"
                                    label="Tải logo lên"
                                    icon="pi pi-upload"
                                    size="small"
                                    severity="secondary"
                                    outlined
                                    @click="fileInput.click()"
                                />
                                <small class="field-hint">JPG, PNG hoặc WebP, tối đa 1 MB. Nên dùng ảnh vuông.</small>
                                <input ref="fileInput" type="file" accept="image/png,image/jpeg,image/webp" hidden @change="pickFile" />
                            </div>
                            <small v-if="fileError || form.errors.logo_file" class="field-error" role="alert">
                                {{ fileError || form.errors.logo_file }}
                            </small>
                        </div>

                        <div class="set-block">
                            <h3 class="set-title">Màu chủ đề</h3>
                            <div class="theme-grid" role="group" aria-label="Màu chủ đề">
                                <button
                                    v-for="t in themes"
                                    :key="t"
                                    type="button"
                                    class="theme-opt"
                                    :class="{ 'is-on': form.theme === t }"
                                    :aria-pressed="form.theme === t"
                                    @click="chooseTheme(t)"
                                >
                                    <span class="theme-dot" :style="{ background: `var(--p-${t}-600)` }">
                                        <i v-if="form.theme === t" class="pi pi-check" aria-hidden="true" />
                                    </span>
                                    <span>{{ THEME_LABELS[t] ?? t }}</span>
                                </button>
                            </div>
                        </div>

                        <div class="dialog-actions" style="margin-top: 0">
                            <Button type="submit" label="Lưu thương hiệu" icon="pi pi-check" :loading="form.processing" />
                        </div>
                    </fieldset>
                </form>
            </section>

            <!-- Cá nhân hóa: lưu trên trình duyệt của từng người -->
            <section class="al-panel">
                <div class="al-toolbar">
                    <p class="al-toolbar-note"><strong>Cá nhân hóa</strong> · chỉ áp dụng trên thiết bị này</p>
                </div>
                <div class="ap-body">
                    <div class="set-block">
                        <h3 class="set-title">Thanh bên</h3>
                        <SelectButton
                            v-model="settings.sidebarMode"
                            :options="modes"
                            optionLabel="label"
                            optionValue="value"
                            :allowEmpty="false"
                            aria-label="Chế độ thanh bên"
                        />
                        <p class="field-hint" style="margin-top: 0.6rem">
                            <strong>Cố định:</strong> luôn mở rộng.<br />
                            <strong>Tự động:</strong> thu gọn thành cột biểu tượng, mở ra khi rê chuột vào.
                        </p>
                    </div>

                    <div class="set-block">
                        <h3 class="set-title">Màu nền</h3>
                        <div class="bg-grid">
                            <button
                                v-for="b in backgrounds"
                                :key="b.key"
                                type="button"
                                class="bg-opt"
                                :class="{ 'is-on': settings.background === b.key }"
                                :aria-pressed="settings.background === b.key"
                                @click="settings.background = b.key"
                            >
                                <span class="bg-swatch" :style="{ background: b.preview }" />
                                <span>{{ b.label }}</span>
                            </button>
                        </div>
                    </div>
                </div>
            </section>
        </div>
    </AppLayout>
</template>
