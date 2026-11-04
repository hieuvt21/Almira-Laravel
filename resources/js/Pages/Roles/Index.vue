<script setup>
import { computed, ref } from 'vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import Button from 'primevue/button';
import Checkbox from 'primevue/checkbox';
import Column from 'primevue/column';
import DataTable from 'primevue/datatable';
import InputText from 'primevue/inputtext';
import Tag from 'primevue/tag';
import { useConfirm } from 'primevue/useconfirm';
import AppDialog from '@/Components/AppDialog.vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import { groupPermissions, roleLabel, roleSeverity } from '@/utils/labels';

const props = defineProps({
    roles: { type: Array, default: () => [] },
    permissions: { type: Array, default: () => [] },
});

const confirm = useConfirm();
const dialog = ref(false);
const editing = ref(null);

const form = useForm({ name: '', permissions: [] });
const groups = computed(() => groupPermissions(props.permissions));

function openCreate() {
    editing.value = null;
    form.reset();
    form.clearErrors();
    dialog.value = true;
}

function openEdit(role) {
    editing.value = role;
    form.clearErrors();
    form.name = role.name;
    form.permissions = [...role.permissions];
    dialog.value = true;
}

function groupAllChecked(group) {
    return group.items.every((i) => form.permissions.includes(i.name));
}

function toggleGroup(group) {
    const names = group.items.map((i) => i.name);
    if (groupAllChecked(group)) {
        form.permissions = form.permissions.filter((p) => !names.includes(p));
    } else {
        form.permissions = [...new Set([...form.permissions, ...names])];
    }
}

function submit() {
    const options = {
        preserveScroll: true,
        onSuccess: () => {
            dialog.value = false;
            form.reset();
        },
    };
    if (editing.value) {
        form.put(route('roles.update', editing.value.id), options);
    } else {
        form.post(route('roles.store'), options);
    }
}

function remove(role) {
    confirm.require({
        header: 'Xóa vai trò',
        message: `Xóa vai trò "${roleLabel(role.name)}"?`,
        icon: 'pi pi-exclamation-triangle',
        rejectLabel: 'Hủy',
        acceptLabel: 'Xóa',
        rejectProps: { severity: 'secondary', outlined: true },
        acceptProps: { severity: 'danger' },
        accept: () => router.delete(route('roles.destroy', role.id), { preserveScroll: true }),
    });
}

const deleteHint = (role) =>
    role.system
        ? 'Vai trò mặc định không thể xóa'
        : role.users_count > 0
          ? 'Vai trò đang có người dùng'
          : 'Xóa';
</script>

<template>
    <Head title="Vai trò & quyền" />

    <AppLayout title="Vai trò & quyền">
        <div class="al-panel al-fill">
            <div class="al-toolbar">
                <p class="al-toolbar-note">
                    Quản trị viên luôn có toàn quyền và không thể chỉnh sửa.
                </p>
                <Button label="Thêm vai trò" icon="pi pi-plus" @click="openCreate" />
            </div>

            <DataTable :value="roles" dataKey="id">
                <Column header="Vai trò">
                    <template #body="{ data }">
                        <div style="display: flex; align-items: center; gap: 0.5rem">
                            <Tag :value="roleLabel(data.name)" :severity="roleSeverity(data.name)" />
                            <small v-if="data.system" class="field-hint">Mặc định</small>
                        </div>
                    </template>
                </Column>
                <Column field="users_count" header="Người dùng" style="width: 8rem" />
                <Column header="Quyền">
                    <template #body="{ data }">
                        {{ data.locked ? 'Toàn quyền' : `${data.permissions.length} quyền` }}
                    </template>
                </Column>
                <Column header="" style="width: 7rem">
                    <template #body="{ data }">
                        <div v-if="!data.locked" style="display: flex; gap: 0.25rem; justify-content: flex-end">
                            <Button
                                icon="pi pi-pencil"
                                text
                                rounded
                                severity="secondary"
                                aria-label="Sửa quyền"
                                @click="openEdit(data)"
                            />
                            <Button
                                icon="pi pi-trash"
                                text
                                rounded
                                severity="danger"
                                :aria-label="deleteHint(data)"
                                :title="deleteHint(data)"
                                :disabled="data.system || data.users_count > 0"
                                @click="remove(data)"
                            />
                        </div>
                    </template>
                </Column>
            </DataTable>
        </div>

        <AppDialog
            v-model:visible="dialog"
            modal
            :header="editing ? `Sửa vai trò: ${roleLabel(editing.name)}` : 'Thêm vai trò'"
            :style="{ width: '44rem' }"
            :breakpoints="{ '768px': '94vw' }"
        >
            <form @submit.prevent="submit">
                <div class="field">
                    <label for="r-name">Tên vai trò</label>
                    <InputText
                        id="r-name"
                        v-model="form.name"
                        :disabled="editing && editing.system"
                        autocomplete="off"
                        fluid
                    />
                    <small v-if="editing && editing.system" class="field-hint">
                        Tên vai trò mặc định không đổi được.
                    </small>
                    <small v-if="form.errors.name" class="field-error">{{ form.errors.name }}</small>
                </div>

                <p class="field-hint" style="margin: 0 0 0.5rem">Quyền của vai trò</p>
                <div class="perm-list">
                    <div v-for="group in groups" :key="group.key" class="perm-row">
                        <div class="perm-head">
                            <strong>{{ group.label }}</strong>
                            <Button
                                type="button"
                                :label="groupAllChecked(group) ? 'Bỏ chọn' : 'Chọn hết'"
                                text
                                size="small"
                                @click="toggleGroup(group)"
                            />
                        </div>
                        <div class="perm-items">
                            <label v-for="p in group.items" :key="p.name" class="perm-item">
                                <Checkbox v-model="form.permissions" :value="p.name" />
                                <span>{{ p.label }}</span>
                            </label>
                        </div>
                    </div>
                </div>
                <small v-if="form.errors.permissions" class="field-error">{{ form.errors.permissions }}</small>

                <div class="dialog-actions">
                    <Button type="button" label="Hủy" severity="secondary" outlined @click="dialog = false" />
                    <Button
                        type="submit"
                        :label="editing ? 'Lưu thay đổi' : 'Thêm vai trò'"
                        :loading="form.processing"
                    />
                </div>
            </form>
        </AppDialog>
    </AppLayout>
</template>

<style>
.perm-list { max-height: 55vh; overflow-y: auto; border: 1px solid var(--al-line); border-radius: 0.6rem; }
.perm-row { padding: 0.6rem 0.9rem; }
.perm-row + .perm-row { border-top: 1px solid var(--al-line); }
.perm-head { display: flex; align-items: center; justify-content: space-between; margin-bottom: 0.25rem; }
.perm-items { display: flex; flex-wrap: wrap; gap: 0.5rem 1.25rem; }
.perm-item { display: flex; align-items: center; gap: 0.45rem; font-size: 0.9rem; cursor: pointer; }
</style>
