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
import FieldStatus from '@/Components/FieldStatus.vue';
import { useConfirm } from 'primevue/useconfirm';
import AppDialog from '@/Components/AppDialog.vue';
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
const dialog = ref(false);
const editing = ref(null);
const imagePreview = ref(null);

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
    units: [],
});

const taxOn = computed(() => !!config.value.tax_enabled);
const taxOptions = computed(() => (config.value.tax_rates ?? []).map((r) => ({ label: `${r}%`, value: Number(r) })));
const activeLists = computed(() => props.priceLists.filter((l) => l.is_active));
const defaultList = computed(() => props.priceLists.find((l) => l.is_default));
const categoryOptions = computed(() => props.categories.filter((c) => c.is_active || c.id === form.category_id));

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
['code', 'name', 'barcode', 'tax_rate'].forEach((f) => watch(() => form[f], () => form.clearErrors(f)));

function blankUnit(base = false) {
    return { unit_id: null, factor: base ? 1 : null, prices: Object.fromEntries(activeLists.value.map((l) => [l.id, null])) };
}

function unitOptions(index) {
    const used = form.units.filter((_, i) => i !== index).map((u) => u.unit_id);
    return props.units.filter((u) => !used.includes(u.id) && (u.is_active || u.id === form.units[index].unit_id));
}

function addUnit() {
    if (form.units.length < 6) form.units.push(blankUnit());
}

function removeUnit(index) {
    form.units.splice(index, 1);
}

function resetImage() {
    imagePreview.value = null;
}

function openCreate() {
    editing.value = null;
    codeCheck.reset();
    barcodeCheck.reset();
    form.reset();
    form.clearErrors();
    form.tax_rate = taxOn.value ? Number(config.value.tax_default_rate) : 0;
    form.units = [blankUnit(true)];
    resetImage();
    dialog.value = true;
}

function openEdit(p) {
    editing.value = p;
    codeCheck.reset();
    barcodeCheck.reset();
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
    form.units = p.units.map((u) => {
        const row = blankUnit();
        row.unit_id = u.unit_id;
        row.factor = u.factor;
        u.prices.forEach((pr) => (row.prices[pr.price_list_id] = pr.price));
        return row;
    });
    resetImage();
    dialog.value = true;
}

function onImage(event) {
    const file = event.target.files?.[0];
    if (!file) return;
    form.image_file = file;
    form.remove_image = false;
    imagePreview.value = URL.createObjectURL(file);
}

function clearImage() {
    form.image_file = null;
    form.remove_image = true;
    imagePreview.value = null;
}

const shownImage = computed(() => imagePreview.value ?? (form.remove_image ? null : editing.value?.image_url ?? null));

function submit() {
    if (hasConflict.value) return;

    form.transform((data) => {
        const payload = {
            ...data,
            // Gửi đúng dạng server cần: mỗi đơn vị kèm danh sách giá theo bảng giá
            units: data.units.map((u) => ({
                unit_id: u.unit_id,
                factor: u.factor,
                prices: activeLists.value.map((l) => ({ price_list_id: l.id, price: u.prices[l.id] ?? 0 })),
            })),
        };
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

// Giá ở bảng giá mặc định của đơn vị cơ bản
function basePrice(p) {
    const base = p.units[0];
    if (!base || !defaultList.value) return null;
    return base.prices.find((x) => x.price_list_id === defaultList.value.id)?.price ?? 0;
}

const unitSummary = (p) =>
    p.units.map((u) => (u.factor === 1 ? u.unit : `${u.unit} (${u.factor})`)).join(', ');

const fractionDigits = computed(() => Number(config.value.decimals) || 0);
const err = (key) => form.errors[key];
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
                    style="min-width: 11rem"
                />
                <Select
                    v-model="statusFilter"
                    :options="statusOptions"
                    optionLabel="label"
                    optionValue="value"
                    aria-label="Lọc theo trạng thái"
                    style="min-width: 10rem"
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
                        <div style="display: flex; align-items: center; gap: 0.6rem">
                            <img
                                v-if="data.image_url"
                                :src="data.image_url"
                                alt=""
                                style="width: 2.25rem; height: 2.25rem; border-radius: 0.4rem; object-fit: cover"
                            />
                            <span
                                v-else
                                class="pi pi-image"
                                style="width: 2.25rem; height: 2.25rem; display: grid; place-items: center; border-radius: 0.4rem; background: var(--al-line); color: var(--al-muted)"
                            />
                            <span>{{ data.name }}</span>
                        </div>
                    </template>
                </Column>
                <Column field="code" header="Mã" />
                <Column field="barcode" header="Mã vạch">
                    <template #body="{ data }">{{ data.barcode || '—' }}</template>
                </Column>
                <Column field="category" header="Danh mục">
                    <template #body="{ data }">{{ data.category || '—' }}</template>
                </Column>
                <Column header="Đơn vị">
                    <template #body="{ data }">{{ unitSummary(data) }}</template>
                </Column>
                <Column header="Giá bán" headerClass="pr-col-right" bodyClass="pr-col-right">
                    <template #body="{ data }">
                        <span v-if="basePrice(data) !== null">{{ money(basePrice(data)) }}</span>
                        <span v-else>—</span>
                    </template>
                </Column>
                <Column header="Trạng thái" style="width: 8rem">
                    <template #body="{ data }">
                        <Tag :value="data.is_active ? 'Đang bán' : 'Ngừng bán'" :severity="data.is_active ? 'success' : 'secondary'" />
                    </template>
                </Column>
                <Column header="" style="width: 7rem">
                    <template #body="{ data }">
                        <div style="display: flex; gap: 0.25rem; justify-content: flex-end">
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
            :style="{ width: '44rem' }"
            :breakpoints="{ '768px': '94vw' }"
        >
            <form @submit.prevent="submit" novalidate>
                <div class="field">
                    <label for="p-name">Tên sản phẩm</label>
                    <InputText id="p-name" v-model="form.name" autocomplete="off" fluid :invalid="!!err('name')" />
                    <small v-if="err('name')" class="field-error">{{ err('name') }}</small>
                </div>

                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(13rem, 1fr)); gap: 0 1rem">
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
                        <label for="p-barcode">Mã vạch <span class="field-hint">(không bắt buộc)</span></label>
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
                    <div class="field">
                        <label for="p-category">Danh mục</label>
                        <Select
                            v-model="form.category_id"
                            inputId="p-category"
                            :options="categoryOptions"
                            optionLabel="name"
                            optionValue="id"
                            placeholder="Chưa phân loại"
                            showClear
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
                </div>

                <!-- Đơn vị tính và giá -->
                <div class="field">
                    <label>Đơn vị tính và giá bán</label>
                    <div
                        v-for="(u, i) in form.units"
                        :key="i"
                        style="border: 1px solid var(--al-line); border-radius: 0.5rem; padding: 0.75rem; margin-bottom: 0.5rem"
                    >
                        <div style="display: flex; flex-wrap: wrap; gap: 0.75rem; align-items: flex-start">
                            <div class="field" style="flex: 1 1 10rem; margin-bottom: 0">
                                <small class="field-hint">{{ i === 0 ? 'Đơn vị cơ bản' : 'Đơn vị quy đổi' }}</small>
                                <Select
                                    v-model="u.unit_id"
                                    :options="unitOptions(i)"
                                    optionLabel="name"
                                    optionValue="id"
                                    placeholder="Chọn đơn vị"
                                    fluid
                                    :invalid="!!err(`units.${i}.unit_id`)"
                                />
                                <small v-if="err(`units.${i}.unit_id`)" class="field-error">{{ err(`units.${i}.unit_id`) }}</small>
                            </div>
                            <div class="field" style="flex: 0 1 8rem; margin-bottom: 0">
                                <small class="field-hint">{{ i === 0 ? 'Quy đổi' : 'Bằng bao nhiêu đơn vị cơ bản' }}</small>
                                <InputNumber
                                    v-model="u.factor"
                                    :min="1"
                                    :useGrouping="false"
                                    :disabled="i === 0"
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
                                style="margin-top: 1.25rem"
                                @click="removeUnit(i)"
                            />
                        </div>

                        <div style="display: flex; flex-wrap: wrap; gap: 0.75rem; margin-top: 0.75rem">
                            <div v-for="l in activeLists" :key="l.id" class="field" style="flex: 1 1 10rem; margin-bottom: 0">
                                <small class="field-hint">{{ l.name }}</small>
                                <InputNumber
                                    v-model="u.prices[l.id]"
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
                    <div>
                        <Button
                            v-if="form.units.length < 6"
                            type="button"
                            label="Thêm đơn vị quy đổi"
                            icon="pi pi-plus"
                            size="small"
                            text
                            @click="addUnit"
                        />
                    </div>
                </div>

                <div class="field">
                    <label for="p-desc">Mô tả <span class="field-hint">(không bắt buộc)</span></label>
                    <Textarea id="p-desc" v-model="form.description" rows="2" autoResize fluid />
                </div>

                <div class="field">
                    <label>Ảnh sản phẩm</label>
                    <div style="display: flex; align-items: center; gap: 0.75rem; flex-wrap: wrap">
                        <img v-if="shownImage" :src="shownImage" alt="" style="width: 4rem; height: 4rem; border-radius: 0.5rem; object-fit: cover" />
                        <input type="file" accept="image/jpeg,image/png,image/webp" @change="onImage" />
                        <Button v-if="shownImage" type="button" label="Bỏ ảnh" size="small" text severity="danger" @click="clearImage" />
                    </div>
                    <small v-if="err('image_file')" class="field-error">{{ err('image_file') }}</small>
                    <small v-else class="field-hint">JPG, PNG hoặc WebP, tối đa 2 MB.</small>
                </div>

                <div class="field" style="flex-direction: row; align-items: center; gap: 0.5rem">
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
/* Cột giá: tiêu đề và nội dung cùng căn phải */
.pr-col-right { text-align: right !important; }
.pr-col-right .p-datatable-column-header-content { justify-content: flex-end; }
</style>
