<script setup>
import { computed, watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
import Button from 'primevue/button';
import InputText from 'primevue/inputtext';
import AvatarPicker from '@/Components/AvatarPicker.vue';
import FieldStatus from '@/Components/FieldStatus.vue';
import PhoneInput from '@/Components/PhoneInput.vue';
import { useUniqueCheck } from '@/composables/useUniqueCheck';

const props = defineProps({
    profile: { type: Object, required: true },
    avatarPresets: { type: Array, default: () => [] },
});

const form = useForm({
    name: props.profile.name,
    phone: props.profile.phone ?? '',
    email: props.profile.email ?? '',
    avatar_file: null,
    avatar_preset: null,
    remove_avatar: false,
});

// Kiểm tra trùng ngay khi nhập (bỏ qua chính mình)
const ignoreId = () => props.profile.id;
const phoneCheck = useUniqueCheck('phone', () => form.phone, { ignoreId, original: () => props.profile.phone });
const emailCheck = useUniqueCheck('email', () => form.email, { ignoreId, original: () => props.profile.email });

['name', 'phone', 'email'].forEach((f) => watch(() => form[f], () => form.clearErrors(f)));

const hasConflict = computed(() =>
    [phoneCheck, emailCheck].some((c) => c.status === 'taken' || c.status === 'invalid'),
);

function submit() {
    if (hasConflict.value) return;

    // Có thể kèm file ảnh nên gửi dạng multipart, dùng _method=patch
    form.transform((data) => ({ ...data, _method: 'patch' })).post(route('profile.update'), {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => {
            form.avatar_file = null;
            form.avatar_preset = null;
            form.remove_avatar = false;
        },
    });
}
</script>

<template>
    <form novalidate @submit.prevent="submit">
        <AvatarPicker
            v-model:file="form.avatar_file"
            v-model:preset="form.avatar_preset"
            v-model:remove="form.remove_avatar"
            :name="form.name"
            :currentUrl="profile.avatar_url"
            :presets="avatarPresets"
        />
        <small v-if="form.errors.avatar_file" class="field-error" style="display: block; margin: -0.5rem 0 1rem">{{ form.errors.avatar_file }}</small>

        <div class="field">
            <label for="pf-name">Họ tên</label>
            <InputText id="pf-name" v-model="form.name" autocomplete="name" autofocus fluid :invalid="!!form.errors.name" />
            <small v-if="form.errors.name" class="field-error">{{ form.errors.name }}</small>
        </div>

        <div class="field">
            <label for="pf-username">Tên đăng nhập</label>
            <div id="pf-username" class="pf-readonly">
                <i class="pi pi-lock" aria-hidden="true" />
                <span>{{ profile.username }}</span>
            </div>
            <small class="field-hint">Tên đăng nhập do quản trị viên đặt, bạn không tự đổi được.</small>
        </div>

        <div class="field">
            <label for="pf-phone">Số điện thoại <span class="field-hint">(không bắt buộc)</span></label>
            <PhoneInput
                id="pf-phone"
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
            <label for="pf-email">Email <span class="field-hint">(không bắt buộc)</span></label>
            <InputText
                id="pf-email"
                v-model="form.email"
                type="email"
                autocomplete="email"
                autocapitalize="none"
                fluid
                :invalid="!!form.errors.email || ['taken', 'invalid'].includes(emailCheck.status)"
                @blur="emailCheck.check()"
            />
            <small v-if="form.errors.email" class="field-error">{{ form.errors.email }}</small>
            <FieldStatus v-else :status="emailCheck.status" :message="emailCheck.message" />
        </div>

        <div class="dialog-actions" style="align-items: center">
            <span v-if="form.recentlySuccessful" class="pf-saved" role="status">Đã lưu.</span>
            <Button type="submit" label="Lưu thay đổi" icon="pi pi-check" :loading="form.processing" :disabled="hasConflict" />
        </div>
    </form>
</template>
