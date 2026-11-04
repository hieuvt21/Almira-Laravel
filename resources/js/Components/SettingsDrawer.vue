<script setup>
import Drawer from 'primevue/drawer';
import SelectButton from 'primevue/selectbutton';
import { useUiSettings } from '@/composables/useUiSettings';

defineProps({ visible: { type: Boolean, default: false } });
defineEmits(['update:visible']);

const { settings, backgrounds } = useUiSettings();

const modes = [
    { label: 'Cố định', value: 'pinned' },
    { label: 'Tự động', value: 'hover' },
];
</script>

<template>
    <Drawer
        :visible="visible"
        position="right"
        header="Giao diện"
        :style="{ width: '20rem' }"
        @update:visible="$emit('update:visible', $event)"
    >
        <section class="set-block">
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
        </section>

        <section class="set-block">
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
        </section>
    </Drawer>
</template>
