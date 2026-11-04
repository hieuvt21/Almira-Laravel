<script setup>
import { Head, useForm } from '@inertiajs/vue3';
import Button from 'primevue/button';
import Checkbox from 'primevue/checkbox';
import InputText from 'primevue/inputtext';
import Password from 'primevue/password';
import BrandMark from '@/Components/BrandMark.vue';
import { useBranding } from '@/composables/useBranding';

defineProps({
    status: { type: String, default: '' },
    canResetPassword: { type: Boolean, default: false },
});

const { branding } = useBranding();

const form = useForm({ login: '', password: '', remember: false });

const submit = () => {
    form.post(route('login'), { onFinish: () => form.reset('password') });
};
</script>

<template>
    <Head title="Đăng nhập" />

    <div class="al-auth">
        <div class="al-auth-card glass">
            <div class="al-auth-brand">
                <BrandMark :logo="branding.logo" :name="branding.name" large />
                <span class="al-brand-name">{{ branding.name }}</span>
            </div>

            <h1 class="al-auth-title">Đăng nhập</h1>
            <p class="al-auth-sub">Dùng tên đăng nhập, số điện thoại hoặc email.</p>

            <p v-if="status" class="al-auth-status" role="status">{{ status }}</p>

            <form @submit.prevent="submit" novalidate>
                <div class="field">
                    <label for="login">Tên đăng nhập, số điện thoại hoặc email</label>
                    <InputText
                        id="login"
                        v-model="form.login"
                        autocomplete="username"
                        autocapitalize="none"
                        spellcheck="false"
                        autofocus
                        fluid
                        :invalid="!!form.errors.login"
                    />
                    <small v-if="form.errors.login" class="field-error" role="alert">{{ form.errors.login }}</small>
                </div>

                <div class="field">
                    <label for="password">Mật khẩu</label>
                    <Password
                        v-model="form.password"
                        inputId="password"
                        :feedback="false"
                        toggleMask
                        autocomplete="current-password"
                        fluid
                        :invalid="!!form.errors.password"
                    />
                    <small v-if="form.errors.password" class="field-error" role="alert">{{ form.errors.password }}</small>
                </div>

                <label class="al-auth-remember">
                    <Checkbox v-model="form.remember" binary inputId="remember" />
                    <span>Ghi nhớ đăng nhập</span>
                </label>

                <Button type="submit" label="Đăng nhập" icon="pi pi-sign-in" :loading="form.processing" fluid />
            </form>
        </div>
    </div>
</template>
