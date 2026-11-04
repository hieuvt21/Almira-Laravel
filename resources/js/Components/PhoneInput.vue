<script setup>
import { computed } from 'vue';
import InputText from 'primevue/inputtext';
import { formatPhone } from '@/utils/phone';

// v-model là chuỗi 10 số (không dấu chấm); ô nhập hiển thị dạng xxxx.xxx.xxx
const model = defineModel({ type: String, default: '' });

const display = computed(() => formatPhone(model.value));

function onInput(e) {
    const digits = e.target.value.replace(/\D/g, '').slice(0, 10);
    model.value = digits;
    e.target.value = formatPhone(digits);
}
</script>

<template>
    <InputText
        :modelValue="display"
        inputmode="numeric"
        autocomplete="off"
        placeholder="0xxx.xxx.xxx"
        maxlength="12"
        fluid
        @input="onInput"
    />
</template>
