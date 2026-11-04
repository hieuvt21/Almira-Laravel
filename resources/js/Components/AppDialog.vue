<script setup>
import Dialog from 'primevue/dialog';

// Hộp thoại chuẩn của app: dùng thay cho <Dialog> của PrimeVue.
// Khi mở sẽ tự đặt con trỏ vào ô nhập liệu đầu tiên.
defineOptions({ inheritAttrs: false });

const FIELD =
    'input:not([type=hidden]):not([type=file]):not([type=checkbox]):not([type=radio]):not([disabled]):not([readonly]), textarea:not([disabled]):not([readonly])';

function onShow() {
    // Hộp thoại vừa mở nằm cuối cùng trong body
    const dialogs = document.querySelectorAll('.p-dialog');
    const field = dialogs[dialogs.length - 1]?.querySelector(FIELD);
    if (!field) return;

    // PrimeVue tự focus phần tử có [autofocus]; đặt sẵn để nó chọn đúng ô này
    field.setAttribute('autofocus', '');
    requestAnimationFrame(() => {
        if (document.activeElement !== field) field.focus();
    });
}
</script>

<template>
    <Dialog v-bind="$attrs" modal @show="onShow">
        <template v-for="(_, name) in $slots" #[name]="slotData">
            <slot :name="name" v-bind="slotData || {}" />
        </template>
    </Dialog>
</template>
