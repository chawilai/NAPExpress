<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { Maximize2, Minimize2 } from 'lucide-vue-next';
import { ref } from 'vue';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';

defineProps<{
    embedUrl: string;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Realtime Monitor', href: '/autonap' },
];

const fullscreen = ref(false);
</script>

<template>
    <Head title="Realtime Monitor" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div
            :class="[
                'flex flex-col',
                fullscreen
                    ? 'fixed inset-0 z-50 bg-white dark:bg-slate-950'
                    : 'h-[calc(100vh-4rem)]',
            ]"
        >
            <!-- Toolbar -->
            <div
                class="flex items-center justify-between border-b border-slate-200 px-4 py-2 dark:border-slate-800"
            >
                <div
                    class="text-sm font-medium text-slate-600 dark:text-slate-400"
                >
                    AutoNAP Realtime Monitor
                </div>
                <div class="flex items-center gap-2">
                    <Button
                        variant="ghost"
                        size="sm"
                        @click="fullscreen = !fullscreen"
                    >
                        <Minimize2 v-if="fullscreen" class="mr-1 h-4 w-4" />
                        <Maximize2 v-else class="mr-1 h-4 w-4" />
                        {{ fullscreen ? 'Exit Fullscreen' : 'Fullscreen' }}
                    </Button>
                </div>
            </div>

            <!-- Embedded blade dashboard -->
            <iframe
                :src="embedUrl"
                class="w-full flex-1 border-0"
                title="AutoNAP Realtime Monitor"
                allow="autoplay"
            ></iframe>
        </div>
    </AppLayout>
</template>
