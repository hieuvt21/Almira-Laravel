<script setup>
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import Button from 'primevue/button';
import UserAvatar from '@/Components/UserAvatar.vue';

const props = defineProps({
    name: { type: String, default: '' },
    currentUrl: { type: String, default: null },
    presets: { type: Array, default: () => [] },
});

// v-model:file / v-model:preset / v-model:remove
const file = defineModel('file', { default: null });
const preset = defineModel('preset', { default: null });
const remove = defineModel('remove', { default: false });

const input = ref();
const blobUrl = ref(null);
const error = ref('');

const previewUrl = computed(() => {
    if (blobUrl.value) return blobUrl.value;
    if (preset.value) return props.presets.find((p) => p.key === preset.value)?.url ?? null;
    if (remove.value) return null;
    return props.currentUrl;
});

const isOn = (p) =>
    preset.value === p.key || (!preset.value && !file.value && !remove.value && props.currentUrl === p.url);

function setBlob(f) {
    if (blobUrl.value) URL.revokeObjectURL(blobUrl.value);
    blobUrl.value = f ? URL.createObjectURL(f) : null;
}

function onFile(e) {
    const f = e.target.files?.[0];
    e.target.value = '';
    if (!f) return;
    if (!['image/jpeg', 'image/png', 'image/webp'].includes(f.type)) {
        error.value = 'Chỉ nhận ảnh JPG, PNG hoặc WebP.';
        return;
    }
    if (f.size > 2 * 1024 * 1024) {
        error.value = 'Ảnh tối đa 2 MB.';
        return;
    }
    error.value = '';
    preset.value = null;
    remove.value = false;
    file.value = f;
    setBlob(f);
}

function choosePreset(key) {
    error.value = '';
    setBlob(null);
    file.value = null;
    remove.value = false;
    preset.value = key;
}

function clear() {
    error.value = '';
    setBlob(null);
    file.value = null;
    preset.value = null;
    remove.value = true;
}

// Form cha đặt lại (mở hộp thoại mới) -> bỏ ảnh xem trước cũ
watch(file, (v) => {
    if (!v) setBlob(null);
});
onBeforeUnmount(() => setBlob(null));
</script>

<template>
    <div class="avatar-picker">
        <div class="avatar-current">
            <UserAvatar :name="name" :url="previewUrl" size="xlarge" />
            <div class="avatar-actions">
                <Button type="button" label="Tải ảnh lên" icon="pi pi-upload" size="small" severity="secondary" outlined @click="input.click()" />
                <Button v-if="previewUrl" type="button" label="Xóa ảnh" icon="pi pi-times" size="small" severity="danger" text @click="clear" />
            </div>
            <input ref="input" type="file" accept="image/png,image/jpeg,image/webp" hidden @change="onFile" />
        </div>

        <p class="field-hint" style="margin: 0">JPG, PNG hoặc WebP, tối đa 2 MB. Hoặc chọn ảnh có sẵn:</p>
        <div class="avatar-grid" role="group" aria-label="Ảnh đại diện có sẵn">
            <button
                v-for="p in presets"
                :key="p.key"
                type="button"
                class="avatar-opt"
                :class="{ 'is-on': isOn(p) }"
                :aria-pressed="isOn(p)"
                :aria-label="`Ảnh có sẵn ${p.key}`"
                @click="choosePreset(p.key)"
            >
                <img :src="p.url" alt="" />
            </button>
        </div>
        <small v-if="error" class="field-error" role="alert">{{ error }}</small>
    </div>
</template>
