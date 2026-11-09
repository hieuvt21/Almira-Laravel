<script setup>
import { computed, onBeforeUnmount, ref, watch } from 'vue';

const props = defineProps({
    file: { type: Object, default: null }, // File vừa chọn
    url: { type: String, default: null }, // ảnh đang lưu
    remove: { type: Boolean, default: false },
    size: { type: String, default: '3.5rem' },
    label: { type: String, default: 'Ảnh' },
    error: { type: String, default: null },
});
const emit = defineEmits(['update:file', 'update:remove']);

const input = ref(null);
const preview = ref(null);

watch(
    () => props.file,
    (f) => {
        if (preview.value) URL.revokeObjectURL(preview.value);
        preview.value = f ? URL.createObjectURL(f) : null;
    },
    { immediate: true },
);
onBeforeUnmount(() => preview.value && URL.revokeObjectURL(preview.value));

const shown = computed(() => preview.value ?? (props.remove ? null : props.url));

function pick(e) {
    const f = e.target.files?.[0];
    e.target.value = '';
    if (!f) return;
    emit('update:file', f);
    emit('update:remove', false);
}

function clear() {
    emit('update:file', null);
    emit('update:remove', true);
}
</script>

<template>
    <div class="img-in">
        <div class="img-in-box" :style="{ width: size, height: size }">
            <button type="button" class="img-in-pick" :aria-label="shown ? `Đổi ${label}` : `Chọn ${label}`" :title="shown ? 'Đổi ảnh' : 'Chọn ảnh'" @click="input.click()">
                <img v-if="shown" :src="shown" alt="" />
                <i v-else class="pi pi-image" aria-hidden="true" />
            </button>
            <button v-if="shown" type="button" class="img-in-clear" :aria-label="`Bỏ ${label}`" title="Bỏ ảnh" @click="clear">
                <i class="pi pi-times" aria-hidden="true" />
            </button>
            <input ref="input" type="file" accept="image/jpeg,image/png,image/webp" hidden @change="pick" />
        </div>
        <small v-if="error" class="field-error">{{ error }}</small>
    </div>
</template>

<style scoped>
.img-in { display: inline-flex; flex-direction: column; gap: 0.25rem; max-width: 100%; }
.img-in-box { position: relative; flex: none; }
.img-in-pick {
    width: 100%;
    height: 100%;
    padding: 0;
    display: grid;
    place-items: center;
    border: 1px dashed var(--al-line);
    border-radius: 0.6rem;
    overflow: hidden;
    background: var(--al-surface-2, transparent);
    color: var(--al-muted);
    cursor: pointer;
    font-size: 1.2rem;
}
.img-in-pick:hover { border-color: var(--al-primary); color: var(--al-primary); }
.img-in-pick:focus-visible { outline: 2px solid var(--al-primary); outline-offset: 2px; }
.img-in-pick img { width: 100%; height: 100%; object-fit: cover; display: block; }
.img-in-clear {
    position: absolute;
    top: -0.35rem;
    right: -0.35rem;
    width: 1.3rem;
    height: 1.3rem;
    display: grid;
    place-items: center;
    padding: 0;
    border: 0;
    border-radius: 999px;
    background: #dc2626;
    color: #fff;
    font-size: 0.6rem;
    cursor: pointer;
}
</style>
