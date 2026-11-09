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
const MAX_UNITS = 6;
const MAX_VARIANTS = 30;

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
    has_variants: false,
    variant_label: 'Màu sắc',
    units: [],
    variants: [],
});

const taxOn = computed(() => !!config.value.tax_enabled);
const taxOptions = computed(() => (config.value.tax_rates ?? []).map((r) => ({ label: `${r}%`, value: Number(r) })));
const activeLists = computed(() => props.priceLists.filter((l) => l.is_active));
const defaultList = computed(() => props.priceLists.find((l) => l.is_default));
const categoryOptions = computed(() => props.categories.filter((c) => c.is_active || c.id === form.category_id));
const fractionDigits = computed(() => Number(config.value.decimals) || 0);
const err = (key) => form.errors[key];

// Đơn vị cơ bản mặc định khi tạo sản phẩm là "Cái"
const defaultUnitId = computed(() => props.units.find((u) => u.is_active && u.name.trim().toLowerCase() === 'cái')?.id ?? null);

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
['code', 'name', 'barcode', 'tax_rate', 'category_id', 'variant_label'].forEach((f) => watch(() => form[f], () => form.clearErrors(f)));

function blankUnit(base = false) {
    return {
        id: null,
        unit_id: base ? defaultUnitId.value : null,
        factor: base ? 1 : null,
        prices: Object.fromEntries(activeLists.value.map((l) => [l.id, null])),
        image_file: null,
        remove_image: false,
        image_url: null,
    };
}

function blankVariant(name = '') {
    return { id: null, name, code: '', barcode: '', is_active: true, image_file: null, remove_image: false, image_url: null };
}

function unitOptions(index) {
    const used = form.units.filter((_, i) => i !== index).map((u) => u.unit_id);
    return props.units.filter((u) => !used.includes(u.id) && (u.is_active || u.id === form.units[index].unit_id));
}

const addUnit = () => form.units.length < MAX_UNITS && form.units.push(blankUnit());
const removeUnit = (i) => form.units.splice(i, 1);

function toggleVariants(on) {
    form.has_variants = on;
    if (on && !form.variants.length) form.variants.push(blankVariant());
}
const addVariant = () => form.variants.length < MAX_VARIANTS && form.variants.push(blankVariant());
const removeVariant = (i) => form.variants.splice(i, 1);

// Thêm nhanh nhiều biến thể: "Xanh, Đỏ, Vàng"
const quickAdd = ref('');
function applyQuickAdd() {
    const names = quickAdd.value
        .split(/[,;\n]/)
        .map((s) => s.trim())
        .filter(Boolean);
    const have = new Set(form.variants.map((v) => v.name.trim().toLowerCase()));

    // Dòng trống đầu tiên được dùng lại
    form.variants = form.variants.filter((v) => v.name.trim() || v.id || v.code || v.barcode || v.image_file);
    for (const n of names) {
        if (form.variants.length >= MAX_VARIANTS) break;
        if (have.has(n.toLowerCase())) continue;
        have.add(n.toLowerCase());
        form.variants.push(blankVariant(n));
    }
    quickAdd.value = '';
}

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
    form.units = [blankUnit(true)];
    form.variants = [];
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
    form.has_variants = p.variants.length > 0;
    form.variant_label = p.variant_label || 'Màu sắc';
    form.units = p.units.map((u) => {
        const row = blankUnit();
        row.id = u.id;
        row.unit_id = u.unit_id;
        row.factor = u.factor;
        row.image_url = u.image_url;
        u.prices.forEach((pr) => (row.prices[pr.price_list_id] = pr.price));
        return row;
    });
    form.variants = p.variants.map((v) => ({
        id: v.id,
        name: v.name,
        code: v.code,
        barcode: v.barcode ?? '',
        is_active: v.is_active,
        image_file: null,
        remove_image: false,
        image_url: v.image_url,
    }));
    dialog.value = true;
}

// Chỉ gửi trường có giá trị cho tệp ảnh (tránh gửi chuỗi rỗng)
const withImage = (row, out) => {
    if (row.image_file) out.image_file = row.image_file;
    if (row.remove_image) out.remove_image = true;
    return out;
};

function submit() {
    if (hasConflict.value) return;

    form.transform((data) => {
        const payload = withImage(data, {
            code: data.code,
            name: data.name,
            barcode: data.has_variants ? '' : data.barcode,
            category_id: data.category_id,
            tax_rate: data.tax_rate,
            description: data.description,
            is_active: data.is_active,
            has_variants: data.has_variants,
            variant_label: data.has_variants ? data.variant_label : '',
            units: data.units.map((u) =>
                withImage(u, {
                    unit_id: u.unit_id,
                    factor: u.factor,
                    prices: activeLists.value.map((l) => ({ price_list_id: l.id, price: u.prices[l.id] })),
                }),
            ),
            variants: data.has_variants
                ? data.variants.map((v) =>
                      withImage(v, {
                          ...(v.id ? { id: v.id } : {}),
                          name: v.name,
                          code: v.code,
                          barcode: v.barcode,
                          is_active: v.is_active,
                      }),
                  )
                : [],
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
        message: `Xóa sản phẩm "${p.name}"${p.variants.length ? ` cùng ${p.variants.length} biến thể` : ''}? Thao tác này không thể hoàn tác.`,
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
const variantNames = (p) => p.variants.map((v) => v.name).join(', ');
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
                                <small v-if="data.variants.length" class="pr-variants" :title="variantNames(data)">
                                    {{ data.variants.length }} {{ (data.variant_label || 'biến thể').toLowerCase() }}: {{ variantNames(data) }}
                                </small>
                                <small class="pr-sub">{{ subline(data) }}</small>
                            </div>
                        </div>
                    </template>
                </Column>
                <Column field="code" header="Mã" headerClass="pr-hide-md" bodyClass="pr-hide-md" />
                <Column field="barcode" header="Mã vạch" headerClass="pr-hide-md" bodyClass="pr-hide-md">
                    <template #body="{ data }">{{ data.variants.length ? 'Theo biến thể' : data.barcode || '—' }}</template>
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
            :style="{ width: '50rem' }"
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
                    <div v-if="!form.has_variants" class="field">
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

                <!-- Đơn vị tính và giá -->
                <div class="field">
                    <label>Đơn vị tính và giá bán</label>
                    <div v-for="(u, i) in form.units" :key="i" class="pr-box">
                        <div class="pr-unit-top">
                            <ImageInput
                                v-model:file="u.image_file"
                                v-model:remove="u.remove_image"
                                :url="u.image_url"
                                size="3rem"
                                label="ảnh đơn vị"
                                :error="err(`units.${i}.image_file`)"
                            />
                            <div class="field pr-grow">
                                <small class="field-hint">{{ i === 0 ? 'Đơn vị cơ bản' : 'Đơn vị quy đổi' }}</small>
                                <Select
                                    v-model="u.unit_id"
                                    :options="unitOptions(i)"
                                    optionLabel="name"
                                    optionValue="id"
                                    placeholder="Chọn đơn vị"
                                    :aria-label="i === 0 ? 'Đơn vị cơ bản' : 'Đơn vị quy đổi'"
                                    fluid
                                    :invalid="!!err(`units.${i}.unit_id`)"
                                />
                                <small v-if="err(`units.${i}.unit_id`)" class="field-error">{{ err(`units.${i}.unit_id`) }}</small>
                            </div>
                            <div class="field pr-factor">
                                <small class="field-hint">Số lượng quy đổi</small>
                                <InputNumber
                                    v-model="u.factor"
                                    :min="1"
                                    :useGrouping="false"
                                    :disabled="i === 0"
                                    aria-label="Số lượng quy đổi"
                                    fluid
                                    :invalid="!!err(`units.${i}.factor`)"
                                />
                                <small v-if="err(`units.${i}.factor`)" class="field-error">{{ err(`units.${i}.factor`) }}</small>
                            </div>
                            <Button
                                v-if="i > 0"
                                type="button"
                                icon="pi pi-times"
                                text
                                rounded
                                severity="danger"
                                aria-label="Bỏ đơn vị này"
                                class="pr-x"
                                @click="removeUnit(i)"
                            />
                        </div>

                        <div class="pr-prices">
                            <div v-for="l in activeLists" :key="l.id" class="field pr-price">
                                <small class="field-hint">
                                    {{ l.name }}<span v-if="i === 0 && l.is_default" class="al-req" aria-hidden="true">*</span>
                                </small>
                                <InputNumber
                                    v-model="u.prices[l.id]"
                                    :min="0"
                                    :minFractionDigits="fractionDigits"
                                    :maxFractionDigits="fractionDigits"
                                    locale="vi-VN"
                                    placeholder="0"
                                    :aria-label="`Giá ${l.name}`"
                                    :aria-required="i === 0 && l.is_default ? 'true' : undefined"
                                    fluid
                                    :invalid="i === 0 && l.is_default && !!err(`units.${i}.retail_price`)"
                                />
                                <small v-if="i === 0 && l.is_default && err(`units.${i}.retail_price`)" class="field-error">
                                    {{ err(`units.${i}.retail_price`) }}
                                </small>
                            </div>
                        </div>
                    </div>
                    <small v-if="err('units')" class="field-error">{{ err('units') }}</small>
                    <div v-if="form.units.length < MAX_UNITS">
                        <Button type="button" label="Thêm đơn vị quy đổi" icon="pi pi-plus" size="small" text @click="addUnit" />
                    </div>
                </div>

                <!-- Biến thể -->
                <div class="field">
                    <div class="pr-check">
                        <Checkbox :modelValue="form.has_variants" inputId="p-hasvar" binary @update:modelValue="toggleVariants" />
                        <label for="p-hasvar">Sản phẩm có biến thể (ví dụ: màu sắc)</label>
                    </div>

                    <div v-if="form.has_variants" class="pr-variants-box">
                        <div class="field">
                            <label for="p-varlabel">Tên thuộc tính<span class="al-req" aria-hidden="true">*</span></label>
                            <InputText id="p-varlabel" v-model="form.variant_label" maxlength="50" autocomplete="off" aria-required="true" fluid :invalid="!!err('variant_label')" />
                            <small v-if="err('variant_label')" class="field-error">{{ err('variant_label') }}</small>
                        </div>

                        <div class="field">
                            <label for="p-quick">Thêm nhanh</label>
                            <div class="pr-quick">
                                <InputText
                                    id="p-quick"
                                    v-model="quickAdd"
                                    placeholder="Xanh, Đỏ, Vàng"
                                    autocomplete="off"
                                    fluid
                                    @keydown.enter.prevent="applyQuickAdd"
                                />
                                <Button type="button" label="Thêm" icon="pi pi-plus" severity="secondary" :disabled="!quickAdd.trim()" @click="applyQuickAdd" />
                            </div>
                            <small class="field-hint">Ngăn cách các {{ (form.variant_label || 'biến thể').toLowerCase() }} bằng dấu phẩy.</small>
                        </div>

                        <div v-for="(v, n) in form.variants" :key="v.id ?? `new-${n}`" class="pr-box pr-var">
                            <ImageInput
                                v-model:file="v.image_file"
                                v-model:remove="v.remove_image"
                                :url="v.image_url"
                                size="3rem"
                                :label="`ảnh ${v.name || 'biến thể'}`"
                                :error="err(`variants.${n}.image_file`)"
                            />
                            <div class="pr-var-fields">
                                <div class="field">
                                    <small class="field-hint">{{ form.variant_label || 'Biến thể' }}<span class="al-req" aria-hidden="true">*</span></small>
                                    <InputText v-model="v.name" autocomplete="off" aria-required="true" :aria-label="form.variant_label || 'Tên biến thể'" fluid :invalid="!!err(`variants.${n}.name`)" />
                                    <small v-if="err(`variants.${n}.name`)" class="field-error">{{ err(`variants.${n}.name`) }}</small>
                                </div>
                                <div class="field">
                                    <small class="field-hint">Mã</small>
                                    <InputText v-model="v.code" placeholder="Tự động" autocomplete="off" spellcheck="false" aria-label="Mã biến thể" fluid :invalid="!!err(`variants.${n}.code`)" />
                                    <small v-if="err(`variants.${n}.code`)" class="field-error">{{ err(`variants.${n}.code`) }}</small>
                                </div>
                                <div class="field">
                                    <small class="field-hint">Mã vạch</small>
                                    <InputText v-model="v.barcode" autocomplete="off" spellcheck="false" aria-label="Mã vạch biến thể" fluid :invalid="!!err(`variants.${n}.barcode`)" />
                                    <small v-if="err(`variants.${n}.barcode`)" class="field-error">{{ err(`variants.${n}.barcode`) }}</small>
                                </div>
                            </div>
                            <div class="pr-var-side">
                                <div class="pr-check">
                                    <Checkbox v-model="v.is_active" :inputId="`v-active-${n}`" binary />
                                    <label :for="`v-active-${n}`">Đang bán</label>
                                </div>
                                <Button type="button" icon="pi pi-trash" text rounded severity="danger" aria-label="Bỏ biến thể này" @click="removeVariant(n)" />
                            </div>
                        </div>

                        <small v-if="err('variants')" class="field-error">{{ err('variants') }}</small>
                        <div v-if="form.variants.length < MAX_VARIANTS">
                            <Button type="button" label="Thêm biến thể" icon="pi pi-plus" size="small" text @click="addVariant" />
                        </div>
                        <small class="field-hint">Giá bán và đơn vị quy đổi dùng chung cho mọi biến thể. Mã để trống sẽ tự đánh theo mã sản phẩm.</small>
                    </div>
                </div>

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
.pr-variants { color: var(--al-muted); overflow: hidden; text-overflow: ellipsis; white-space: nowrap; max-width: 22rem; }
.pr-sub { display: none; color: var(--al-muted); }

/* Máy tính bảng: bớt cột, đưa thông tin xuống dòng phụ dưới tên */
@media (max-width: 900px) {
    .pr-hide-md { display: none !important; }
    .pr-sub { display: block; }
    .pr-variants { max-width: 14rem; }
}
/* Điện thoại */
@media (max-width: 560px) {
    .pr-hide-sm { display: none !important; }
    .pr-toolbar-wrap, .pr-filter { flex: 1 1 100%; }
}

/* Form */
.pr-head { display: flex; gap: 1rem; align-items: flex-start; margin-bottom: 1rem; }
.pr-head-name { flex: 1 1 14rem; margin-bottom: 0; min-width: 0; }
.pr-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(13rem, 1fr)); gap: 0 1rem; }
.pr-box { border: 1px solid var(--al-line); border-radius: 0.6rem; padding: 0.75rem; margin-bottom: 0.5rem; }
.pr-unit-top { display: flex; flex-wrap: wrap; gap: 0.75rem; align-items: flex-start; }
.pr-unit-top .field { margin-bottom: 0; }
.pr-grow { flex: 1 1 10rem; min-width: 0; }
.pr-factor { flex: 0 1 9rem; }
.pr-x { margin-top: 1.1rem; }
.pr-prices { display: flex; flex-wrap: wrap; gap: 0.75rem; margin-top: 0.75rem; }
.pr-price { flex: 1 1 10rem; margin-bottom: 0 !important; }
.pr-check { display: flex; align-items: center; gap: 0.5rem; }
.pr-check label { font-weight: 500; }
.pr-variants-box { margin-top: 0.75rem; padding-left: 0.75rem; border-left: 3px solid var(--al-line); }
.pr-quick { display: flex; gap: 0.5rem; }
.pr-var { display: flex; flex-wrap: wrap; gap: 0.75rem; align-items: flex-start; }
.pr-var-fields { flex: 1 1 18rem; display: grid; grid-template-columns: repeat(auto-fit, minmax(9rem, 1fr)); gap: 0.5rem; min-width: 0; }
.pr-var-fields .field { margin-bottom: 0; }
.pr-var-side { display: flex; align-items: center; gap: 0.25rem; margin-left: auto; }

@media (max-width: 560px) {
    .pr-head { flex-direction: column; }
    .pr-head-name { width: 100%; flex-basis: auto; }
    .pr-var-side { width: 100%; justify-content: space-between; margin-left: 0; }
    .pr-quick { flex-wrap: wrap; }
}
</style>
