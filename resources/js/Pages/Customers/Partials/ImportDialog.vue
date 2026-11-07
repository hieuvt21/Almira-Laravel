<script setup>
import { ref, watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
import Button from 'primevue/button';
import AppDialog from '@/Components/AppDialog.vue';

const props = defineProps({
    result: { type: Object, default: null }, // { created, skipped, errors: [{row, message}], more_errors }
});

const visible = defineModel('visible', { type: Boolean, default: false });
const resultOpen = ref(false);

watch(() => props.result, (r) => { if (r) resultOpen.value = true; }, { immediate: true });

const form = useForm({ file: null });
const input = ref();
const error = ref('');

watch(visible, (v) => {
    if (v) {
        form.reset();
        form.clearErrors();
        error.value = '';
    }
});

function pick(e) {
    const f = e.target.files?.[0];
    e.target.value = '';
    if (!f) return;
    const ext = f.name.split('.').pop().toLowerCase();
    if (!['xlsx', 'csv'].includes(ext)) {
        error.value = 'Chỉ nhận file .xlsx hoặc .csv.';
        return;
    }
    if (f.size > 5 * 1024 * 1024) {
        error.value = 'File tối đa 5 MB.';
        return;
    }
    error.value = '';
    form.clearErrors();
    form.file = f;
}

function submit() {
    if (!form.file) {
        error.value = 'Hãy chọn file cần nhập.';
        return;
    }
    form.post(route('customers.import'), {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => {
            visible.value = false;
            form.reset();
        },
    });
}
</script>

<template>
    <AppDialog v-model:visible="visible" header="Nhập khách hàng từ Excel" :style="{ width: '32rem' }" :breakpoints="{ '640px': '94vw' }">
        <form novalidate @submit.prevent="submit">
            <ol class="im-steps">
                <li>
                    Tải <a :href="route('customers.template')">file mẫu</a>, điền thông tin khách (mỗi khách một dòng).
                </li>
                <li>Chọn file đã điền (.xlsx hoặc .csv, tối đa 2.000 dòng) rồi bấm Nhập.</li>
            </ol>

            <ul class="im-rules">
                <li><strong>Họ tên</strong> là bắt buộc.</li>
                <li>Số điện thoại gồm 10 số và không trùng khách đã có; dòng lỗi sẽ được bỏ qua và báo lại cho bạn.</li>
                <li>Ngày sinh ghi dạng <strong>dd/mm/yyyy</strong>, hoặc chỉ ghi <strong>năm</strong> (ví dụ 1990).</li>
            </ul>

            <div class="field">
                <label>File dữ liệu</label>
                <div class="im-file">
                    <Button type="button" label="Chọn file" icon="pi pi-upload" size="small" severity="secondary" outlined @click="input.click()" />
                    <span :class="form.file ? '' : 'field-hint'">{{ form.file ? form.file.name : 'Chưa chọn file' }}</span>
                    <input ref="input" type="file" accept=".xlsx,.csv" hidden @change="pick" />
                </div>
                <small v-if="error || form.errors.file" class="field-error" role="alert">{{ error || form.errors.file }}</small>
            </div>

            <div class="dialog-actions">
                <Button type="button" label="Hủy" severity="secondary" outlined @click="visible = false" />
                <Button type="submit" label="Nhập" icon="pi pi-check" :loading="form.processing" />
            </div>
        </form>
    </AppDialog>

    <AppDialog v-model:visible="resultOpen" header="Kết quả nhập khách hàng" :style="{ width: '34rem' }" :breakpoints="{ '640px': '94vw' }">
        <template v-if="result">
            <div class="im-sum">
                <div class="im-ok"><strong>{{ result.created }}</strong><span>đã nhập</span></div>
                <div :class="result.skipped ? 'im-bad' : 'im-ok'"><strong>{{ result.skipped }}</strong><span>bỏ qua</span></div>
            </div>

            <template v-if="result.errors?.length">
                <p class="field-hint" style="margin: 1rem 0 0.4rem">Các dòng bị bỏ qua (sửa trong file rồi nhập lại các dòng này):</p>
                <ul class="im-errors">
                    <li v-for="e in result.errors" :key="e.row"><strong>Dòng {{ e.row }}:</strong> {{ e.message }}</li>
                </ul>
                <p v-if="result.more_errors" class="field-hint">… và {{ result.more_errors }} dòng khác.</p>
            </template>

            <div class="dialog-actions">
                <Button type="button" label="Đóng" @click="resultOpen = false" />
            </div>
        </template>
    </AppDialog>
</template>

<style>
.im-steps, .im-rules { margin: 0 0 1rem; padding-left: 1.2rem; font-size: 0.9rem; line-height: 1.55; }
.im-rules { color: var(--al-muted); font-size: 0.85rem; }
.im-steps a { color: var(--al-primary); font-weight: 600; }
.im-file { display: flex; align-items: center; gap: 0.75rem; min-width: 0; }
.im-file span { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; font-size: 0.88rem; }
.im-sum { display: grid; grid-template-columns: 1fr 1fr; gap: 0.75rem; }
.im-sum > div { display: flex; flex-direction: column; align-items: center; padding: 0.8rem; border-radius: 0.9rem; }
.im-sum strong { font-size: 1.6rem; line-height: 1.1; }
.im-sum span { font-size: 0.82rem; color: var(--al-muted); }
.im-ok { background: color-mix(in srgb, #16a34a 12%, transparent); }
.im-ok strong { color: #15803d; }
.im-bad { background: color-mix(in srgb, #dc2626 10%, transparent); }
.im-bad strong { color: #b91c1c; }
.im-errors { max-height: 14rem; margin: 0; padding: 0.6rem 0.9rem 0.6rem 1.9rem; overflow-y: auto; border: 1px solid var(--al-line); border-radius: 0.7rem; font-size: 0.85rem; line-height: 1.5; }
</style>
