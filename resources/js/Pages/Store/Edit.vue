<script setup>
import { computed, ref } from 'vue';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import Button from 'primevue/button';
import Column from 'primevue/column';
import DataTable from 'primevue/datatable';
import InputNumber from 'primevue/inputnumber';
import InputText from 'primevue/inputtext';
import Select from 'primevue/select';
import SelectButton from 'primevue/selectbutton';
import Tab from 'primevue/tab';
import TabList from 'primevue/tablist';
import TabPanel from 'primevue/tabpanel';
import TabPanels from 'primevue/tabpanels';
import Tabs from 'primevue/tabs';
import ToggleSwitch from 'primevue/toggleswitch';
import AppLayout from '@/Layouts/AppLayout.vue';
import { DEFAULT_FINANCE, formatMoney, roundMoney } from '@/utils/money';
import { DATE_FORMATS, SEPARATORS, composeNumber } from '@/utils/numbering';

const props = defineProps({
    store: { type: Object, required: true },
    numbering: { type: Object, required: true },
    types: { type: Array, default: () => [] },
    counters: { type: Object, default: () => ({}) },
});

const page = usePage();
const tab = ref('info');

// Mỗi tab lưu riêng. preserveState để không bị nhảy về tab đầu sau khi lưu.
const saveOptions = { preserveScroll: true, preserveState: true };

/* ============ Tab 1: Cửa hàng ============ */
const infoForm = useForm({ ...props.store });
const saveInfo = () => infoForm.put(route('store.info'), saveOptions);

/* ============ Tab 2: Tiền tệ & thuế ============ */
const saved = { ...DEFAULT_FINANCE, ...(page.props.finance ?? {}) };
const financeForm = useForm({ ...saved, tax_rates: [...saved.tax_rates] });

const symbolOptions = [
    { label: '₫  (ký hiệu đồng)', value: '₫' },
    { label: 'đ', value: 'đ' },
    { label: 'VND', value: 'VND' },
    { label: 'Không hiện ký hiệu', value: '' },
];
const positionOptions = [
    { label: 'Sau số', value: 'after' },
    { label: 'Trước số', value: 'before' },
];
const formatOptions = [
    { label: '1.234.567,5  (kiểu Việt Nam)', value: 'vi' },
    { label: '1,234,567.5  (kiểu quốc tế)', value: 'en' },
    { label: '1 234 567,5  (cách bằng dấu cách)', value: 'space' },
];
const decimalOptions = [
    { label: 'Không có', value: 0 },
    { label: '1 số', value: 1 },
    { label: '2 số', value: 2 },
];
const stepOptions = [
    { label: 'Không làm tròn', value: 1 },
    { label: 'Đến 10 đồng', value: 10 },
    { label: 'Đến 100 đồng', value: 100 },
    { label: 'Đến 500 đồng', value: 500 },
    { label: 'Đến 1.000 đồng', value: 1000 },
];
const modeOptions = [
    { label: 'Gần nhất', value: 'nearest' },
    { label: 'Luôn làm tròn lên', value: 'up' },
    { label: 'Luôn làm tròn xuống', value: 'down' },
];
const includeOptions = [
    { label: 'Giá bán đã gồm VAT', value: true },
    { label: 'Giá bán chưa gồm VAT', value: false },
];

const sampleAmount = 1234567;
const roundSample = 123456;
const moneyPreview = computed(() => formatMoney(sampleAmount + (financeForm.decimals ? 0.5 : 0), financeForm));
const roundBefore = computed(() => formatMoney(roundSample, financeForm));
const roundAfter = computed(() => formatMoney(roundMoney(roundSample, financeForm), financeForm));

const rateOptions = computed(() => financeForm.tax_rates.map((r) => ({ label: `${r}%`, value: r })));
const newRate = ref(null);
const rateError = ref('');

function addRate() {
    const v = newRate.value;
    if (v === null || v < 0 || v > 100) return;
    if (financeForm.tax_rates.includes(v)) {
        rateError.value = 'Mức thuế này đã có trong danh sách.';
        return;
    }
    if (financeForm.tax_rates.length >= 8) {
        rateError.value = 'Tối đa 8 mức thuế.';
        return;
    }
    rateError.value = '';
    financeForm.tax_rates = [...financeForm.tax_rates, v].sort((a, b) => a - b);
    newRate.value = null;
}

function removeRate(rate) {
    if (financeForm.tax_rates.length <= 1) return;
    financeForm.tax_rates = financeForm.tax_rates.filter((r) => r !== rate);
    if (financeForm.tax_default_rate === rate) financeForm.tax_default_rate = financeForm.tax_rates[0];
}

const taxError = computed(() => {
    const e = financeForm.errors;
    const nested = Object.keys(e).find((k) => k.startsWith('tax_rates'));
    return rateError.value || (nested && e[nested]) || e.tax_default_rate || '';
});

const saveFinance = () => financeForm.put(route('store.finance'), saveOptions);

/* ============ Tab 3: Đánh số chứng từ ============ */
const numberingForm = useForm({ numbering: JSON.parse(JSON.stringify(props.numbering)) });

const rows = computed(() => props.types.map((t) => ({ ...t, cfg: numberingForm.numbering[t.key] })));
const paddingOptions = [3, 4, 5, 6, 7, 8].map((n) => ({ label: `${n} chữ số`, value: n }));

const duplicatePrefixes = computed(() => {
    const count = {};
    rows.value.forEach((r) => (count[r.cfg.prefix] = (count[r.cfg.prefix] ?? 0) + 1));
    return new Set(Object.keys(count).filter((p) => p && count[p] > 1));
});
const rowError = (row) => {
    if (!row.cfg.prefix) return 'Nhập tiền tố.';
    if (duplicatePrefixes.value.has(row.cfg.prefix)) return 'Tiền tố này đã dùng cho loại khác.';
    return numberingForm.errors[`numbering.${row.key}.prefix`] ?? '';
};
const numberingInvalid = computed(() => rows.value.some((r) => !r.cfg.prefix) || duplicatePrefixes.value.size > 0);

const cleanPrefix = (row, v) => (row.cfg.prefix = v.toUpperCase().replace(/[^A-Z0-9]/g, ''));
const saveNumbering = () => numberingForm.put(route('store.numbering'), saveOptions);
</script>

<template>
    <Head title="Cửa hàng" />

    <AppLayout title="Cửa hàng">
        <div class="al-panel al-fill st-tabs">
            <Tabs v-model:value="tab">
                <TabList>
                    <Tab value="info">Cửa hàng</Tab>
                    <Tab value="finance">Tiền tệ &amp; thuế</Tab>
                    <Tab value="numbering">Đánh số chứng từ</Tab>
                </TabList>

                <TabPanels>
                    <!-- ============ Cửa hàng ============ -->
                    <TabPanel value="info">
                        <form class="st-form" novalidate @submit.prevent="saveInfo">
                            <p class="ap-note">
                                Tên và logo cửa hàng chỉnh ở trang
                                <Link :href="route('appearance.edit')">Giao diện</Link>. Thông tin dưới đây dùng để in lên hóa đơn.
                            </p>

                            <div class="field">
                                <label for="st-address">Địa chỉ</label>
                                <InputText id="st-address" v-model="infoForm.address" autocomplete="off" autofocus fluid :invalid="!!infoForm.errors.address" />
                                <small v-if="infoForm.errors.address" class="field-error">{{ infoForm.errors.address }}</small>
                            </div>

                            <div class="st-row">
                                <div class="field">
                                    <label for="st-phone">Điện thoại cửa hàng</label>
                                    <InputText id="st-phone" v-model="infoForm.phone" inputmode="tel" autocomplete="off" fluid :invalid="!!infoForm.errors.phone" />
                                    <small v-if="infoForm.errors.phone" class="field-error">{{ infoForm.errors.phone }}</small>
                                </div>
                                <div class="field">
                                    <label for="st-email">Email</label>
                                    <InputText id="st-email" v-model="infoForm.email" type="email" autocomplete="off" fluid :invalid="!!infoForm.errors.email" />
                                    <small v-if="infoForm.errors.email" class="field-error">{{ infoForm.errors.email }}</small>
                                </div>
                            </div>

                            <div class="st-row">
                                <div class="field">
                                    <label for="st-owner">Chủ cửa hàng / người đại diện</label>
                                    <InputText id="st-owner" v-model="infoForm.owner" autocomplete="off" fluid :invalid="!!infoForm.errors.owner" />
                                    <small v-if="infoForm.errors.owner" class="field-error">{{ infoForm.errors.owner }}</small>
                                </div>
                                <div class="field">
                                    <label for="st-tax">Mã số thuế</label>
                                    <InputText id="st-tax" v-model="infoForm.tax_code" inputmode="numeric" autocomplete="off" fluid :invalid="!!infoForm.errors.tax_code" />
                                    <small v-if="infoForm.errors.tax_code" class="field-error">{{ infoForm.errors.tax_code }}</small>
                                    <small v-else class="field-hint">10 hoặc 13 số. Bỏ trống nếu không có.</small>
                                </div>
                            </div>

                            <div class="dialog-actions">
                                <span v-if="infoForm.recentlySuccessful" class="pf-saved" role="status">Đã lưu.</span>
                                <Button type="submit" label="Lưu thông tin" icon="pi pi-check" :loading="infoForm.processing" />
                            </div>
                        </form>
                    </TabPanel>

                    <!-- ============ Tiền tệ & thuế ============ -->
                    <TabPanel value="finance">
                        <form class="st-form" novalidate @submit.prevent="saveFinance">
                            <h3 class="set-title">Hiển thị tiền</h3>
                            <div class="st-row">
                                <div class="field">
                                    <label for="fi-symbol">Ký hiệu tiền</label>
                                    <Select id="fi-symbol" v-model="financeForm.currency_symbol" :options="symbolOptions" optionLabel="label" optionValue="value" fluid />
                                </div>
                                <div class="field">
                                    <label for="fi-position">Vị trí ký hiệu</label>
                                    <SelectButton
                                        v-model="financeForm.symbol_position"
                                        :options="positionOptions"
                                        optionLabel="label"
                                        optionValue="value"
                                        :allowEmpty="false"
                                        :disabled="!financeForm.currency_symbol"
                                        aria-labelledby="fi-position"
                                    />
                                </div>
                            </div>
                            <div class="st-row">
                                <div class="field">
                                    <label for="fi-format">Dấu phân cách</label>
                                    <Select id="fi-format" v-model="financeForm.number_format" :options="formatOptions" optionLabel="label" optionValue="value" fluid />
                                </div>
                                <div class="field">
                                    <label id="fi-decimals">Số lẻ</label>
                                    <SelectButton
                                        v-model="financeForm.decimals"
                                        :options="decimalOptions"
                                        optionLabel="label"
                                        optionValue="value"
                                        :allowEmpty="false"
                                        aria-labelledby="fi-decimals"
                                    />
                                </div>
                            </div>
                            <p class="st-preview">Xem trước: <strong>{{ moneyPreview }}</strong></p>

                            <h3 class="set-title" style="margin-top: 1.5rem">Làm tròn số tiền thanh toán</h3>
                            <div class="st-row">
                                <div class="field">
                                    <label for="fi-step">Làm tròn</label>
                                    <Select id="fi-step" v-model="financeForm.rounding_step" :options="stepOptions" optionLabel="label" optionValue="value" fluid />
                                </div>
                                <div class="field">
                                    <label for="fi-mode">Kiểu làm tròn</label>
                                    <Select
                                        id="fi-mode"
                                        v-model="financeForm.rounding_mode"
                                        :options="modeOptions"
                                        optionLabel="label"
                                        optionValue="value"
                                        :disabled="financeForm.rounding_step === 1"
                                        fluid
                                    />
                                </div>
                            </div>
                            <p class="st-preview">
                                Ví dụ: tổng hóa đơn <strong>{{ roundBefore }}</strong> → khách thanh toán <strong>{{ roundAfter }}</strong>
                            </p>

                            <h3 class="set-title" style="margin-top: 1.5rem">Thuế VAT</h3>
                            <div class="st-switch-row">
                                <ToggleSwitch v-model="financeForm.tax_enabled" inputId="fi-tax" />
                                <label for="fi-tax">Tính và hiển thị VAT trên hóa đơn</label>
                            </div>
                            <p class="field-hint" style="margin: 0.4rem 0 0.9rem">
                                Nếu cửa hàng không xuất hóa đơn VAT thì để tắt. Mức thuế áp dụng thực tế bạn nên hỏi kế toán hoặc cơ quan thuế.
                            </p>

                            <template v-if="financeForm.tax_enabled">
                                <div class="field">
                                    <label id="fi-rates">Các mức thuế</label>
                                    <div class="rate-chips" role="list" aria-labelledby="fi-rates">
                                        <span v-for="r in financeForm.tax_rates" :key="r" class="rate-chip" role="listitem">
                                            {{ r }}%
                                            <button
                                                type="button"
                                                :aria-label="`Xóa mức ${r}%`"
                                                :disabled="financeForm.tax_rates.length <= 1"
                                                @click="removeRate(r)"
                                            >
                                                <i class="pi pi-times" aria-hidden="true" />
                                            </button>
                                        </span>
                                    </div>
                                    <div class="rate-add">
                                        <InputNumber
                                            v-model="newRate"
                                            :min="0"
                                            :max="100"
                                            :maxFractionDigits="2"
                                            suffix=" %"
                                            placeholder="Thêm mức"
                                            inputId="fi-newrate"
                                            aria-label="Mức thuế mới"
                                            @keydown.enter.prevent="addRate"
                                        />
                                        <Button type="button" label="Thêm" icon="pi pi-plus" severity="secondary" outlined :disabled="newRate === null" @click="addRate" />
                                    </div>
                                    <small v-if="taxError" class="field-error" role="alert">{{ taxError }}</small>
                                </div>

                                <div class="st-row">
                                    <div class="field">
                                        <label for="fi-default">Mức thuế mặc định</label>
                                        <Select id="fi-default" v-model="financeForm.tax_default_rate" :options="rateOptions" optionLabel="label" optionValue="value" fluid />
                                    </div>
                                    <div class="field">
                                        <label id="fi-include">Cách nhập giá</label>
                                        <SelectButton
                                            v-model="financeForm.prices_include_tax"
                                            :options="includeOptions"
                                            optionLabel="label"
                                            optionValue="value"
                                            :allowEmpty="false"
                                            aria-labelledby="fi-include"
                                        />
                                    </div>
                                </div>
                            </template>

                            <div class="dialog-actions">
                                <span v-if="financeForm.recentlySuccessful" class="pf-saved" role="status">Đã lưu.</span>
                                <Button type="submit" label="Lưu cài đặt" icon="pi pi-check" :loading="financeForm.processing" />
                            </div>
                        </form>
                    </TabPanel>

                    <!-- ============ Đánh số chứng từ ============ -->
                    <TabPanel value="numbering">
                        <form novalidate @submit.prevent="saveNumbering">
                            <p class="ap-note">
                                Mã được ghép từ <strong>tiền tố</strong>, <strong>ngày tháng</strong> và <strong>số thứ tự</strong>. Số thứ tự tự quay về 1
                                khi sang ngày, tháng hoặc năm mới (tùy mẫu ngày tháng bạn chọn); chọn "Không" thì đếm liên tục.
                                Đổi mẫu ngày tháng sẽ đếm lại từ 1. Mã đã cấp không bị thay đổi.
                            </p>

                            <div class="st-scroll">
                                <DataTable :value="rows" dataKey="key" size="small" class="st-numbering">
                                    <Column header="Loại" style="min-width: 10rem">
                                        <template #body="{ data }">{{ data.label }}</template>
                                    </Column>
                                    <Column header="Tiền tố" style="min-width: 8rem">
                                        <template #body="{ data }">
                                            <InputText
                                                :modelValue="data.cfg.prefix"
                                                maxlength="10"
                                                autocomplete="off"
                                                :aria-label="`Tiền tố ${data.label}`"
                                                :invalid="!!rowError(data)"
                                                style="width: 7rem"
                                                @update:modelValue="(v) => cleanPrefix(data, v)"
                                            />
                                            <small v-if="rowError(data)" class="field-error" style="display: block">{{ rowError(data) }}</small>
                                        </template>
                                    </Column>
                                    <Column header="Ngày tháng" style="min-width: 10rem">
                                        <template #body="{ data }">
                                            <Select v-model="data.cfg.date_format" :options="DATE_FORMATS" optionLabel="label" optionValue="value" :aria-label="`Ngày tháng ${data.label}`" fluid />
                                        </template>
                                    </Column>
                                    <Column header="Dấu ngăn" style="min-width: 10rem">
                                        <template #body="{ data }">
                                            <Select v-model="data.cfg.separator" :options="SEPARATORS" optionLabel="label" optionValue="value" :aria-label="`Dấu ngăn ${data.label}`" fluid />
                                        </template>
                                    </Column>
                                    <Column header="Số thứ tự" style="min-width: 9rem">
                                        <template #body="{ data }">
                                            <Select v-model="data.cfg.padding" :options="paddingOptions" optionLabel="label" optionValue="value" :aria-label="`Độ dài ${data.label}`" fluid />
                                        </template>
                                    </Column>
                                    <Column header="Ví dụ" style="min-width: 11rem">
                                        <template #body="{ data }">
                                            <code class="st-code">{{ composeNumber(data.cfg, 1) }}</code>
                                        </template>
                                    </Column>
                                    <Column header="Đã cấp trong kỳ" style="min-width: 8rem">
                                        <template #body="{ data }">{{ counters[data.key] ?? 0 }}</template>
                                    </Column>
                                </DataTable>
                            </div>

                            <div class="dialog-actions">
                                <span v-if="numberingForm.recentlySuccessful" class="pf-saved" role="status">Đã lưu.</span>
                                <Button type="submit" label="Lưu cách đánh số" icon="pi pi-check" :loading="numberingForm.processing" :disabled="numberingInvalid" />
                            </div>
                        </form>
                    </TabPanel>
                </TabPanels>
            </Tabs>
        </div>
    </AppLayout>
</template>
