<script setup>
import { computed, ref } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import Button from 'primevue/button';
import Checkbox from 'primevue/checkbox';
import Column from 'primevue/column';
import DataTable from 'primevue/datatable';
import InputText from 'primevue/inputtext';
import Tag from 'primevue/tag';
import { useConfirm } from 'primevue/useconfirm';
import AppDialog from '@/Components/AppDialog.vue';
import { usePermission } from '@/composables/usePermission';

const props = defineProps({
    type: { type: String, required: true }, // categories | units | price-lists
    items: { type: Array, default: () => [] },
    noun: { type: String, required: true }, // "danh mục", "đơn vị tính", "bảng giá"
    countField: { type: String, required: true }, // categories: products_count, ...
    countLabel: { type: String, default: 'Đang dùng' },
});

const { can } = usePermission();
const confirm = useConfirm();

const dialog = ref(false);
const editing = ref(null);

const form = useForm({ name: '', description: '', is_default: false, is_active: true });

const isUnit = computed(() => props.type === 'units');

const isCategory = computed(() => props.type === 'categories');
const isPriceList = computed(() => props.type === 'price-lists');
const hasDefault = computed(() => isPriceList.value || isUnit.value);

function openCreate() {
    editing.value = null;
    form.reset();
    form.clearErrors();
    dialog.value = true;
}

function openEdit(item) {
    editing.value = item;
    form.clearErrors();
    form.name = item.name;
    form.description = item.description ?? '';
    form.is_default = !!item.is_default;
    form.is_active = !!item.is_active;
    dialog.value = true;
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
        form.put(route('catalog.update', { type: props.type, id: editing.value.id }), options);
    } else {
        form.post(route('catalog.store', { type: props.type }), options);
    }
}

function remove(item) {
    confirm.require({
        header: `Xóa ${props.noun}`,
        message: `Xóa ${props.noun} "${item.name}"? Thao tác này không thể hoàn tác.`,
        icon: 'pi pi-exclamation-triangle',
        rejectLabel: 'Hủy',
        acceptLabel: 'Xóa',
        rejectProps: { severity: 'secondary', outlined: true },
        acceptProps: { severity: 'danger' },
        accept: () =>
            router.delete(route('catalog.destroy', { type: props.type, id: item.id }), { preserveScroll: true }),
    });
}
</script>

<template>
    <DataTable :value="items" dataKey="id">
        <template #header>
            <div class="al-toolbar" style="padding: 0; border: 0">
                <p class="al-toolbar-note">{{ items.length }} {{ noun }}</p>
                <Button v-if="can('products.update')" :label="`Thêm ${noun}`" icon="pi pi-plus" @click="openCreate" />
            </div>
        </template>

        <template #empty>Chưa có {{ noun }} nào.</template>

        <Column field="name" header="Tên">
            <template #body="{ data }">
                {{ data.name }}
                <Tag v-if="data.is_default" value="Mặc định" severity="info" style="margin-left: 0.4rem" />
            </template>
        </Column>
        <Column v-if="isCategory" field="description" header="Mô tả">
            <template #body="{ data }">{{ data.description || '—' }}</template>
        </Column>
        <Column :header="countLabel" style="width: 9rem">
            <template #body="{ data }">{{ data[countField] }}</template>
        </Column>
        <Column header="Trạng thái" style="width: 9rem">
            <template #body="{ data }">
                <Tag :value="data.is_active ? 'Đang dùng' : 'Ngừng dùng'" :severity="data.is_active ? 'success' : 'secondary'" />
            </template>
        </Column>
        <Column header="" style="width: 7rem">
            <template #body="{ data }">
                <div style="display: flex; gap: 0.25rem; justify-content: flex-end">
                    <Button
                        v-if="can('products.update')"
                        icon="pi pi-pencil"
                        text
                        rounded
                        severity="secondary"
                        aria-label="Sửa"
                        @click="openEdit(data)"
                    />
                    <Button
                        v-if="can('products.delete') && !data.is_default"
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

    <AppDialog
        v-model:visible="dialog"
        :header="editing ? `Sửa ${noun}` : `Thêm ${noun}`"
        :style="{ width: '26rem' }"
        :breakpoints="{ '640px': '94vw' }"
    >
        <form @submit.prevent="submit" novalidate>
            <div class="field">
                <label for="c-name">Tên<span class="al-req" aria-hidden="true">*</span></label>
                <InputText id="c-name" v-model="form.name" autocomplete="off" fluid :invalid="!!form.errors.name" />
                <small v-if="form.errors.name" class="field-error">{{ form.errors.name }}</small>
            </div>

            <div v-if="isCategory" class="field">
                <label for="c-desc">Mô tả</label>
                <InputText id="c-desc" v-model="form.description" autocomplete="off" fluid :invalid="!!form.errors.description" />
                <small v-if="form.errors.description" class="field-error">{{ form.errors.description }}</small>
            </div>

            <div v-if="hasDefault" class="field" style="flex-direction: row; align-items: center; gap: 0.5rem">
                <Checkbox v-model="form.is_default" inputId="c-default" binary :disabled="editing?.is_default" />
                <label for="c-default">{{ isUnit ? 'Dùng làm đơn vị mặc định khi thêm sản phẩm' : 'Dùng làm bảng giá mặc định' }}</label>
            </div>

            <div class="field" style="flex-direction: row; align-items: center; gap: 0.5rem">
                <Checkbox v-model="form.is_active" inputId="c-active" binary />
                <label for="c-active">Đang sử dụng</label>
            </div>
            <small v-if="form.errors.is_active" class="field-error">{{ form.errors.is_active }}</small>

            <div class="dialog-actions">
                <Button type="button" label="Hủy" severity="secondary" outlined @click="dialog = false" />
                <Button type="submit" :label="editing ? 'Lưu thay đổi' : `Thêm ${noun}`" :loading="form.processing" />
            </div>
        </form>
    </AppDialog>
</template>
