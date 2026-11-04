<script setup>
import { computed } from 'vue';

const props = defineProps({
    status: { type: String, default: 'idle' },
    message: { type: String, default: '' },
});

const ICONS = {
    checking: 'pi pi-spin pi-spinner',
    ok: 'pi pi-check-circle',
    taken: 'pi pi-times-circle',
    invalid: 'pi pi-exclamation-circle',
};
const icon = computed(() => ICONS[props.status]);
</script>

<template>
    <small v-if="icon && message" class="field-status" :class="`is-${status}`" role="status" aria-live="polite">
        <i :class="icon" aria-hidden="true" />
        <span>{{ message }}</span>
    </small>
</template>
