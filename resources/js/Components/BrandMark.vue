<script setup>
import { computed } from 'vue';

const props = defineProps({
    logo: { type: Object, default: () => ({ type: 'icon', icon: 'letter', image_url: null }) },
    name: { type: String, default: 'Almira' },
    large: { type: Boolean, default: false },
});

const isImage = computed(() => props.logo?.type === 'image' && !!props.logo?.image_url);
const isIcon = computed(() => !isImage.value && props.logo?.icon && props.logo.icon !== 'letter');
const letter = computed(() => (props.name || 'A').trim().charAt(0).toUpperCase() || 'A');
</script>

<template>
    <span class="al-mark" :class="{ 'al-mark--image': isImage, 'al-mark--lg': large }" aria-hidden="true">
        <img v-if="isImage" :src="logo.image_url" alt="" />
        <i v-else-if="isIcon" :class="['pi', logo.icon]" />
        <span v-else>{{ letter }}</span>
    </span>
</template>
