<script setup>
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import AutoComplete from 'primevue/autocomplete';
import Button from 'primevue/button';
import Checkbox from 'primevue/checkbox';
import Column from 'primevue/column';
import DataTable from 'primevue/datatable';
import DatePicker from 'primevue/datepicker';
import IconField from 'primevue/iconfield';
import InputIcon from 'primevue/inputicon';
import InputText from 'primevue/inputtext';
import Menu from 'primevue/menu';
import Paginator from 'primevue/paginator';
import Select from 'primevue/select';
import SelectButton from 'primevue/selectbutton';
import Textarea from 'primevue/textarea';
import { useConfirm } from 'primevue/useconfirm';
import AppDialog from '@/Components/AppDialog.vue';
import AvatarPicker from '@/Components/AvatarPicker.vue';
import FieldStatus from '@/Components/FieldStatus.vue';
import PhoneInput from '@/Components/PhoneInput.vue';
import TierBadge from '@/Components/TierBadge.vue';
import UserAvatar from '@/Components/UserAvatar.vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import { useMoney } from '@/composables/useMoney';
import { usePermission } from '@/composables/usePermission';
import { useUniqueCheck } from '@/composables/useUniqueCheck';
import { formatPhone } from '@/utils/phone';
import CustomerDetail from './Partials/CustomerDetail.vue';
import ImportDialog from './Partials/ImportDialog.vue';

const props = defineProps({
    customers: { type: Object, required: true },
    filters: { type: Object, default: () => ({ search: '', scope: 'basic', sort: 'newest', tier: null }) },
    tiersEnabled: { type: Boolean, default: false },
    tiers: { type: Array, default: () => [] },
    avatarPresets: { type: Array, default: () => [] },
    occupations: { type: Array, default: () => [] },
    importResult: { type: Object, default: null },
});

const { can } = usePermission();
const { money } = useMoney();
const confirm = useConfirm();

/* ---------- Kiểu hiển thị ----------
   Máy tính (>= 1024px, cùng mốc với thanh bên): chọn Bảng hoặc Thẻ, nhớ lại ở lần mở sau.
   Máy tính bảng / điện thoại: luôn là Thẻ. */
const VIEW_KEY = 'almira.customers.view';
const loadView = () => {
    try {
        return localStorage.getItem(VIEW_KEY) === 'card' ? 'card' : 'table';
    } catch {
        return 'table';
    }
};
const view = ref(loadView());
watch(view, (v) => {
    try {
        localStorage.setItem(VIEW_KEY, v);
    } catch {
        // trình duyệt chặn lưu trữ: bỏ qua
    }
});
const viewOptions = [
    { value: 'table', icon: 'pi pi-table', label: 'Dạng bảng' },
    { value: 'card', icon: 'pi pi-th-large', label: 'Dạng thẻ' },
];

const media = window.matchMedia('(min-width: 1024px)');
const isDesktop = ref(media.matches);
const onMedia = (e) => (isDesktop.value = e.matches);
media.addEventListener('change', onMedia);
onBeforeUnmount(() => media.removeEventListener('change', onMedia));

const showTable = computed(() => isDesktop.value && view.value === 'table');

/* ---------- Tìm kiếm, sắp xếp, lọc hạng, phân trang (xử lý ở server) ---------- */
const sortOptions = [
    { value: 'newest', label: 'Mới nhất' },
    { value: 'oldest', label: 'Cũ nhất' },
    { value: 'name', label: 'Theo tên (A → Z)' },
    { value: 'name_desc', label: 'Theo tên (Z → A)' },
    { value: 'age_asc', label: 'Theo tuổi (nhỏ → lớn)' },
    { value: 'age_desc', label: 'Theo tuổi (lớn → nhỏ)' },
    { value: 'birthday', label: 'Sinh nhật sắp tới' },
    { value: 'spent', label: 'Chi tiêu (cao → thấp)' },
    { value: 'spent_asc', label: 'Chi tiêu (thấp → cao)' },
];

const ALL_TIERS = 'all'; // PrimeVue Select không hiện lựa chọn có giá trị null, nên dùng giá trị riêng
const search = ref(props.filters.search ?? '');
const sort = ref(props.filters.sort ?? 'newest');
const tier = ref(props.filters.tier ?? ALL_TIERS);
// Tìm theo: 'basic' = tên, mã, số điện thoại; 'extra' = địa chỉ, ghi chú
const scope = ref(props.filters.scope === 'extra' ? 'extra' : 'basic');
const scopeOptions = [
    { value: 'basic', label: 'Khách hàng', icon: 'pi pi-user', hint: 'Tìm theo tên, số điện thoại hoặc mã khách' },
    { value: 'extra', label: 'Địa chỉ, ghi chú', icon: 'pi pi-map-marker', hint: 'Tìm trong địa chỉ và ghi chú' },
];
const searchPlaceholder = computed(() => scopeOptions.find((o) => o.value === scope.value).hint);

const tierOptions = computed(() => [
    { value: ALL_TIERS, label: 'Tất cả hạng' },
    ...props.tiers.map((t) => ({ value: t.level, label: t.name })),
]);

const queryParams = (extra = {}) => ({
    search: search.value || undefined,
    scope: search.value && scope.value !== 'basic' ? scope.value : undefined,
    sort: sort.value !== 'newest' ? sort.value : undefined,
    tier: tier.value !== ALL_TIERS ? tier.value : undefined,
    ...extra,
});

const visit = (extra = {}) =>
    router.get(route('customers.index'), queryParams(extra), { preserveState: true, preserveScroll: true, replace: true });

let searchTimer = null;
watch(search, () => {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(() => visit(), 300);
});
watch([sort, tier], () => visit());
// Đổi phạm vi tìm: chỉ tải lại khi đang có từ khóa
watch(scope, () => search.value && visit());

const onPage = (e) => visit({ page: e.page + 1 });
const first = computed(() => (props.customers.current_page - 1) * props.customers.per_page);

/* ---------- Menu Excel ---------- */
const excelMenu = ref();
const importOpen = ref(false);
const excelItems = computed(() => [
    { label: 'Nhập từ Excel', icon: 'pi pi-upload', visible: can('customers.create'), command: () => (importOpen.value = true) },
    {
        label: 'Xuất Excel',
        icon: 'pi pi-download',
        visible: can('customers.update'),
        command: () => (window.location.href = route('customers.export', queryParams())),
    },
    { label: 'Tải file mẫu', icon: 'pi pi-file', visible: can('customers.create'), url: route('customers.template') },
]);
const showExcel = computed(() => can('customers.create') || can('customers.update'));

/* ---------- Sinh nhật ---------- */
const birthdayText = (c) => {
    const n = c.birthday_in;
    if (n === null || n === undefined || n > 7) return '';
    return n === 0 ? 'Hôm nay sinh nhật' : n === 1 ? 'Ngày mai sinh nhật' : `Sinh nhật sau ${n} ngày`;
};

/* ---------- Form ---------- */
const dialog = ref(false);
const editing = ref(null);
const yearOnly = ref(false); // true: chỉ chọn năm sinh
const today = new Date();

const form = useForm({
    name: '',
    phone: '',
    address: '',
    occupation: '',
    note: '',
    birth: null, // Date (hoặc 1/1 của năm khi chỉ chọn năm)
    avatar_file: null,
    avatar_preset: null,
    remove_avatar: false,
});

/* ---------- Ngày sinh ---------- */
const pad = (n) => String(n).padStart(2, '0');
const toYmd = (d) => `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`;
const parseYmd = (s) => {
    const [y, m, d] = s.split('-').map(Number);
    return new Date(y, m - 1, d);
};
const formatBirth = (c) => {
    if (c.birth_date) {
        const [y, m, d] = c.birth_date.split('-');
        return `${d}/${m}/${y}`;
    }
    return c.birth_year ? String(c.birth_year) : '—';
};

// Đổi giữa "đủ ngày" và "chỉ năm" thì giữ lại năm đã chọn
watch(yearOnly, () => {
    if (form.birth) form.birth = new Date(form.birth.getFullYear(), 0, 1);
});

/* ---------- Gợi ý nghề nghiệp ---------- */
const DEFAULT_OCCUPATIONS = [
    'Giáo viên', 'Giảng viên', 'Học sinh', 'Sinh viên', 'Nhân viên văn phòng', 'Công nhân', 'Kỹ sư',
    'Bác sĩ', 'Y tá / Điều dưỡng', 'Dược sĩ', 'Kế toán', 'Luật sư', 'Lập trình viên', 'Thiết kế',
    'Nhân viên bán hàng', 'Nhân viên ngân hàng', 'Buôn bán / Kinh doanh', 'Kinh doanh online', 'Tiểu thương',
    'Chủ cửa hàng', 'Chủ doanh nghiệp', 'Nội trợ', 'Nông dân', 'Tài xế', 'Tài xế công nghệ', 'Shipper',
    'Thợ xây', 'Thợ điện', 'Thợ may', 'Đầu bếp', 'Bảo vệ', 'Công an', 'Bộ đội', 'Cán bộ / Công chức',
    'Viên chức', 'Nhà báo', 'Nghệ sĩ', 'Lao động tự do', 'Freelancer', 'Hưu trí',
];

// bỏ dấu, viết thường: "Giáo" -> "giao"
const plain = (s) =>
    String(s ?? '')
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .replace(/[đĐ]/g, 'd')
        .toLowerCase();

// Nghề đã dùng trong cửa hàng xếp trước, rồi tới danh sách mặc định
const occupationPool = computed(() => {
    const seen = new Set();
    return [...props.occupations, ...DEFAULT_OCCUPATIONS].filter((o) => {
        const k = plain(o);
        if (!k || seen.has(k)) return false;
        seen.add(k);
        return true;
    });
});

const occupationHints = ref([]);
function suggestOccupation(e) {
    const q = plain(e.query).trim();
    if (!q) {
        occupationHints.value = occupationPool.value;
        return;
    }
    const scored = [];
    for (const o of occupationPool.value) {
        const p = plain(o);
        const score = p.startsWith(q) ? 0 : p.split(/[\s/]+/).some((w) => w.startsWith(q)) ? 1 : q.length >= 2 && p.includes(q) ? 2 : -1;
        if (score >= 0) scored.push([score, o]);
    }
    occupationHints.value = scored.sort((a, b) => a[0] - b[0]).map((x) => x[1]).slice(0, 8);
}

/* ---------- Kiểm tra trùng số điện thoại ---------- */
const phoneCheck = useUniqueCheck('phone', () => form.phone, {
    ignoreId: () => editing.value?.id ?? null,
    original: () => editing.value?.phone,
    url: () => route('customers.check'),
});

watch(() => form.name, () => form.clearErrors('name'));
watch(() => form.phone, () => form.clearErrors('phone'));
watch(() => form.birth, () => form.clearErrors('birth_date', 'birth_year'));

const phoneInvalid = computed(() => ['taken', 'invalid'].includes(phoneCheck.status));

/* ---------- Thêm / sửa / xóa ---------- */
function openCreate() {
    editing.value = null;
    form.reset();
    form.clearErrors();
    yearOnly.value = false;
    phoneCheck.reset();
    dialog.value = true;
}

function openEdit(c) {
    detailOpen.value = false;
    editing.value = c;
    form.clearErrors();
    form.name = c.name;
    form.phone = c.phone ?? '';
    form.address = c.address ?? '';
    form.occupation = c.occupation ?? '';
    form.note = c.note ?? '';
    form.avatar_file = null;
    form.avatar_preset = null;
    form.remove_avatar = false;
    if (c.birth_date) {
        yearOnly.value = false;
        form.birth = parseYmd(c.birth_date);
    } else if (c.birth_year) {
        yearOnly.value = true;
        form.birth = new Date(c.birth_year, 0, 1);
    } else {
        yearOnly.value = false;
        form.birth = null;
    }
    phoneCheck.reset();
    dialog.value = true;
}

function submit() {
    if (phoneInvalid.value) return;

    // Có thể kèm file ảnh nên gửi dạng multipart; khi sửa dùng _method=put
    form.transform((d) => ({
        name: d.name,
        phone: d.phone,
        address: d.address,
        occupation: d.occupation,
        note: d.note,
        birth_date: d.birth && !yearOnly.value ? toYmd(d.birth) : null,
        birth_year: d.birth && yearOnly.value ? d.birth.getFullYear() : null,
        avatar_file: d.avatar_file,
        avatar_preset: d.avatar_preset,
        remove_avatar: d.remove_avatar,
        ...(editing.value ? { _method: 'put' } : {}),
    }));

    const options = {
        preserveScroll: true,
        forceFormData: true,
        onSuccess: () => {
            dialog.value = false;
            form.reset();
        },
    };
    if (editing.value) {
        form.post(route('customers.update', editing.value.id), options);
    } else {
        form.post(route('customers.store'), options);
    }
}

function remove(c) {
    confirm.require({
        header: 'Xóa khách hàng',
        message: `Xóa khách hàng "${c.name}"? Thao tác này không thể hoàn tác.`,
        icon: 'pi pi-exclamation-triangle',
        rejectLabel: 'Hủy',
        acceptLabel: 'Xóa',
        rejectProps: { severity: 'secondary', outlined: true },
        acceptProps: { severity: 'danger' },
        accept: () => {
            detailOpen.value = false;
            router.delete(route('customers.destroy', c.id), { preserveScroll: true });
        },
    });
}

/* ---------- Xem chi tiết ---------- */
const detailOpen = ref(false);
const detailId = ref(null);
const detailCustomer = computed(() => props.customers.data.find((c) => c.id === detailId.value) ?? null);
function openDetail(c) {
    detailId.value = c.id;
    detailOpen.value = true;
}
</script>

<template>
    <Head title="Khách hàng" />

    <AppLayout title="Khách hàng">
        <div class="al-panel al-fill">
            <div class="al-toolbar">
                <div class="cu-searchbar">
                    <IconField class="al-search">
                        <InputIcon class="pi pi-search" />
                        <InputText v-model="search" :placeholder="searchPlaceholder" aria-label="Tìm khách hàng" fluid />
                    </IconField>
                    <SelectButton
                        v-model="scope"
                        :options="scopeOptions"
                        optionValue="value"
                        :allowEmpty="false"
                        size="small"
                        aria-label="Phạm vi tìm kiếm"
                    >
                        <template #option="{ option }">
                            <i :class="option.icon" aria-hidden="true" />
                            <span>{{ option.label }}</span>
                        </template>
                    </SelectButton>
                </div>
                <Select
                    v-model="sort"
                    :options="sortOptions"
                    optionLabel="label"
                    optionValue="value"
                    aria-label="Sắp xếp khách hàng"
                    style="min-width: 12.5rem"
                />
                <Select
                    v-if="tiersEnabled"
                    v-model="tier"
                    :options="tierOptions"
                    optionLabel="label"
                    optionValue="value"
                    aria-label="Lọc theo hạng"
                    style="min-width: 9.5rem"
                />
                <SelectButton
                    v-if="isDesktop"
                    v-model="view"
                    :options="viewOptions"
                    optionValue="value"
                    :allowEmpty="false"
                    aria-label="Kiểu hiển thị"
                >
                    <template #option="{ option }">
                        <i :class="option.icon" aria-hidden="true" :title="option.label" />
                        <span class="sr-only">{{ option.label }}</span>
                    </template>
                </SelectButton>
                <template v-if="showExcel">
                    <Button
                        label="Excel"
                        icon="pi pi-file-excel"
                        severity="secondary"
                        outlined
                        aria-haspopup="true"
                        @click="(e) => excelMenu.toggle(e)"
                    />
                    <Menu ref="excelMenu" :model="excelItems" popup />
                </template>
                <Button v-if="can('customers.create')" label="Thêm khách hàng" icon="pi pi-plus" @click="openCreate" />
            </div>

            <!-- Dạng bảng (chỉ trên máy tính) -->
            <DataTable
                v-if="showTable"
                :value="customers.data"
                dataKey="id"
                lazy
                paginator
                :rows="customers.per_page"
                :first="first"
                :totalRecords="customers.total"
                @page="onPage"
            >
                <template #empty>Chưa có khách hàng nào khớp với tìm kiếm.</template>

                <Column field="code" header="Mã" style="width: 8rem" />
                <Column field="name" header="Họ tên">
                    <template #body="{ data }">
                        <div class="user-cell">
                            <UserAvatar :name="data.name" :url="data.avatar_url" />
                            <button type="button" class="cu-link" @click="openDetail(data)">{{ data.name }}</button>
                        </div>
                    </template>
                </Column>
                <Column v-if="tiersEnabled" header="Hạng" style="width: 9rem">
                    <template #body="{ data }"><TierBadge :tier="data.tier" /></template>
                </Column>
                <Column field="phone" header="Số điện thoại">
                    <template #body="{ data }">{{ data.phone ? formatPhone(data.phone) : '—' }}</template>
                </Column>
                <Column header="Ngày sinh">
                    <template #body="{ data }">
                        {{ formatBirth(data) }}
                        <i v-if="birthdayText(data)" class="pi pi-gift cu-gift" :title="birthdayText(data)" :aria-label="birthdayText(data)" />
                    </template>
                </Column>
                <Column field="occupation" header="Nghề nghiệp">
                    <template #body="{ data }">{{ data.occupation || '—' }}</template>
                </Column>
                <Column field="address" header="Địa chỉ">
                    <template #body="{ data }">{{ data.address || '—' }}</template>
                </Column>
                <Column header="Chi tiêu" headerClass="cu-col-right" bodyClass="cu-col-right">
                    <template #body="{ data }">{{ money(data.total_spent) }}</template>
                </Column>
                <Column header="" style="width: 7rem">
                    <template #body="{ data }">
                        <div style="display: flex; gap: 0.25rem; justify-content: flex-end">
                            <Button
                                v-if="can('customers.update')"
                                icon="pi pi-pencil"
                                text
                                rounded
                                severity="secondary"
                                aria-label="Sửa"
                                @click="openEdit(data)"
                            />
                            <Button
                                v-if="can('customers.delete')"
                                icon="pi pi-trash"
                                text
                                rounded
                                severity="danger"
                                aria-label="Xóa"
                                @click="remove(data)"
                            />
                        </div>
                    </template>
                </Column>
            </DataTable>

            <!-- Dạng thẻ (máy tính chọn Thẻ, máy tính bảng và điện thoại luôn dùng) -->
            <template v-else>
                <div v-if="customers.data.length" class="cu-grid">
                    <article v-for="c in customers.data" :key="c.id" class="cu-card" @click="openDetail(c)">
                        <header class="cu-top">
                            <div class="cu-tags">
                                <span class="cu-code">{{ c.code }}</span>
                            </div>
                            <div class="cu-actions">
                                <Button
                                    v-if="can('customers.update')"
                                    icon="pi pi-pencil"
                                    text
                                    rounded
                                    size="small"
                                    severity="secondary"
                                    :aria-label="`Sửa ${c.name}`"
                                    @click.stop="openEdit(c)"
                                />
                                <Button
                                    v-if="can('customers.delete')"
                                    icon="pi pi-trash"
                                    text
                                    rounded
                                    size="small"
                                    severity="danger"
                                    :aria-label="`Xóa ${c.name}`"
                                    @click.stop="remove(c)"
                                />
                            </div>
                        </header>

                        <div class="cu-main">
                            <div class="cu-who">
                                <UserAvatar :name="c.name" :url="c.avatar_url" size="xlarge" />
                                <TierBadge v-if="tiersEnabled" :tier="c.tier" />
                            </div>
                            <div class="cu-lines">
                                <h3 class="cu-name">
                                    <button type="button" class="cu-link" @click.stop="openDetail(c)">{{ c.name }}</button>
                                </h3>
                                <span v-if="birthdayText(c)" class="cu-bday"><i class="pi pi-gift" aria-hidden="true" />{{ birthdayText(c) }}</span>
                                <p class="cu-line">
                                    <i class="pi pi-phone" aria-hidden="true" />
                                    <span class="sr-only">Điện thoại:</span>
                                    <a v-if="c.phone" :href="`tel:${c.phone}`" @click.stop>{{ formatPhone(c.phone) }}</a>
                                    <span v-else class="cu-empty">Chưa có số điện thoại</span>
                                </p>
                                <p class="cu-line">
                                    <i class="pi pi-calendar" aria-hidden="true" />
                                    <span class="sr-only">Ngày sinh:</span>
                                    <span v-if="c.birth_date || c.birth_year">{{ formatBirth(c) }}</span>
                                    <span v-else class="cu-empty">Chưa có ngày sinh</span>
                                </p>
                                <p v-if="c.occupation" class="cu-line">
                                    <i class="pi pi-briefcase" aria-hidden="true" />
                                    <span class="sr-only">Nghề nghiệp:</span>
                                    <span>{{ c.occupation }}</span>
                                </p>
                                <p v-if="c.address" class="cu-line">
                                    <i class="pi pi-map-marker" aria-hidden="true" />
                                    <span class="sr-only">Địa chỉ:</span>
                                    <span>{{ c.address }}</span>
                                </p>
                            </div>
                        </div>

                        <p v-if="c.note" class="cu-note">
                            <i class="pi pi-comment" aria-hidden="true" />
                            <span class="sr-only">Ghi chú:</span>
                            <span>{{ c.note }}</span>
                        </p>

                        <footer class="cu-spent">
                            <span>Tổng chi tiêu</span>
                            <strong>{{ money(c.total_spent) }}</strong>
                        </footer>
                    </article>
                </div>
                <p v-else class="dash-empty">Chưa có khách hàng nào khớp với tìm kiếm.</p>

                <Paginator
                    :first="first"
                    :rows="customers.per_page"
                    :totalRecords="customers.total"
                    @page="onPage"
                />
            </template>
        </div>

        <!-- Xem nhanh chi tiết khách -->
        <CustomerDetail
            v-model:visible="detailOpen"
            :customer="detailCustomer"
            :canEdit="can('customers.update')"
            :canDelete="can('customers.delete')"
            @edit="openEdit"
            @remove="remove"
        />

        <!-- Nhập từ Excel + kết quả -->
        <ImportDialog v-model:visible="importOpen" :result="importResult" />

        <!-- Thêm / sửa -->
        <AppDialog
            v-model:visible="dialog"
            :header="editing ? 'Sửa khách hàng' : 'Thêm khách hàng'"
            :style="{ width: '34rem' }"
            :breakpoints="{ '640px': '94vw' }"
        >
            <form novalidate @submit.prevent="submit">
                <AvatarPicker
                    v-model:file="form.avatar_file"
                    v-model:preset="form.avatar_preset"
                    v-model:remove="form.remove_avatar"
                    :name="form.name"
                    :currentUrl="editing?.avatar_url ?? null"
                    :presets="avatarPresets"
                />
                <small v-if="form.errors.avatar_file" class="field-error" style="display: block; margin: -0.5rem 0 1rem">{{ form.errors.avatar_file }}</small>

                <div class="field">
                    <label for="c-name">Họ tên <span class="req" aria-hidden="true">*</span></label>
                    <InputText id="c-name" v-model="form.name" autocomplete="off" aria-required="true" fluid :invalid="!!form.errors.name" />
                    <small v-if="form.errors.name" class="field-error">{{ form.errors.name }}</small>
                </div>

                <div class="field">
                    <label for="c-phone">Số điện thoại</label>
                    <PhoneInput
                        id="c-phone"
                        v-model="form.phone"
                        :invalid="!!form.errors.phone || phoneInvalid"
                        @blur="phoneCheck.check()"
                    />
                    <small v-if="form.errors.phone" class="field-error">{{ form.errors.phone }}</small>
                    <FieldStatus v-else :status="phoneCheck.status" :message="phoneCheck.message" />
                    <small v-if="!form.errors.phone && phoneCheck.status === 'idle'" class="field-hint">Gồm 10 số.</small>
                </div>

                <div class="field">
                    <label for="c-birth">Ngày sinh</label>
                    <DatePicker
                        :key="yearOnly ? 'year' : 'date'"
                        v-model="form.birth"
                        inputId="c-birth"
                        :view="yearOnly ? 'year' : 'date'"
                        :dateFormat="yearOnly ? 'yy' : 'dd/mm/yy'"
                        :placeholder="yearOnly ? 'Chọn năm sinh' : 'dd/mm/yyyy'"
                        :maxDate="today"
                        showIcon
                        showButtonBar
                        fluid
                        :invalid="!!(form.errors.birth_date || form.errors.birth_year)"
                    />
                    <div class="cu-toggle">
                        <Checkbox v-model="yearOnly" binary inputId="c-yearonly" />
                        <label for="c-yearonly">Chỉ biết năm sinh</label>
                    </div>
                    <small v-if="form.errors.birth_date || form.errors.birth_year" class="field-error">
                        {{ form.errors.birth_date || form.errors.birth_year }}
                    </small>
                </div>

                <div class="field">
                    <label for="c-job">Nghề nghiệp</label>
                    <AutoComplete
                        v-model="form.occupation"
                        inputId="c-job"
                        :suggestions="occupationHints"
                        dropdown
                        placeholder="Gõ để gợi ý, ví dụ: G"
                        autocomplete="off"
                        fluid
                        @complete="suggestOccupation"
                    />
                </div>

                <div class="field">
                    <label for="c-addr">Địa chỉ</label>
                    <InputText id="c-addr" v-model="form.address" autocomplete="off" fluid />
                </div>

                <div class="field">
                    <label for="c-note">Ghi chú</label>
                    <Textarea id="c-note" v-model="form.note" rows="3" autoResize fluid />
                </div>

                <div v-if="editing" class="field">
                    <label for="c-spent">Chi tiêu</label>
                    <div id="c-spent" class="pf-readonly">
                        <i class="pi pi-wallet" aria-hidden="true" />
                        <span>{{ money(editing.total_spent) }}</span>
                        <TierBadge v-if="tiersEnabled" :tier="editing.tier" />
                    </div>
                    <small class="field-hint">Tự cộng dồn từ các đơn hàng đã thanh toán, không sửa tay.</small>
                </div>

                <div class="dialog-actions">
                    <Button type="button" label="Hủy" severity="secondary" outlined @click="dialog = false" />
                    <Button
                        type="submit"
                        :label="editing ? 'Lưu thay đổi' : 'Thêm khách hàng'"
                        :loading="form.processing"
                        :disabled="phoneInvalid"
                    />
                </div>
            </form>
        </AppDialog>
    </AppLayout>
</template>

<style>
.req { margin-left: 0.15rem; color: #dc2626; font-weight: 700; }
.cu-toggle { display: flex; align-items: center; gap: 0.7rem; margin-top: 0.5rem; }
.cu-toggle label { font-weight: 400; cursor: pointer; }
.cu-link { padding: 0; border: 0; background: none; color: inherit; font: inherit; font-weight: inherit; text-align: left; cursor: pointer; }
.cu-link:hover { color: var(--al-primary); text-decoration: underline; }
.cu-link:focus-visible { outline: 2px solid var(--al-primary); outline-offset: 2px; border-radius: 0.3rem; }
.cu-gift { margin-left: 0.35rem; color: #db2777; font-size: 0.85rem; }
.cu-bday { display: inline-flex; align-items: center; gap: 0.35rem; align-self: flex-start; padding: 0.12rem 0.6rem; border-radius: 999px; background: color-mix(in srgb, #db2777 12%, transparent); color: #be185d; font-size: 0.76rem; font-weight: 600; }
.cu-bday i { font-size: 0.7rem; }
.cu-empty { color: var(--al-muted); font-style: italic; }

.cu-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(17.5rem, 1fr)); gap: 1rem; padding: 1rem; }
.cu-card {
    display: flex; flex-direction: column; gap: 0.75rem; padding: 0.85rem 1rem 1rem;
    border: 1px solid var(--al-glass-border); border-radius: 16px;
    background: rgba(255, 255, 255, 0.72);
    box-shadow: 0 2px 10px rgba(28, 58, 68, 0.06);
    cursor: pointer;
    transition: transform 0.15s ease, box-shadow 0.15s ease, background 0.15s ease;
}
.cu-card:hover { transform: translateY(-2px); background: rgba(255, 255, 255, 0.92); box-shadow: var(--al-shadow); }

.cu-top { display: flex; align-items: center; justify-content: space-between; gap: 0.5rem; min-height: 2rem; }
.cu-tags { display: flex; flex-wrap: wrap; align-items: center; gap: 0.35rem; min-width: 0; }
.cu-code { padding: 0.15rem 0.6rem; border-radius: 999px; background: var(--al-primary-soft); color: var(--al-primary); font-size: 0.78rem; font-weight: 600; letter-spacing: 0.02em; }
.cu-actions { display: flex; flex: none; gap: 0.1rem; margin-right: -0.35rem; }

.cu-main { display: flex; align-items: flex-start; gap: 0.9rem; }
.cu-card .p-avatar {
    flex: none; width: 4.25rem !important; height: 4.25rem !important;
    box-shadow: 0 6px 14px color-mix(in srgb, var(--p-primary-700) 24%, transparent);
}
.cu-card .al-avatar {
    font-size: 1.5rem !important;
    background: linear-gradient(135deg, var(--p-primary-400), var(--p-primary-700)) !important; color: #fff !important;
}
.cu-who { flex: none; display: flex; flex-direction: column; align-items: center; gap: 0.45rem; max-width: 6.5rem; }
.cu-who .tier-badge { max-width: 100%; }
.cu-searchbar { display: flex; flex: 1 1 20rem; flex-wrap: wrap; align-items: center; gap: 0.5rem; min-width: 0; }
.cu-searchbar .al-search { flex: 1 1 14rem; min-width: 0; }
.cu-searchbar .p-togglebutton { white-space: nowrap; }
.cu-searchbar .p-togglebutton i { margin-right: 0.35rem; }
/* Cột số tiền: tiêu đề và nội dung cùng căn phải */
.cu-col-right { text-align: right !important; }
.cu-col-right .p-datatable-column-header-content { justify-content: flex-end; }
.cu-lines { flex: 1; min-width: 0; display: flex; flex-direction: column; gap: 0.3rem; }
.cu-name { margin: 0 0 0.1rem; font-size: 1.05rem; font-weight: 700; line-height: 1.25; letter-spacing: -0.01em; overflow-wrap: anywhere; }
.cu-line { display: flex; align-items: flex-start; gap: 0.5rem; margin: 0; font-size: 0.88rem; line-height: 1.35; overflow-wrap: anywhere; }
.cu-line i { flex: none; width: 1rem; margin-top: 0.15rem; color: var(--p-primary-600); font-size: 0.85rem; text-align: center; }
.cu-line a { color: inherit; font-weight: 500; text-decoration: none; }
.cu-line a:hover { color: var(--al-primary); text-decoration: underline; }

.cu-note { display: flex; gap: 0.5rem; margin: 0; padding: 0.55rem 0.75rem; border-radius: 0.8rem; background: rgba(255, 255, 255, 0.7); color: var(--al-muted); font-size: 0.84rem; line-height: 1.4; }
.cu-note i { flex: none; margin-top: 0.15rem; font-size: 0.8rem; }
.cu-note span:last-child { display: -webkit-box; overflow: hidden; overflow-wrap: anywhere; -webkit-line-clamp: 2; -webkit-box-orient: vertical; }

.cu-spent { display: flex; align-items: center; justify-content: space-between; gap: 0.5rem; margin-top: auto; padding: 0.55rem 0.8rem; border-radius: 0.8rem; background: color-mix(in srgb, #16a34a 11%, transparent); }
.cu-spent span { color: var(--al-muted); font-size: 0.84rem; }
.cu-spent strong { color: #15803d; font-size: 1rem; font-weight: 700; font-variant-numeric: tabular-nums; }

@media (max-width: 639px) {
    .cu-grid { grid-template-columns: 1fr; padding: 0.75rem; gap: 0.75rem; }
}
@media (prefers-reduced-motion: reduce) {
    .cu-card { transition: none; }
    .cu-card:hover { transform: none; }
}
</style>
