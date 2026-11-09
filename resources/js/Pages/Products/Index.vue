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

const form = useForm({
    code: '',
    name: '',
    barcode: '',
    category_id: null,
    unit_id: null,
    tax_rate: 0,
    description: '',
    is_active: true,
    image_file: null,
    remove_image: false,
    retail_price: null, // giá bán lẻ (bảng giá mặc định)
    qty_pricing: false, // bật "Giá theo số lượng"
    tiers: {}, // { [price_list_id]: { min_qty, price } } cho các bảng giá khác
    track_stock: false,
    opening_stock: null,
});

const taxOn = computed(() => !!config.value.tax_enabled);
const taxOptions = computed(() => (config.value.tax_rates ?? []).map((r) => ({ label: `${r}%`, value: Number(r) })));
const defaultList = computed(() => props.priceLists.find((l) => l.is_default));
const tierLists = computed(() => props.priceLists.filter((l) => l.is_active && !l.is_default));
const categoryOptions = computed(() => props.categories.filter((c) => c.is_active || c.id === form.category_id));
const unitOptions = computed(() => props.units.filter((u) => u.is_active || u.id === form.unit_id));
const defaultUnitId = computed(() => props.units.find((u) => u.is_default && u.is_active)?.id ?? null);
const unitName = computed(() => props.units.find((u) => u.id === form.unit_id)?.name ?? 'đơn vị tính');
const fractionDigits = computed(() => Number(config.value.decimals) || 0);
const err = (key) => form.errors[key];

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
['code', 'name', 'barcode', 'tax_rate', 'category_id', 'unit_id'].forEach((f) => watch(() => form[f], () => form.clearErrors(f)));

const emptyTiers = () => Object.fromEntries(tierLists.value.map((l) => [l.id, { min_qty: null, price: null }]));

// Lỗi của dòng giá theo chỉ số trong danh sách gửi lên server (bảng giá mặc định đứng đầu)
const tierIndex = (listId) => 1 + tierLists.value.findIndex((l) => l.id === listId);

// Mỗi bảng giá một mức số lượng riêng: báo trùng ngay khi nhập
const dupQty = (listId) => {
    if (!form.qty_pricing) return false;
    const q = form.tiers[listId]?.min_qty;
    if (q === null || q === undefined || q === '') return false;
    return tierLists.value.some((l) => l.id !== listId && form.tiers[l.id]?.price != null && Number(form.tiers[l.id]?.min_qty) === Number(q) && form.tiers[listId]?.price != null);
};
const qtyConflict = computed(() => form.qty_pricing && tierLists.value.some((l) => dupQty(l.id)));

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
    form.unit_id = defaultUnitId.value;
    form.tiers = emptyTiers();
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
    form.unit_id = p.unit_id;
    form.tax_rate = Number(p.tax_rate);
    form.description = p.description ?? '';
    form.is_active = p.is_active;
    form.image_file = null;
    form.remove_image = false;
    form.qty_pricing = p.qty_pricing;
    form.track_stock = p.track_stock;
    form.opening_stock = p.track_stock ? Number(p.opening_stock) : null;

    form.retail_price = p.prices.find((x) => x.price_list_id === defaultList.value?.id)?.price ?? null;
    const tiers = emptyTiers();
    p.prices.forEach((x) => {
        if (tiers[x.price_list_id]) tiers[x.price_list_id] = { min_qty: x.min_qty, price: x.price };
    });
    form.tiers = tiers;
    dialog.value = true;
}

function submit() {
    if (hasConflict.value || qtyConflict.value) return;

    form.transform((data) => {
        const payload = {
            code: data.code,
            name: data.name,
            barcode: data.barcode,
            category_id: data.category_id,
            unit_id: data.unit_id,
            tax_rate: data.tax_rate,
            description: data.description,
            is_active: data.is_active,
            qty_pricing: data.qty_pricing,
            track_stock: data.track_stock,
            opening_stock: data.track_stock ? (data.opening_stock ?? 0) : 0,
            // Bảng giá mặc định (bán lẻ) đứng đầu, sau đó là các bảng giá khác; số lượng chỉ gửi khi bật tự động chuyển giá
            prices: [
                { price_list_id: defaultList.value?.id, price: data.retail_price },
                ...tierLists.value.map((l) => ({
                    price_list_id: l.id,
                    price: data.tiers[l.id]?.price,
                    ...(data.qty_pricing ? { min_qty: data.tiers[l.id]?.min_qty } : {}),
                })),
            ],
        };
        if (data.image_file) payload.image_file = data.image_file;
        if (data.remove_image) payload.remove_image = true;
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
// Giá bán lẻ = giá ở bảng giá mặc định
const retailPrice = (p) => (defaultList.value ? p.prices.find((x) => x.price_list_id === defaultList.value.id)?.price ?? null : null);
const subline = (p) => [p.code, p.category, p.unit].filter(Boolean).join(' · ');
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
                <Column field="unit" header="Đơn vị" headerClass="pr-hide-md" bodyClass="pr-hide-md">
                    <template #body="{ data }">{{ data.unit || '—' }}</template>
                </Column>
                <Column header="Giá bán" headerClass="pr-col-right" bodyClass="pr-col-right">
                    <template #body="{ data }">
                        <span v-if="retailPrice(data) !== null">{{ money(retailPrice(data)) }}</span>
                        <span v-else>—</span>
                        <Tag v-if="data.qty_pricing" value="Giá theo SL" severity="info" class="pr-tag" />
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
            :style="{ width: '42rem' }"
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
                    <div class="field">
                        <label for="p-unit">Đơn vị tính<span class="al-req" aria-hidden="true">*</span></label>
                        <Select
                            v-model="form.unit_id"
                            inputId="p-unit"
                            :options="unitOptions"
                            optionLabel="name"
                            optionValue="id"
                            placeholder="Chọn đơn vị"
                            aria-required="true"
                            fluid
                            :invalid="!!err('unit_id')"
                        />
                        <small v-if="err('unit_id')" class="field-error">{{ err('unit_id') }}</small>
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
                    <div v-if="taxOn" class="field">
                        <label for="p-tax">Thuế</label>
                        <Select v-model="form.tax_rate" inputId="p-tax" :options="taxOptions" optionLabel="label" optionValue="value" fluid :invalid="!!err('tax_rate')" />
                        <small v-if="err('tax_rate')" class="field-error">{{ err('tax_rate') }}</small>
                    </div>
                </div>

                <!-- Giá bán -->
                <section class="pr-section" aria-labelledby="p-price-title">
                    <h3 id="p-price-title" class="pr-section-title">Giá bán</h3>

                    <div class="field pr-retail">
                        <label for="p-retail">{{ defaultList?.name ?? 'Giá bán lẻ' }}<span class="al-req" aria-hidden="true">*</span></label>
                        <InputNumber
                            v-model="form.retail_price"
                            inputId="p-retail"
                            :min="0"
                            :minFractionDigits="fractionDigits"
                            :maxFractionDigits="fractionDigits"
                            locale="vi-VN"
                            placeholder="0"
                            aria-required="true"
                            fluid
                            :invalid="!!err('prices.0.price')"
                        />
                        <small v-if="err('prices.0.price')" class="field-error">{{ err('prices.0.price') }}</small>
                    </div>

                    <!-- Các bảng giá khác: luôn nhập được, chọn thủ công khi bán -->
                    <div v-if="tierLists.length" class="pr-tiers">
                        <div v-for="l in tierLists" :key="l.id" class="pr-tier" :class="{ 'pr-tier--qty': form.qty_pricing }">
                            <div class="field">
                                <label :for="`p-tier-price-${l.id}`">{{ l.name }}</label>
                                <InputNumber
                                    v-model="form.tiers[l.id].price"
                                    :inputId="`p-tier-price-${l.id}`"
                                    :min="0"
                                    :minFractionDigits="fractionDigits"
                                    :maxFractionDigits="fractionDigits"
                                    locale="vi-VN"
                                    placeholder="Không bán theo giá này"
                                    fluid
                                    :invalid="!!err(`prices.${tierIndex(l.id)}.price`)"
                                />
                                <small v-if="err(`prices.${tierIndex(l.id)}.price`)" class="field-error">{{ err(`prices.${tierIndex(l.id)}.price`) }}</small>
                            </div>
                            <div v-if="form.qty_pricing" class="field">
                                <label :for="`p-tier-qty-${l.id}`">Tự chuyển từ số lượng ({{ unitName }})</label>
                                <InputNumber
                                    v-model="form.tiers[l.id].min_qty"
                                    :inputId="`p-tier-qty-${l.id}`"
                                    :min="0"
                                    :maxFractionDigits="3"
                                    locale="vi-VN"
                                    placeholder="VD: 100"
                                    fluid
                                    :invalid="dupQty(l.id) || !!err(`prices.${tierIndex(l.id)}.min_qty`)"
                                />
                                <small v-if="dupQty(l.id)" class="field-error">Số lượng này đã dùng cho một bảng giá khác. Mỗi bảng giá cần một mức riêng.</small>
                                <small v-else-if="err(`prices.${tierIndex(l.id)}.min_qty`)" class="field-error">{{ err(`prices.${tierIndex(l.id)}.min_qty`) }}</small>
                            </div>
                        </div>
                        <small class="field-hint pr-hint-block">
                            Các bảng giá này luôn chọn thủ công được khi bán, kể cả chưa đủ số lượng.
                        </small>
                    </div>
                    <p v-else class="field-hint">
                        Muốn bán nhiều loại giá (ví dụ "Giá sỉ"), hãy thêm bảng giá trong Danh mục &amp; đơn vị, tab Bảng giá.
                    </p>

                    <template v-if="tierLists.length">
                        <div class="pr-check pr-auto">
                            <Checkbox v-model="form.qty_pricing" inputId="p-qty" binary />
                            <label for="p-qty">Tự động chuyển giá theo số lượng</label>
                        </div>
                        <small v-if="form.qty_pricing" class="field-hint pr-hint-block">
                            Khi số lượng bán từ mức đã nhập trở lên, màn hình bán hàng hỏi có áp dụng giá đó không. Để trống số lượng nếu bảng giá đó chỉ chọn thủ công.
                        </small>
                        <small v-if="err('qty_pricing')" class="field-error pr-hint-block">{{ err('qty_pricing') }}</small>
                    </template>
                </section>

                <!-- Tồn kho -->
                <section class="pr-section" aria-labelledby="p-stock-title">
                    <h3 id="p-stock-title" class="pr-section-title">Tồn kho</h3>
                    <div class="pr-check">
                        <Checkbox v-model="form.track_stock" inputId="p-track" binary />
                        <label for="p-track">Quản lý tồn kho</label>
                    </div>
                    <small class="field-hint pr-hint-block">
                        Bật thì khi bán sẽ trừ kho và không cho bán quá số lượng tồn. Tắt thì không theo dõi số lượng.
                    </small>
                    <div v-if="form.track_stock" class="field pr-stock">
                        <label for="p-stock">Tồn kho ban đầu ({{ unitName }})</label>
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
                    <Button type="submit" :label="editing ? 'Lưu thay đổi' : 'Thêm sản phẩm'" :loading="form.processing" :disabled="hasConflict || qtyConflict" />
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
.pr-retail { max-width: 16rem; }
.pr-check { display: flex; align-items: center; gap: 0.5rem; }
.pr-check label { font-weight: 500; }
.pr-hint-block { display: block; margin: 0.35rem 0 0; }
.pr-tiers { margin-top: 0.25rem; }
.pr-tier { display: grid; grid-template-columns: 1fr; gap: 0 0.75rem; margin-bottom: 0.5rem; max-width: 16rem; }
.pr-tier--qty { grid-template-columns: minmax(0, 1fr) minmax(0, 1fr); max-width: none; }
.pr-tier .field { margin-bottom: 0; }
.pr-auto { margin-top: 0.75rem; }
.pr-stock { margin-top: 0.75rem; max-width: 16rem; }
.pr-tag { margin-left: 0.4rem; }

@media (max-width: 560px) {
    .pr-head { flex-direction: column; }
    .pr-head-name { width: 100%; flex-basis: auto; }
    .pr-tier--qty { grid-template-columns: 1fr; gap: 0.5rem; }
    .pr-tier { max-width: none; }
}
</style>
