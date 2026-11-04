<script setup>
import { ref } from 'vue';
import { useForm } from '@inertiajs/vue3';
import Button from 'primevue/button';
import Password from 'primevue/password';

const currentRef = ref(null);
const newRef = ref(null);

const form = useForm({
    current_password: '',
    password: '',
    password_confirmation: '',
});

// Đặt con trỏ vào ô mật khẩu (PrimeVue bọc input bên trong component)
const focusInput = (comp) => comp.value?.$el?.querySelector('input')?.focus();

function updatePassword() {
    form.put(route('password.update'), {
        preserveScroll: true,
        onSuccess: () => form.reset(),
        onError: () => {
            if (form.errors.password) {
                form.reset('password', 'password_confirmation');
                focusInput(newRef);
            }
            if (form.errors.current_password) {
                form.reset('current_password');
                focusInput(currentRef);
            }
        },
    });
}
</script>

<template>
    <form novalidate @submit.prevent="updatePassword">
        <p class="field-hint" style="margin: 0 0 1rem">
            Nên dùng mật khẩu dài và khó đoán để giữ tài khoản an toàn.
        </p>

        <div class="field">
            <label for="pw-current">Mật khẩu hiện tại</label>
            <Password
                ref="currentRef"
                v-model="form.current_password"
                inputId="pw-current"
                :feedback="false"
                toggleMask
                autocomplete="current-password"
                fluid
                :invalid="!!form.errors.current_password"
            />
            <small v-if="form.errors.current_password" class="field-error">{{ form.errors.current_password }}</small>
        </div>

        <div class="field">
            <label for="pw-new">Mật khẩu mới</label>
            <Password
                ref="newRef"
                v-model="form.password"
                inputId="pw-new"
                :feedback="false"
                toggleMask
                autocomplete="new-password"
                fluid
                :invalid="!!form.errors.password"
            />
            <small v-if="form.errors.password" class="field-error">{{ form.errors.password }}</small>
            <small v-else class="field-hint">Tối thiểu 8 ký tự.</small>
        </div>

        <div class="field">
            <label for="pw-confirm">Nhập lại mật khẩu mới</label>
            <Password
                v-model="form.password_confirmation"
                inputId="pw-confirm"
                :feedback="false"
                toggleMask
                autocomplete="new-password"
                fluid
                :invalid="!!form.errors.password_confirmation"
            />
            <small v-if="form.errors.password_confirmation" class="field-error">{{ form.errors.password_confirmation }}</small>
        </div>

        <div class="dialog-actions" style="align-items: center">
            <span v-if="form.recentlySuccessful" class="pf-saved" role="status">Đã lưu.</span>
            <Button type="submit" label="Đổi mật khẩu" icon="pi pi-key" :loading="form.processing" />
        </div>
    </form>
</template>
