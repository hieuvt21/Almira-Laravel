<script setup>
import { computed } from 'vue';

// Màu dự phòng theo thứ tự hạng (dùng khi hạng chưa có màu riêng)
const PALETTE = ['#64748b', '#0284c7', '#0d9488', '#a16207', '#ea580c', '#7c3aed', '#db2777', '#334155'];

const props = defineProps({
    tier: { type: Object, default: null }, // { level, name, discount, color?, icon? }
    showDiscount: { type: Boolean, default: false },
});

const color = computed(() => props.tier?.color || PALETTE[(props.tier?.level ?? 0) % PALETTE.length]);
const icon = computed(() => props.tier?.icon || 'pi-star-fill');
</script>

<template>
    <span v-if="tier" class="tier-badge" :style="{ '--tier': color }">
        <i :class="['pi', icon]" aria-hidden="true" />
        <span>{{ tier.name }}</span>
        <small v-if="showDiscount && tier.discount > 0">· Giảm {{ tier.discount }}%</small>
    </span>
</template>

<style>
.tier-badge { display: inline-flex; align-items: center; gap: 0.3rem; padding: 0.12rem 0.6rem; border-radius: 999px; background: color-mix(in srgb, var(--tier) 14%, transparent); color: var(--tier); font-size: 0.76rem; font-weight: 600; white-space: nowrap; }
.tier-badge i { font-size: 0.7rem; }
.tier-badge small { font-size: 0.72rem; font-weight: 500; }
</style>
