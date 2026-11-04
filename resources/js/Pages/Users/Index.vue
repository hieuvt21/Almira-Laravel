<script setup>
import { computed, ref, watch } from 'vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import Button from 'primevue/button';
import Column from 'primevue/column';
import DataTable from 'primevue/datatable';
import IconField from 'primevue/iconfield';
import InputIcon from 'primevue/inputicon';
import InputText from 'primevue/inputtext';
import Password from 'primevue/password';
import Select from 'primevue/select';
import Tag from 'primevue/tag';
import { useConfirm } from 'primevue/useconfirm';
import AppDialog from '@/Components/AppDialog.vue';
import AvatarPicker from '@/Components/AvatarPicker.vue';
import FieldStatus from '@/Components/FieldStatus.vue';
import PhoneInput from '@/Components/PhoneInput.vue';
import UserAvatar from '@/Components/UserAvatar.vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import { usePermission } from '@/composables/usePermission';
import { useUniqueCheck } from '@/composables/useUniqueCheck';
import { formatPhone } from '@/utils/phone';
import { roleLabel, roleSeverity } from '@/utils/labels';

const props = defineProps({
    users: { type: Array, default: () => [] },
    roles: { type: Array, default: () => [] },
    avatarPresets: { type: Array, default: () => [] },
});

const { can, isAdmin, user: me } = usePermission();
const confirm = useConfirm();

const filters = ref({ global: { value: null, matchMode: 'contains' } });
const dialog = ref(false);
const editing = ref(null);

const form = useForm({
    name: '',
    username: '',
    phone: '',
    email: '',
    password: '',
    role: null,
    avatar_file: null,
    avatar_preset: null,
    remove_avatar: false,
});

// Kiểm tra trùng ngay khi nhập (khi sửa: bỏ qua chính người đang sửa)
const ignoreId = () => editing.value?.id ?? null;
const usernameCheck = useUniqueCheck('username', () => form.username, { ignoreId, original: () => editing.value?.username });
const phoneCheck = useUniqueCheck('phone', () => form.phone, { ignoreId, original: () => editing.value?.phone });
const emailCheck = useUniqueCheck('email', () => form.email, { ignoreId, original: () => editing.value?.email });

// Sửa lại ô nào thì xóa lỗi cũ của ô đó (lỗi từ lần bấm lưu trước)
['username', 'phone', 'email', 'name', 'password', 'role'].forEach((f) =>
    watch(() => form[f], () => form.clearErrors(f)),
);

const hasConflict = computed(() =>
    [usernameCheck, phoneCheck, emailCheck].some((c) => c.status === 'taken' || c.status === 'invalid'),
);

// Chỉ quản trị viên mới được gán/sửa vai trò admin
const roleOptions = computed(() =>
    props.roles
        .filter((r) => isAdmin.value || r !== 'admin')
        .map((r) => ({ label: roleLabel(r), value: r })),
);
const canTouch = (u) => isAdmin.value || u.role !== 'admin';
const isSelf = (u) => u.id === me.value?.id;

function resetChecks() {
    [usernameCheck, phoneCheck, emailCheck].forEach((c) => c.reset());
}

function openCreate() {
    editing.value = null;
    form.reset();
    form.clearErrors();
    resetChecks();
    dialog.value = true;
}

function openEdit(u) {
    editing.value = u;
    form.clearErrors();
    form.name = u.name;
    form.username = u.username ?? '';
    form.phone = u.phone ?? '';
    form.email = u.email ?? '';
    form.password = '';
    form.role = u.role;
    form.avatar_file = null;
    form.avatar_preset = null;
    form.remove_avatar = false;
    resetChecks();
    dialog.value = true;
}

function submit() {
    if (hasConflict.value) return;

    const options = {
        preserveScroll: true,
        forceFormData: true, // có thể kèm file ảnh
        onSuccess: () => {
            dialog.value = false;
            form.reset();
        },
    };
    // Sửa người dùng gửi dạng multipart nên dùng _method=put
    form.transform((data) => (editing.value ? { ...data, _method: 'put' } : data));

    if (editing.value) {
        form.post(route('users.update', editing.value.id), options);
    } else {
        form.post(route('users.store'), options);
    }
}

function remove(u) {
    confirm.require({
        header: 'Xóa người dùng',
        message: `Xóa tài khoản "${u.name}"? Thao tác này không thể hoàn tác.`,
        icon: 'pi pi-exclamation-triangle',
        rejectLabel: 'Hủy',
        acceptLabel: 'Xóa',
        rejectProps: { severity: 'secondary', outlined: true },
        acceptProps: { severity: 'danger' },
        accept: () => router.delete(route('users.destroy', u.id), { preserveScroll: true }),
    });
}
</script>

<template>
    <Head title="Người dùng" />

    <AppLayout title="Người dùng">
        <div class="al-panel al-fill">
            <!-- Server đã xếp tài khoản admin lên đầu, nên bảng không cho đổi thứ tự cột -->
            <DataTable
                :value="users"
                v-model:filters="filters"
                :globalFilterFields="['name', 'username', 'phone', 'email']"
                dataKey="id"
                paginator
                :rows="15"
                :rowsPerPageOptions="[15, 30, 50]"
            >
                <template #header>
                    <div class="al-toolbar" style="padding: 0; border: 0">
                        <IconField class="al-search">
                            <InputIcon class="pi pi-search" />
                            <InputText
                                v-model="filters.global.value"
                                placeholder="Tìm theo tên, tên đăng nhập, số điện thoại hoặc email"
                                aria-label="Tìm người dùng"
                                fluid
                            />
                        </IconField>
                        <Button
                            v-if="can('users.create')"
                            label="Thêm người dùng"
                            icon="pi pi-plus"
                            @click="openCreate"
                        />
                    </div>
                </template>

                <template #empty>Chưa có người dùng nào khớp với tìm kiếm.</template>

                <Column field="name" header="Họ tên">
                    <template #body="{ data }">
                        <div class="user-cell">
                            <UserAvatar :name="data.name" :url="data.avatar_url" />
                            <span>{{ data.name }}</span>
                        </div>
                    </template>
                </Column>
                <Column field="username" header="Tên đăng nhập" />
                <Column field="phone" header="Số điện thoại">
                    <template #body="{ data }">{{ data.phone ? formatPhone(data.phone) : '—' }}</template>
                </Column>
                <Column field="email" header="Email">
                    <template #body="{ data }">{{ data.email || '—' }}</template>
                </Column>
                <Column field="role" header="Vai trò">
                    <template #body="{ data }">
                        <Tag
                            v-if="data.role"
                            :value="roleLabel(data.role)"
                            :severity="roleSeverity(data.role)"
                        />
                        <span v-else class="field-hint">Chưa có vai trò</span>
                    </template>
                </Column>
                <Column field="created_at" header="Ngày tạo" />
                <Column header="" style="width: 7rem">
                    <template #body="{ data }">
                        <div v-if="canTouch(data)" style="display: flex; gap: 0.25rem; justify-content: flex-end">
                            <Button
                                v-if="can('users.update')"
                                icon="pi pi-pencil"
                                text
                                rounded
                                severity="secondary"
                                aria-label="Sửa"
                                @click="openEdit(data)"
                            />
                            <Button
                                v-if="can('users.delete') && !isSelf(data)"
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
        </div>

        <AppDialog
            v-model:visible="dialog"
            :header="editing ? 'Sửa người dùng' : 'Thêm người dùng'"
            :style="{ width: '32rem' }"
            :breakpoints="{ '640px': '94vw' }"
        >
            <form @submit.prevent="submit" novalidate>
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
                    <label for="u-name">Họ tên</label>
                    <InputText id="u-name" v-model="form.name" autocomplete="off" fluid :invalid="!!form.errors.name" />
                    <small v-if="form.errors.name" class="field-error">{{ form.errors.name }}</small>
                </div>

                <div class="field">
                    <label for="u-username">Tên đăng nhập</label>
                    <InputText
                        id="u-username"
                        :modelValue="form.username"
                        autocomplete="off"
                        autocapitalize="none"
                        spellcheck="false"
                        fluid
                        :invalid="!!form.errors.username || ['taken', 'invalid'].includes(usernameCheck.status)"
                        @update:modelValue="(v) => (form.username = v.toLowerCase().replace(/\s+/g, ''))"
                        @blur="usernameCheck.check()"
                    />
                    <small v-if="form.errors.username" class="field-error">{{ form.errors.username }}</small>
                    <FieldStatus v-else :status="usernameCheck.status" :message="usernameCheck.message" />
                    <small v-if="!form.errors.username && usernameCheck.status === 'idle'" class="field-hint">
                        Chữ thường không dấu, số, dấu chấm, gạch dưới hoặc gạch ngang.
                    </small>
                </div>

                <div class="field">
                    <label for="u-phone">Số điện thoại <span class="field-hint">(không bắt buộc)</span></label>
                    <PhoneInput
                        id="u-phone"
                        v-model="form.phone"
                        :invalid="!!form.errors.phone || ['taken', 'invalid'].includes(phoneCheck.status)"
                        @blur="phoneCheck.check()"
                    />
                    <small v-if="form.errors.phone" class="field-error">{{ form.errors.phone }}</small>
                    <FieldStatus v-else :status="phoneCheck.status" :message="phoneCheck.message" />
                    <small v-if="!form.errors.phone && phoneCheck.status === 'idle'" class="field-hint">
                        Gồm 10 số. Có thể dùng để đăng nhập.
                    </small>
                </div>

                <div class="field">
                    <label for="u-email">Email <span class="field-hint">(không bắt buộc)</span></label>
                    <InputText
                        id="u-email"
                        v-model="form.email"
                        type="email"
                        autocomplete="off"
                        autocapitalize="none"
                        fluid
                        :invalid="!!form.errors.email || ['taken', 'invalid'].includes(emailCheck.status)"
                        @blur="emailCheck.check()"
                    />
                    <small v-if="form.errors.email" class="field-error">{{ form.errors.email }}</small>
                    <FieldStatus v-else :status="emailCheck.status" :message="emailCheck.message" />
                </div>

                <div class="field">
                    <label for="u-password">{{ editing ? 'Mật khẩu mới' : 'Mật khẩu' }}</label>
                    <Password
                        v-model="form.password"
                        inputId="u-password"
                        :feedback="false"
                        toggleMask
                        autocomplete="new-password"
                        fluid
                        :invalid="!!form.errors.password"
                    />
                    <small v-if="form.errors.password" class="field-error">{{ form.errors.password }}</small>
                    <small v-else class="field-hint">
                        {{ editing ? 'Bỏ trống nếu không đổi mật khẩu.' : 'Tối thiểu 8 ký tự.' }}
                    </small>
                </div>

                <div class="field">
                    <label for="u-role">Vai trò</label>
                    <Select
                        v-model="form.role"
                        inputId="u-role"
                        :options="roleOptions"
                        optionLabel="label"
                        optionValue="value"
                        placeholder="Chọn vai trò"
                        :disabled="editing && isSelf(editing)"
                        fluid
                        :invalid="!!form.errors.role"
                    />
                    <small v-if="editing && isSelf(editing)" class="field-hint">
                        Bạn không thể đổi vai trò của chính mình.
                    </small>
                    <small v-if="form.errors.role" class="field-error">{{ form.errors.role }}</small>
                </div>

                <div class="dialog-actions">
                    <Button type="button" label="Hủy" severity="secondary" outlined @click="dialog = false" />
                    <Button
                        type="submit"
                        :label="editing ? 'Lưu thay đổi' : 'Thêm người dùng'"
                        :loading="form.processing"
                        :disabled="hasConflict"
                    />
                </div>
            </form>
        </AppDialog>
    </AppLayout>
</template>
