<script setup>
import { Head } from '@inertiajs/vue3';
import Tab from 'primevue/tab';
import TabList from 'primevue/tablist';
import TabPanel from 'primevue/tabpanel';
import TabPanels from 'primevue/tabpanels';
import Tabs from 'primevue/tabs';
import AppLayout from '@/Layouts/AppLayout.vue';
import CatalogList from './Partials/CatalogList.vue';

defineProps({
    categories: { type: Array, default: () => [] },
    units: { type: Array, default: () => [] },
    priceLists: { type: Array, default: () => [] },
});
</script>

<template>
    <Head title="Danh mục & đơn vị" />

    <AppLayout title="Danh mục & đơn vị">
        <div class="al-panel al-fill">
            <Tabs value="categories">
                <TabList>
                    <Tab value="categories">Danh mục</Tab>
                    <Tab value="units">Đơn vị tính</Tab>
                    <Tab value="price-lists">Bảng giá</Tab>
                </TabList>
                <TabPanels>
                    <TabPanel value="categories">
                        <CatalogList type="categories" noun="danh mục" :items="categories" countField="products_count" countLabel="Sản phẩm" />
                    </TabPanel>
                    <TabPanel value="units">
                        <CatalogList type="units" noun="đơn vị tính" :items="units" countField="products_count" countLabel="Sản phẩm dùng" />
                    </TabPanel>
                    <TabPanel value="price-lists">
                        <CatalogList type="price-lists" noun="bảng giá" :items="priceLists" countField="prices_count" countLabel="Giá đã nhập" />
                    </TabPanel>
                </TabPanels>
            </Tabs>
        </div>
    </AppLayout>
</template>
