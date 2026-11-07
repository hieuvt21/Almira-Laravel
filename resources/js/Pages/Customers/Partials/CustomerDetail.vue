<script setup>
import { computed, ref, watch } from 'vue';
import Button from 'primevue/button';
import Drawer from 'primevue/drawer';
import ProgressBar from 'primevue/progressbar';
import TierBadge from '@/Components/TierBadge.vue';
import UserAvatar from '@/Components/UserAvatar.vue';
import { useMoney } from '@/composables/useMoney';
import { formatPhone } from '@/utils/phone';

const props = defineProps({
    customer: { type: Object, default: null },
    canEdit: { type: Boolean, default: false },
    canDelete: { type: Boolean, default: false },
});
const emit = defineEmits(['edit', 'remove']);
const visible = defineModel('visible', { type: Boolean, default: false });

const { money } = useMoney();

/* ---------- Lịch sử mua hàng (lấy khi mở) ---------- */
const empty = () => ({ orders: [], orders_count: 0, last_purchase: null });
const history = ref(empty());
const loading = ref(false);

watch(
    () => [visible.value, props.customer?.id],
    async ([open, id]) => {
        if (!open || !id) return;
        history.value = empty();
        loading.value = true;
        try {
            const res = await fetch(route('customers.show', id), {
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin',
            });
            if (res.ok) {
                const data = await res.json();
                if (props.customer?.id === id) history.value = { ...empty(), ...data };
            }
        } catch {
            // lỗi mạng: giữ trạng thái trống
        } finally {
            loading.value = false;
        }
    },
    { immediate: true },
);

/* ---------- Hiển thị ---------- */
const birthText = computed(() => {
    const c = props.customer;
    if (!c) return '';
    if (c.birth_date) {
        const [y, m, d] = c.birth_date.split('-');
        return `${d}/${m}/${y}`;
    }
    return c.birth_year ? `Năm ${c.birth_year}` : '';
});

const age = computed(() => {
    const c = props.customer;
    if (!c) return null;
    const now = new Date();
    if (c.birth_date) {
        const [y, m, d] = c.birth_date.split('-').map(Number);
        let a = now.getFullYear() - y;
        if (now.getMonth() + 1 < m || (now.getMonth() + 1 === m && now.getDate() < d)) a--;
        return a;
    }
    return c.birth_year ? now.getFullYear() - c.birth_year : null;
});

const birthdayText = computed(() => {
    const n = props.customer?.birthday_in;
    if (n === null || n === undefined || n > 7) return '';
    return n === 0 ? 'Hôm nay sinh nhật' : n === 1 ? 'Ngày mai sinh nhật' : `Sinh nhật sau ${n} ngày`;
});

// Tiến độ lên hạng kế tiếp
const progress = computed(() => {
    const t = props.customer?.tier;
    if (!t?.next) return null;
    const span = t.next.min_spent - t.min_spent;
    return span > 0 ? Math.min(100, Math.max(0, Math.round(((props.customer.total_spent - t.min_spent) / span) * 100))) : 100;
});
</script>

<template>
    <Drawer v-model:visible="visible" position="right" header="Thông tin khách hàng" :style="{ width: 'min(27rem, 100vw)' }">
        <div v-if="customer" class="cd">
            <div class="cd-head">
                <div class="cd-who">
                    <UserAvatar :name="customer.name" :url="customer.avatar_url" size="xlarge" />
                    <TierBadge :tier="customer.tier" show-discount />
                </div>
                <div class="cd-id">
                    <h3>{{ customer.name }}</h3>
                    <span class="cd-code">{{ customer.code }}</span>
                    <div class="cd-tags">
                        <span v-if="birthdayText" class="cu-bday"><i class="pi pi-gift" aria-hidden="true" />{{ birthdayText }}</span>
                    </div>
                </div>
            </div>

            <div class="cd-stats">
                <div>
                    <span>Tổng chi tiêu</span>
                    <strong class="cd-money">{{ money(customer.total_spent) }}</strong>
                </div>
                <div>
                    <span>Số đơn</span>
                    <strong>{{ history.orders_count }}</strong>
                </div>
                <div>
                    <span>Mua gần nhất</span>
                    <strong>{{ history.last_purchase || '—' }}</strong>
                </div>
            </div>

            <div v-if="customer.tier" class="cd-tier">
                <template v-if="customer.tier.next">
                    <p>
                        Còn <strong>{{ money(customer.tier.next.remaining) }}</strong> để lên hạng
                        <strong>{{ customer.tier.next.name }}</strong>
                    </p>
                    <ProgressBar :value="progress" :showValue="false" style="height: 0.5rem" />
                </template>
                <p v-else>Đã đạt hạng cao nhất.</p>
            </div>

            <dl class="cd-info">
                <div>
                    <dt><i class="pi pi-phone" aria-hidden="true" />Điện thoại</dt>
                    <dd><a v-if="customer.phone" :href="`tel:${customer.phone}`">{{ formatPhone(customer.phone) }}</a><span v-else class="cu-empty">Chưa có</span></dd>
                </div>
                <div>
                    <dt><i class="pi pi-calendar" aria-hidden="true" />Ngày sinh</dt>
                    <dd>
                        <template v-if="birthText">{{ birthText }}<span v-if="age !== null" class="cu-empty"> · {{ customer.birth_date ? '' : '≈ ' }}{{ age }} tuổi</span></template>
                        <span v-else class="cu-empty">Chưa có</span>
                    </dd>
                </div>
                <div>
                    <dt><i class="pi pi-briefcase" aria-hidden="true" />Nghề nghiệp</dt>
                    <dd>{{ customer.occupation || '—' }}</dd>
                </div>
                <div>
                    <dt><i class="pi pi-map-marker" aria-hidden="true" />Địa chỉ</dt>
                    <dd>{{ customer.address || '—' }}</dd>
                </div>
                <div v-if="customer.note">
                    <dt><i class="pi pi-comment" aria-hidden="true" />Ghi chú</dt>
                    <dd>{{ customer.note }}</dd>
                </div>
            </dl>

            <h4 class="cd-title">Lịch sử mua hàng</h4>
            <p v-if="loading" class="field-hint">Đang tải…</p>
            <ul v-else-if="history.orders.length" class="cd-orders">
                <li v-for="o in history.orders" :key="o.code">
                    <div>
                        <strong>{{ o.code }}</strong>
                        <small>{{ o.date }}<template v-if="o.items_count"> · {{ o.items_count }} sản phẩm</template></small>
                    </div>
                    <span>{{ money(o.total) }}</span>
                </li>
            </ul>
            <p v-else class="cd-none">Chưa có đơn hàng nào.</p>
        </div>

        <template #footer>
            <div v-if="customer" class="cd-actions">
                <Button v-if="canEdit" label="Sửa" icon="pi pi-pencil" severity="secondary" outlined @click="emit('edit', customer)" />
                <Button v-if="canDelete" label="Xóa" icon="pi pi-trash" severity="danger" text @click="emit('remove', customer)" />
            </div>
        </template>
    </Drawer>
</template>

<style>
.cd { display: flex; flex-direction: column; gap: 1rem; }
.cd-head { display: flex; align-items: center; gap: 1rem; }
.cd-head .p-avatar { flex: none; width: 5rem !important; height: 5rem !important; }
.cd-head .al-avatar { font-size: 1.8rem !important; background: linear-gradient(135deg, var(--p-primary-400), var(--p-primary-700)) !important; color: #fff !important; }
.cd-who { flex: none; display: flex; flex-direction: column; align-items: center; gap: 0.45rem; max-width: 8rem; }
.cd-who .tier-badge { max-width: 100%; white-space: normal; text-align: center; }
.cd-id { min-width: 0; display: flex; flex-direction: column; gap: 0.3rem; }
.cd-id h3 { margin: 0; font-size: 1.2rem; font-weight: 700; line-height: 1.25; overflow-wrap: anywhere; }
.cd-code { color: var(--al-muted); font-size: 0.82rem; }
.cd-tags { display: flex; flex-wrap: wrap; gap: 0.4rem; }
.cd-stats { display: grid; grid-template-columns: 1.4fr 0.7fr 1fr; gap: 0.5rem; }
.cd-stats > div { display: flex; flex-direction: column; gap: 0.2rem; padding: 0.6rem 0.7rem; border-radius: 0.8rem; background: rgba(255, 255, 255, 0.6); }
.cd-stats span { color: var(--al-muted); font-size: 0.74rem; }
.cd-stats strong { font-size: 0.95rem; }
.cd-money { color: #15803d; font-variant-numeric: tabular-nums; }
.cd-tier { padding: 0.7rem 0.8rem; border-radius: 0.8rem; background: var(--al-primary-soft); }
.cd-tier p { margin: 0 0 0.45rem; font-size: 0.86rem; }
.cd-tier p:last-child { margin-bottom: 0; }
.cd-info { display: flex; flex-direction: column; gap: 0.55rem; margin: 0; font-size: 0.9rem; }
.cd-info > div { display: flex; gap: 0.7rem; }
.cd-info dt { flex: none; display: flex; align-items: flex-start; gap: 0.45rem; width: 8rem; color: var(--al-muted); }
.cd-info dt i { margin-top: 0.15rem; width: 1rem; color: var(--p-primary-600); font-size: 0.85rem; text-align: center; }
.cd-info dd { margin: 0; min-width: 0; overflow-wrap: anywhere; white-space: pre-line; }
.cd-info a { color: var(--al-primary); font-weight: 500; text-decoration: none; }
.cd-title { margin: 0.4rem 0 0; font-size: 0.95rem; font-weight: 600; }
.cd-none { margin: 0; padding: 1rem; border: 2px dashed var(--al-line); border-radius: 0.8rem; text-align: center; color: var(--al-muted); font-size: 0.88rem; }
.cd-orders { list-style: none; margin: 0; padding: 0; }
.cd-orders li { display: flex; align-items: center; justify-content: space-between; gap: 0.6rem; padding: 0.55rem 0; border-top: 1px solid var(--al-line); font-size: 0.88rem; }
.cd-orders li > div { display: flex; flex-direction: column; }
.cd-orders small { color: var(--al-muted); }
.cd-orders span { font-weight: 600; font-variant-numeric: tabular-nums; }
.cd-actions { display: flex; justify-content: space-between; gap: 0.5rem; width: 100%; }
</style>
