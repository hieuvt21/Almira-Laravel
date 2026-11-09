<script setup>
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import Button from 'primevue/button';
import Checkbox from 'primevue/checkbox';
import Column from 'primevue/column';
import DataTable from 'primevue/datatable';
import IconField from 'primevue/iconfield';
import InputIcon from 'primevue/inputicon';
import InputNumber from 'primevue/inputnumber';
import InputText from 'primevue/inputtext';
import Select from 'primevue/select';
import Tag from 'primevue/tag';
import Textarea from 'primevue/textarea';
import { useConfirm } from 'primevue/useconfirm';
import AppDialog from '@/Components/AppDialog.vue';
import FieldStatus from '@/Components/FieldStatus.vue';
import ImageInput from '@/Components/ImageInput.vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import { usePermission } from '@/composables/usePermission';
import { useMoney } from '@/composables/useMoney';
import { useUniqueCheck } from '@/composables/useUniqueCheck';

const props = defineProps({
    products: { type: Object, required: true },
    filters: { type: Object, default: () => ({ search: '', category: null, status: null }) },
    categories: { type: Array, default: () => [] },
    units: { type: Array, default: () => [] },
    priceLists: { type: Array, default: () => [] },
});

const { can } = usePermission();
const { money, config } = useMoney();
const confirm = useConfirm();

/* ---------- Tìm kiếm, lọc, phân trang (xử lý ở server) ---------- */
const ALL = 'all'; // PrimeVue Select không hiện lựa chọn có giá trị null
const search = ref(props.filters.search ?? '');
const categoryFilter = ref(props.filters.category ?? ALL);
const statusFilter = ref(props.filters.status ?? ALL);

const categoryFilterOptions = computed(() => [
    { value: ALL, label: 'Tất cả danh mục' },
    { value: 'none', label: 'Chưa phân loại' },
    ...props.categories.map((c) => ({ value: String(c.id), label: c.name })),
]);
const statusOptions = [
    { value: ALL, label: 'Mọi trạng thái' },
    { value: 'active', label: 'Đang bán' },
    { value: 'inactive', label: 'Ngừng bán' },
];

const visit = (extra = {}) =>
    router.get(
        route('products.index'),
        {
            search: search.value || undefined,
            category: categoryFilter.value !== ALL ? categoryFilter.value : undefined,
            status: statusFilter.value !== ALL ? statusFilter.value : undefined,
            ...extra,
        },
        { preserveState: true, preserveScroll: true, replace: true },
    );

let searchTimer = null;
watch(search, () => {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(() => visit(), 300);
});
watch([categoryFilter, statusFilter], () => visit());
onBeforeUnmount(() => clearTimeout(searchTimer));

const onPage = (e) => visit({ page: e.page + 1 });
const first = computed(() => (props.products.current_page - 1) * props.products.per_page);

/* ---------- Form thêm / sửa ---------- */
const dialog = ref(false);
const editing = ref(null);
const MAX_CONVERSIONS = 5;

const form = useForm({
    code: '',
    name: '',
    barcode: '',
    category_id: null,
    tax_rate: 0,
    description: '',
    is_active: true,
    image_file: null,
    remove_image: false,
    base: { unit_id: null, prices: {} }, // đơn vị cơ bản: không có ảnh riêng
    cost_price: null,
    track_stock: false,
    opening_stock: null,
    conversions: [], // đơn vị quy đổi
});

const taxOn = computed(() => !!config.value.tax_enabled);
const taxOptions = computed(() => (config.value.tax_rates ?? []).map((r) => ({ label: `${r}%`, value: Number(r) })));
const activeLists = computed(() => props.priceLists.filter((l) => l.is_active));
const defaultList = computed(() => props.priceLists.find((l) => l.is_default));
const categoryOptions = computed(() => props.categories.filter((c) => c.is_active || c.id === form.category_id));
const fractionDigits = computed(() => Number(config.value.decimals) || 0);
const err = (key) => form.errors[key];

// Đơn vị cơ bản và đơn vị quy đổi là hai danh sách riêng (loại đặt trong Danh mục & đơn vị)
const baseUnits = computed(() => props.units.filter((u) => u.kind === 'base'));
const conversionUnits = computed(() => props.units.filter((u) => u.kind === 'conversion'));
const defaultBaseId = computed(() => baseUnits.value.find((u) => u.is_default && u.is_active)?.id ?? null);
const defaultConversionId = computed(() => conversionUnits.value.find((u) => u.is_default && u.is_active)?.id ?? null);

const baseOptions = computed(() => baseUnits.value.filter((u) => u.is_active || u.id === form.base.unit_id));
const baseName = computed(() => props.units.find((u) => u.id === form.base.unit_id)?.name ?? 'đơn vị cơ bản');

function conversionOptions(index) {
    const used = form.conversions.filter((_, i) => i !== index).map((c) => c.unit_id);
    return conversionUnits.value.filter((u) => !used.includes(u.id) && (u.is_active || u.id === form.conversions[index].unit_id));
}

// Kiểm tra trùng mã / mã vạch ngay khi nhập (khi sửa: bỏ qua chính sản phẩm đang sửa)
const checkOptions = (key) => ({
    ignoreId: () => editing.value?.id ?? null,
    original: () => editing.value?.[key] ?? null,
    url: () => route('products.check'),
});
const codeCheck = useUniqueCheck('code', () => form.code, checkOptions('code'));
const barcodeCheck = useUniqueCheck('barcode', () => form.barcode, checkOptions('barcode'));
const hasConflict = computed(() => [codeCheck, barcodeCheck].some((c) => ['taken', 'invalid'].includes(c.status)));

// Xóa lỗi cũ của ô vừa sửa
['code', 'name', 'barcode', 'tax_rate', 'category_id'].forEach((f) => watch(() => form[f], () => form.clearErrors(f)));

const emptyPrices = () => Object.fromEntries(activeLists.value.map((l) => [l.id, null]));

function blankConversion() {
    const used = form.conversions.map((c) => c.unit_id);
    const preset = defaultConversionId.value && !used.includes(defaultConversionId.value) ? defaultConversionId.value : null;
    return { unit_id: preset, factor: null, prices: emptyPrices(), image_file: null, remove_image: false, image_url: null };
}

const addConversion = () => form.conversions.length < MAX_CONVERSIONS && form.conversions.push(blankConversion());
const removeConversion = (i) => form.conversions.splice(i, 1);

function resetChecks() {
    codeCheck.reset();
    barcodeCheck.reset();
}

function openCreate() {
    editing.value = null;
    resetChecks();
    form.reset();
    form.clearErrors();
    form.tax_rate = taxOn.value ? Number(config.value.tax_default_rate) : 0;
    form.base = { unit_id: defaultBaseId.value, prices: emptyPrices() };
    form.conversions = [];
    dialog.value = true;
}

function openEdit(p) {
    editing.value = p;
    resetChecks();
    form.clearErrors();
    form.code = p.code;
    form.name = p.name;
    form.barcode = p.barcode ?? '';
    form.category_id = p.category_id;
    form.tax_rate = Number(p.tax_rate);
    form.description = p.description ?? '';
    form.is_active = p.is_active;
    form.image_file = null;
    form.remove_image = false;
    form.cost_price = Number(p.cost_price) || null;
    form.track_stock = p.track_stock;
    form.opening_stock = p.track_stock ? Number(p.opening_stock) : null;

    const [base, ...rest] = p.units; // đã sắp theo số lượng quy đổi: đơn vị cơ bản đứng đầu
    const toPrices = (u) => {
        const prices = emptyPrices();
        u.prices.forEach((pr) => (prices[pr.price_list_id] = pr.price));
        return prices;
    };
    form.base = { unit_id: base?.unit_id ?? null, prices: base ? toPrices(base) : emptyPrices() };
    form.conversions = rest.map((u) => ({
        unit_id: u.unit_id,
        factor: u.factor,
        prices: toPrices(u),
        image_file: null,
        remove_image: false,
        image_url: u.image_url,
    }));
    dialog.value = true;
}

// Chỉ gửi trường ảnh khi có thay đổi (tránh gửi chuỗi rỗng)
const withImage = (row, out) => {
    if (row.image_file) out.image_file = row.image_file;
    if (row.remove_image) out.remove_image = true;
    return out;
};

const priceList = (prices) => activeLists.value.map((l) => ({ price_list_id: l.id, price: prices[l.id] }));

function submit() {
    if (hasConflict.value) return;

    form.transform((data) => {
        const payload = withImage(data, {
            code: data.code,
            name: data.name,
            barcode: data.barcode,
            category_id: data.category_id,
            tax_rate: data.tax_rate,
            description: data.description,
            is_active: data.is_active,
            cost_price: data.cost_price ?? 0,
            track_stock: data.track_stock,
            opening_stock: data.track_stock ? (data.opening_stock ?? 0) : 0,
            // Máy chủ nhận một danh sách đơn vị: đơn vị cơ bản (quy đổi = 1) đứng đầu
            units: [
                { unit_id: data.base.unit_id, factor: 1, prices: priceList(data.base.prices) },
                ...data.conversions.map((c) =>
                    withImage(c, { unit_id: c.unit_id, factor: c.factor, prices: priceList(c.prices) }),
                ),
            ],
        });
        return editing.value ? { ...payload, _method: 'put' } : payload;
    });

    const options = {
        preserveScroll: true,
        forceFormData: true,
        onSuccess: () => {
            dialog.value = false;
            form.reset();
        },
    };

    if (editing.value) {
        form.post(route('products.update', editing.value.id), options);
    } else {
        form.post(route('products.store'), options);
    }
}

function remove(p) {
    confirm.require({
        header: 'Xóa sản phẩm',
        message: `Xóa sản phẩm "${p.name}"? Thao tác này không thể hoàn tác.`,
        icon: 'pi pi-exclamation-triangle',
        rejectLabel: 'Hủy',
        acceptLabel: 'Xóa',
        rejectProps: { severity: 'secondary', outlined: true },
        acceptProps: { severity: 'danger' },
        accept: () => router.delete(route('products.destroy', p.id), { preserveScroll: true }),
    });
}

/* ---------- Hiển thị bảng ---------- */
// Giá ở bảng giá mặc định của đơn vị cơ bản
function basePrice(p) {
    const base = p.units[0];
    if (!base || !defaultList.value) return null;
    return base.prices.find((x) => x.price_list_id === defaultList.value.id)?.price ?? 0;
}

const unitSummary = (p) => p.units.map((u) => (u.factor === 1 ? u.unit : `${u.unit} (${u.factor})`)).join(', ');
const subline = (p) => [p.code, p.category, unitSummary(p)].filter(Boolean).join(' · ');
</script>

<template>
    <Head title="Sản phẩm" />

    <AppLayout title="Sản phẩm">
        <div class="al-panel al-fill">
            <div class="al-toolbar">
                <IconField class="al-search">
                    <InputIcon class="pi pi-search" />
                    <InputText v-model="search" placeholder="Tìm theo tên, mã hoặc mã vạch" aria-label="Tìm sản phẩm" fluid />
                </IconField>
                <Select
                    v-model="categoryFilter"
                    :options="categoryFilterOptions"
                    optionLabel="label"
                    optionValue="value"
                    aria-label="Lọc theo danh mục"
                    class="pr-filter"
                />
                <Select
                    v-model="statusFilter"
                    :options="statusOptions"
                    optionLabel="label"
                    optionValue="value"
                    aria-label="Lọc theo trạng thái"
                    class="pr-filter"
                />
                <Button v-if="can('products.create')" label="Thêm sản phẩm" icon="pi pi-plus" @click="openCreate" />
            </div>

            <DataTable
                :value="products.data"
                dataKey="id"
                lazy
                paginator
                :rows="products.per_page"
                :first="first"
                :totalRecords="products.total"
                @page="onPage"
            >
                <template #empty>Chưa có sản phẩm nào khớp với tìm kiếm.</template>

                <Column field="name" header="Sản phẩm">
                    <template #body="{ data }">
                        <div class="pr-name">
                            <img v-if="data.image_url" :src="data.image_url" alt="" class="pr-thumb" />
                            <span v-else class="pr-thumb pr-thumb--empty pi pi-image" aria-hidden="true" />
                            <div class="pr-name-text">
                                <span>{{ data.name }}</span>
                                <small class="pr-sub">{{ subline(data) }}</small>
                            </div>
                        </div>
                    </template>
                </Column>
                <Column field="code" header="Mã" headerClass="pr-hide-md" bodyClass="pr-hide-md" />
                <Column field="barcode" header="Mã vạch" headerClass="pr-hide-md" bodyClass="pr-hide-md">
                    <template #body="{ data }">{{ data.barcode || '—' }}</template>
                </Column>
                <Column field="category" header="Danh mục" headerClass="pr-hide-md" bodyClass="pr-hide-md">
                    <template #body="{ data }">{{ data.category || '—' }}</template>
                </Column>
                <Column header="Đơn vị" headerClass="pr-hide-md" bodyClass="pr-hide-md">
                    <template #body="{ data }">{{ unitSummary(data) }}</template>
                </Column>
                <Column header="Giá bán" headerClass="pr-col-right" bodyClass="pr-col-right">
                    <template #body="{ data }">
                        <span v-if="basePrice(data) !== null">{{ money(basePrice(data)) }}</span>
                        <span v-else>—</span>
                    </template>
                </Column>
                <Column header="Trạng thái" headerClass="pr-hide-sm" bodyClass="pr-hide-sm" style="width: 8rem">
                    <template #body="{ data }">
                        <Tag :value="data.is_active ? 'Đang bán' : 'Ngừng bán'" :severity="data.is_active ? 'success' : 'secondary'" />
                    </template>
                </Column>
                <Column header="" style="width: 7rem">
                    <template #body="{ data }">
                        <div class="pr-actions">
                            <Button v-if="can('products.update')" icon="pi pi-pencil" text rounded severity="secondary" aria-label="Sửa" @click="openEdit(data)" />
                            <Button v-if="can('products.delete')" icon="pi pi-trash" text rounded severity="danger" aria-label="Xóa" @click="remove(data)" />
                        </div>
                    </template>
                </Column>
            </DataTable>
        </div>

        <AppDialog
            v-model:visible="dialog"
            :header="editing ? 'Sửa sản phẩm' : 'Thêm sản phẩm'"
            :style="{ width: '46rem' }"
            :breakpoints="{ '900px': '96vw' }"
        >
            <form @submit.prevent="submit" novalidate>
                <!-- Thông tin chung -->
                <div class="pr-head">
                    <ImageInput
                        v-model:file="form.image_file"
                        v-model:remove="form.remove_image"
                        :url="editing?.image_url"
                        size="5rem"
                        label="ảnh sản phẩm"
                        :error="err('image_file')"
                    />
                    <div class="field pr-head-name">
                        <label for="p-name">Tên sản phẩm<span class="al-req" aria-hidden="true">*</span></label>
                        <InputText id="p-name" v-model="form.name" autocomplete="off" aria-required="true" fluid :invalid="!!err('name')" />
                        <small v-if="err('name')" class="field-error">{{ err('name') }}</small>
                    </div>
                </div>

                <div class="pr-grid">
                    <div class="field">
                        <label for="p-category">Danh mục<span class="al-req" aria-hidden="true">*</span></label>
                        <Select
                            v-model="form.category_id"
                            inputId="p-category"
                            :options="categoryOptions"
                            optionLabel="name"
                            optionValue="id"
                            placeholder="Chọn danh mục"
                            aria-required="true"
                            fluid
                            :invalid="!!err('category_id')"
                        />
                        <small v-if="err('category_id')" class="field-error">{{ err('category_id') }}</small>
                    </div>
                    <div v-if="taxOn" class="field">
                        <label for="p-tax">Thuế</label>
                        <Select v-model="form.tax_rate" inputId="p-tax" :options="taxOptions" optionLabel="label" optionValue="value" fluid :invalid="!!err('tax_rate')" />
                        <small v-if="err('tax_rate')" class="field-error">{{ err('tax_rate') }}</small>
                    </div>
                    <div class="field">
                        <label for="p-code">Mã sản phẩm</label>
                        <InputText
                            id="p-code"
                            v-model="form.code"
                            autocomplete="off"
                            autocapitalize="characters"
                            spellcheck="false"
                            fluid
                            :invalid="!!err('code') || ['taken', 'invalid'].includes(codeCheck.status)"
                            @blur="codeCheck.check()"
                        />
                        <small v-if="err('code')" class="field-error">{{ err('code') }}</small>
                        <FieldStatus v-else :status="codeCheck.status" :message="codeCheck.message" />
                        <small v-if="!err('code') && codeCheck.status === 'idle' && !editing" class="field-hint">Bỏ trống để tự động đánh số.</small>
                    </div>
                    <div class="field">
                        <label for="p-barcode">Mã vạch</label>
                        <InputText
                            id="p-barcode"
                            v-model="form.barcode"
                            autocomplete="off"
                            spellcheck="false"
                            fluid
                            :invalid="!!err('barcode') || ['taken', 'invalid'].includes(barcodeCheck.status)"
                            @blur="barcodeCheck.check()"
                        />
                        <small v-if="err('barcode')" class="field-error">{{ err('barcode') }}</small>
                        <FieldStatus v-else :status="barcodeCheck.status" :message="barcodeCheck.message" />
                    </div>
                </div>

                <!-- Đơn vị cơ bản, giá bán, giá vốn -->
                <section class="pr-section" aria-labelledby="p-base-title">
                    <h3 id="p-base-title" class="pr-section-title">Đơn vị cơ bản và giá</h3>

                    <div class="field">
                        <label for="p-base-unit">Đơn vị cơ bản<span class="al-req" aria-hidden="true">*</span></label>
                        <Select
                            v-model="form.base.unit_id"
                            inputId="p-base-unit"
                            :options="baseOptions"
                            optionLabel="name"
                            optionValue="id"
                            placeholder="Chọn đơn vị cơ bản"
                            aria-required="true"
                            fluid
                            :invalid="!!err('units.0.unit_id')"
                        />
                        <small v-if="err('units.0.unit_id')" class="field-error">{{ err('units.0.unit_id') }}</small>
                        <small v-else-if="!baseUnits.length" class="field-hint">Chưa có đơn vị cơ bản, hãy thêm trong Danh mục &amp; đơn vị.</small>
                    </div>

                    <div class="pr-prices">
                        <div v-for="l in activeLists" :key="l.id" class="field pr-price">
                            <label :for="`p-base-price-${l.id}`">
                                {{ l.name }}<span v-if="l.is_default" class="al-req" aria-hidden="true">*</span>
                            </label>
                            <InputNumber
                                v-model="form.base.prices[l.id]"
                                :inputId="`p-base-price-${l.id}`"
                                :min="0"
                                :minFractionDigits="fractionDigits"
                                :maxFractionDigits="fractionDigits"
                                locale="vi-VN"
                                placeholder="0"
                                :aria-required="l.is_default ? 'true' : undefined"
                                fluid
                                :invalid="l.is_default && !!err('units.0.retail_price')"
                            />
                            <small v-if="l.is_default && err('units.0.retail_price')" class="field-error">{{ err('units.0.retail_price') }}</small>
                        </div>
                        <div class="field pr-price">
                            <label for="p-cost">Giá vốn</label>
                            <InputNumber
                                v-model="form.cost_price"
                                inputId="p-cost"
                                :min="0"
                                :minFractionDigits="fractionDigits"
                                :maxFractionDigits="fractionDigits"
                                locale="vi-VN"
                                placeholder="0"
                                fluid
                                :invalid="!!err('cost_price')"
                            />
                            <small v-if="err('cost_price')" class="field-error">{{ err('cost_price') }}</small>
                            <small v-else class="field-hint">Để trống nếu chưa biết (tính là 0).</small>
                        </div>
                    </div>
                </section>

                <!-- Đơn vị quy đổi -->
                <section class="pr-section" aria-labelledby="p-conv-title">
                    <h3 id="p-conv-title" class="pr-section-title">Đơn vị quy đổi</h3>

                    <p v-if="!form.conversions.length" class="field-hint pr-empty">
                        Không bắt buộc. Dùng khi bán theo thùng, lố, hộp... (bằng nhiều {{ baseName }}).
                    </p>

                    <div v-for="(c, i) in form.conversions" :key="i" class="pr-box">
                        <div class="pr-unit-top">
                            <ImageInput
                                v-model:file="c.image_file"
                                v-model:remove="c.remove_image"
                                :url="c.image_url"
                                size="3rem"
                                label="ảnh đơn vị quy đổi"
                                :error="err(`units.${i + 1}.image_file`)"
                            />
                            <div class="field pr-grow">
                                <label :for="`p-conv-unit-${i}`">Đơn vị quy đổi<span class="al-req" aria-hidden="true">*</span></label>
                                <Select
                                    v-model="c.unit_id"
                                    :inputId="`p-conv-unit-${i}`"
                                    :options="conversionOptions(i)"
                                    optionLabel="name"
                                    optionValue="id"
                                    placeholder="Chọn đơn vị quy đổi"
                                    aria-required="true"
                                    fluid
                                    :invalid="!!err(`units.${i + 1}.unit_id`)"
                                />
                                <small v-if="err(`units.${i + 1}.unit_id`)" class="field-error">{{ err(`units.${i + 1}.unit_id`) }}</small>
                            </div>
                            <div class="field pr-factor">
                                <label :for="`p-conv-factor-${i}`">Số lượng quy đổi<span class="al-req" aria-hidden="true">*</span></label>
                                <InputNumber
                                    v-model="c.factor"
                                    :inputId="`p-conv-factor-${i}`"
                                    :min="2"
                                    :useGrouping="false"
                                    placeholder="VD: 24"
                                    aria-required="true"
                                    fluid
                                    :invalid="!!err(`units.${i + 1}.factor`)"
                                />
                                <small v-if="err(`units.${i + 1}.factor`)" class="field-error">{{ err(`units.${i + 1}.factor`) }}</small>
                            </div>
                            <Button
                                type="button"
                                icon="pi pi-times"
                                text
                                rounded
                                severity="danger"
                                aria-label="Bỏ đơn vị quy đổi này"
                                class="pr-x"
                                @click="removeConversion(i)"
                            />
                        </div>

                        <div class="pr-prices">
                            <div v-for="l in activeLists" :key="l.id" class="field pr-price">
                                <label :for="`p-conv-price-${i}-${l.id}`">{{ l.name }}</label>
                                <InputNumber
                                    v-model="c.prices[l.id]"
                                    :inputId="`p-conv-price-${i}-${l.id}`"
                                    :min="0"
                                    :minFractionDigits="fractionDigits"
                                    :maxFractionDigits="fractionDigits"
                                    locale="vi-VN"
                                    placeholder="0"
                                    fluid
                                />
                            </div>
                        </div>
                    </div>

                    <small v-if="err('units')" class="field-error">{{ err('units') }}</small>
                    <div v-if="form.conversions.length < MAX_CONVERSIONS">
                        <Button
                            type="button"
                            label="Thêm đơn vị quy đổi"
                            icon="pi pi-plus"
                            size="small"
                            text
                            :disabled="!conversionUnits.length"
                            @click="addConversion"
                        />
                        <small v-if="!conversionUnits.length" class="field-hint">Chưa có đơn vị quy đổi, hãy thêm trong Danh mục &amp; đơn vị.</small>
                    </div>
                </section>

                <!-- Tồn kho -->
                <section class="pr-section" aria-labelledby="p-stock-title">
                    <h3 id="p-stock-title" class="pr-section-title">Tồn kho</h3>
                    <div class="pr-check">
                        <Checkbox v-model="form.track_stock" inputId="p-track" binary />
                        <label for="p-track">Quản lý tồn kho</label>
                    </div>
                    <div v-if="form.track_stock" class="field pr-stock">
                        <label for="p-stock">Tồn kho ban đầu ({{ baseName }})</label>
                        <InputNumber
                            v-model="form.opening_stock"
                            inputId="p-stock"
                            :min="0"
                            :maxFractionDigits="3"
                            locale="vi-VN"
                            placeholder="0"
                            fluid
                            :invalid="!!err('opening_stock')"
                        />
                        <small v-if="err('opening_stock')" class="field-error">{{ err('opening_stock') }}</small>
                    </div>
                    <small v-else class="field-hint">Không bật thì sản phẩm không theo dõi số lượng tồn (phù hợp dịch vụ, hàng không cần đếm).</small>
                </section>

                <div class="field">
                    <label for="p-desc">Mô tả</label>
                    <Textarea id="p-desc" v-model="form.description" rows="2" autoResize fluid />
                </div>

                <div class="pr-check">
                    <Checkbox v-model="form.is_active" inputId="p-active" binary />
                    <label for="p-active">Đang bán</label>
                </div>

                <div class="dialog-actions">
                    <Button type="button" label="Hủy" severity="secondary" outlined @click="dialog = false" />
                    <Button type="submit" :label="editing ? 'Lưu thay đổi' : 'Thêm sản phẩm'" :loading="form.processing" :disabled="hasConflict" />
                </div>
            </form>
        </AppDialog>
    </AppLayout>
</template>

<style>
.pr-filter { min-width: 10.5rem; }
.pr-actions { display: flex; gap: 0.25rem; justify-content: flex-end; }

/* Cột giá: tiêu đề và nội dung cùng căn phải */
.pr-col-right { text-align: right !important; }
.pr-col-right .p-datatable-column-header-content { justify-content: flex-end; }

/* Ô sản phẩm trong bảng */
.pr-name { display: flex; align-items: center; gap: 0.6rem; min-width: 0; }
.pr-thumb { width: 2.4rem; height: 2.4rem; flex: none; border-radius: 0.4rem; object-fit: cover; }
.pr-thumb--empty { display: grid; place-items: center; background: var(--al-line); color: var(--al-muted); }
.pr-name-text { display: flex; flex-direction: column; min-width: 0; }
.pr-sub { display: none; color: var(--al-muted); }

/* Máy tính bảng: bớt cột, đưa thông tin xuống dòng phụ dưới tên */
@media (max-width: 900px) {
    .pr-hide-md { display: none !important; }
    .pr-sub { display: block; }
}
/* Điện thoại */
@media (max-width: 560px) {
    .pr-hide-sm { display: none !important; }
    .pr-filter { flex: 1 1 100%; }
}

/* Form */
.pr-head { display: flex; gap: 1rem; align-items: flex-start; margin-bottom: 1rem; }
.pr-head-name { flex: 1 1 14rem; margin-bottom: 0; min-width: 0; }
.pr-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(13rem, 1fr)); gap: 0 1rem; }
.pr-section { border-top: 1px solid var(--al-line); padding-top: 0.9rem; margin-bottom: 1rem; }
.pr-section-title { font-size: 0.95rem; font-weight: 600; margin: 0 0 0.75rem; }
.pr-empty { margin: 0 0 0.5rem; }
.pr-box { border: 1px solid var(--al-line); border-radius: 0.6rem; padding: 0.75rem; margin-bottom: 0.5rem; }
.pr-unit-top { display: flex; flex-wrap: wrap; gap: 0.75rem; align-items: flex-start; }
.pr-unit-top .field { margin-bottom: 0; }
.pr-grow { flex: 1 1 10rem; min-width: 0; }
.pr-factor { flex: 0 1 10rem; }
.pr-x { margin-top: 1.5rem; }
.pr-prices { display: flex; flex-wrap: wrap; gap: 0.75rem; }
.pr-box .pr-prices { margin-top: 0.75rem; }
.pr-price { flex: 1 1 10rem; margin-bottom: 0 !important; }
.pr-check { display: flex; align-items: center; gap: 0.5rem; }
.pr-check label { font-weight: 500; }
.pr-stock { margin-top: 0.75rem; max-width: 16rem; }

@media (max-width: 560px) {
    .pr-head { flex-direction: column; }
    .pr-head-name { width: 100%; flex-basis: auto; }
}
</style>
