<script setup>
import { Head } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import UpdatePasswordForm from './Partials/UpdatePasswordForm.vue';
import UpdateProfileInformationForm from './Partials/UpdateProfileInformationForm.vue';

defineProps({
    profile: { type: Object, required: true },
    avatarPresets: { type: Array, default: () => [] },
});
</script>

<template>
    <Head title="Hồ sơ cá nhân" />

    <AppLayout title="Hồ sơ cá nhân">
        <div class="pf-grid">
            <section class="al-panel">
                <div class="al-toolbar">
                    <p class="al-toolbar-note"><strong>Thông tin cá nhân</strong></p>
                </div>
                <UpdateProfileInformationForm :profile="profile" :avatar-presets="avatarPresets" class="ap-body" />
            </section>

            <section class="al-panel">
                <div class="al-toolbar">
                    <p class="al-toolbar-note"><strong>Đổi mật khẩu</strong></p>
                </div>
                <UpdatePasswordForm class="ap-body" />
            </section>
        </div>
    </AppLayout>
</template>
