<script setup>
import { computed } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import Button from 'primevue/button';
import InputNumber from 'primevue/inputnumber';
import InputText from 'primevue/inputtext';
import ToggleSwitch from 'primevue/toggleswitch';
import TierBadge from '@/Components/TierBadge.vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import { useMoney } from '@/composables/useMoney';

const props = defineProps({
    config: { type: Object, required: true },
});

const { money } = useMoney();
const MAX_TIERS = 8;

const form = useForm({
    enabled: props.config.enabled,
    tiers: props.config.tiers.map(({ name, min_spent, discount }) => ({ name, min_spent, discount })),
});

function addTier() {
    if (form.tiers.length >= MAX_TIERS) return;
    const last = form.tiers[form.tiers.length - 1];
    form.tiers = [
        ...form.tiers,
        { name: `Hạng ${form.tiers.length + 1}`, min_spent: (last?.min_spent ?? 0) + 5000000, discount: last?.discount ?? 0 },
    ];
}

function removeTier(index) {
    if (index === 0 || form.tiers.length <= 1) return;
    form.tiers = form.tiers.filter((_, i) => i !== index);
}

// Kiểm tra ngay trên trình duyệt (khớp với quy tắc ở server)
const rowErrors = computed(() => {
    const seen = new Set();
    return form.tiers.map((t, i) => {
        const name = (t.name ?? '').trim();
        const key = name.toLowerCase();
        const problems = {};
        if (!name) problems.name = 'Nhập tên hạng.';
        else if (seen.has(key)) problems.name = 'Tên hạng bị trùng.';
        seen.add(key);

        if (t.min_spent === null || t.min_spent === undefined) problems.min_spent = 'Nhập mức chi tiêu.';
        else if (i > 0 && t.min_spent <= (form.tiers[i - 1].min_spent ?? 0)) problems.min_spent = 'Phải lớn hơn hạng ngay trên.';

        if (t.discount === null || t.discount === undefined) problems.discount = 'Nhập % giảm.';
        return problems;
    });
});

const serverError = (i, field) => form.errors[`tiers.${i}.${field}`];
const rowError = (i, field) => serverError(i, field) || rowErrors.value[i]?.[field] || '';
const invalid = computed(() => rowErrors.value.some((e) => Object.keys(e).length > 0));

const rangeText = (i) => {
    const from = form.tiers[i].min_spent ?? 0;
    const next = form.tiers[i + 1];
    return next ? `${money(from)} – dưới ${money(next.min_spent ?? 0)}` : `Từ ${money(from)} trở lên`;
};

function save() {
    if (invalid.value) return;
    form.put(route('customer-tiers.update'), { preserveScroll: true });
}
</script>

<template>
    <Head title="Hạng khách hàng" />

    <AppLayout title="Hạng khách hàng">
        <section class="al-panel">
            <div class="al-toolbar">
                <p class="al-toolbar-note">
                    Hạng được tính tự động từ <strong>tổng chi tiêu</strong> của khách. Module Bán hàng sẽ dùng % giảm giá theo hạng.
                </p>
            </div>

            <form class="ap-body tr-form" novalidate @submit.prevent="save">
                <div class="st-switch-row">
                    <ToggleSwitch v-model="form.enabled" inputId="tr-enabled" />
                    <label for="tr-enabled">Bật phân hạng khách hàng</label>
                </div>
                <p class="field-hint" style="margin: 0.4rem 0 1rem">
                    Tắt thì ẩn hạng trên danh sách khách và không áp dụng giảm giá theo hạng.
                </p>

                <fieldset class="ap-fieldset" :disabled="!form.enabled">
                    <div class="tr-head" aria-hidden="true">
                        <span>Hạng</span>
                        <span>Tên hạng</span>
                        <span>Từ mức chi tiêu</span>
                        <span>Giảm giá</span>
                        <span />
                    </div>

                    <div v-for="(t, i) in form.tiers" :key="i" class="tr-row">
                        <div class="tr-cell tr-badge">
                            <TierBadge :tier="{ level: i, name: t.name || '...' }" />
                            <small class="field-hint">{{ rangeText(i) }}</small>
                        </div>

                        <div class="tr-cell">
                            <label :for="`tr-name-${i}`" class="tr-label">Tên hạng</label>
                            <InputText :id="`tr-name-${i}`" v-model="t.name" maxlength="30" autocomplete="off" fluid :invalid="!!rowError(i, 'name')" />
                            <small v-if="rowError(i, 'name')" class="field-error">{{ rowError(i, 'name') }}</small>
                        </div>

                        <div class="tr-cell">
                            <label :for="`tr-min-${i}`" class="tr-label">Từ mức chi tiêu</label>
                            <InputNumber
                                v-model="t.min_spent"
                                :inputId="`tr-min-${i}`"
                                locale="vi-VN"
                                :min="0"
                                :maxFractionDigits="0"
                                :disabled="i === 0"
                                fluid
                                :invalid="!!rowError(i, 'min_spent')"
                            />
                            <small v-if="rowError(i, 'min_spent')" class="field-error">{{ rowError(i, 'min_spent') }}</small>
                            <small v-else-if="i === 0" class="field-hint">Hạng đầu luôn từ 0.</small>
                            <small v-else class="field-hint">{{ money(t.min_spent ?? 0) }}</small>
                        </div>

                        <div class="tr-cell">
                            <label :for="`tr-dis-${i}`" class="tr-label">Giảm giá</label>
                            <InputNumber
                                v-model="t.discount"
                                :inputId="`tr-dis-${i}`"
                                suffix=" %"
                                :min="0"
                                :max="100"
                                :maxFractionDigits="1"
                                fluid
                                :invalid="!!rowError(i, 'discount') || !!serverError(i, 'discount')"
                            />
                            <small v-if="rowError(i, 'discount') || serverError(i, 'discount')" class="field-error">
                                {{ rowError(i, 'discount') || serverError(i, 'discount') }}
                            </small>
                        </div>

                        <div class="tr-cell tr-del">
                            <Button
                                v-if="i > 0"
                                type="button"
                                icon="pi pi-trash"
                                text
                                rounded
                                severity="danger"
                                :disabled="form.tiers.length <= 1"
                                :aria-label="`Xóa hạng ${t.name}`"
                                @click="removeTier(i)"
                            />
                        </div>
                    </div>

                    <small v-if="form.errors.tiers" class="field-error" role="alert">{{ form.errors.tiers }}</small>

                    <div style="margin-top: 0.75rem">
                        <Button
                            type="button"
                            label="Thêm hạng"
                            icon="pi pi-plus"
                            severity="secondary"
                            outlined
                            size="small"
                            :disabled="form.tiers.length >= MAX_TIERS"
                            @click="addTier"
                        />
                        <small class="field-hint" style="margin-left: 0.6rem">Tối đa {{ MAX_TIERS }} hạng, mức chi tiêu tăng dần từ trên xuống.</small>
                    </div>
                </fieldset>

                <div class="dialog-actions">
                    <span v-if="form.recentlySuccessful" class="pf-saved" role="status">Đã lưu.</span>
                    <Button type="submit" label="Lưu cài đặt" icon="pi pi-check" :loading="form.processing" :disabled="invalid" />
                </div>
            </form>
        </section>
    </AppLayout>
</template>

<style>
.tr-form { max-width: 62rem; }
.tr-head, .tr-row { display: grid; grid-template-columns: 11rem 1.3fr 1.3fr 0.8fr 3rem; gap: 0.75rem; align-items: start; }
.tr-head { padding: 0 0 0.4rem; color: var(--al-muted); font-size: 0.8rem; font-weight: 600; }
.tr-row { padding: 0.7rem 0; border-top: 1px solid var(--al-line); }
.tr-cell { display: flex; flex-direction: column; gap: 0.3rem; min-width: 0; }
.tr-badge { align-items: flex-start; padding-top: 0.45rem; }
.tr-del { align-items: flex-end; }
.tr-label { display: none; font-size: 0.85rem; font-weight: 500; }
@media (max-width: 767px) {
    .tr-head { display: none; }
    .tr-row { grid-template-columns: 1fr 1fr; }
    .tr-badge, .tr-del { grid-column: 1 / -1; }
    .tr-del { align-items: flex-start; }
    .tr-label { display: block; }
}
</style>
