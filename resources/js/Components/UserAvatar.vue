<script setup>
import { computed } from 'vue';
import Avatar from 'primevue/avatar';

const props = defineProps({
    name: { type: String, default: '' },
    url: { type: String, default: null },
    size: { type: String, default: 'normal' },
});

const initials = computed(
    () =>
        (props.name || '?')
            .split(' ')
            .filter(Boolean)
            .slice(-2)
            .map((w) => w[0])
            .join('')
            .toUpperCase(),
);
</script>

<template>
    <Avatar v-if="url" :image="url" shape="circle" :size="size" class="al-avatar-img" :aria-label="name" />
    <Avatar v-else :label="initials" shape="circle" :size="size" class="al-avatar" :aria-label="name" />
</template>
