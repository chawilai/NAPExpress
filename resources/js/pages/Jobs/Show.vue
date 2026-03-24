<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { dashboard } from '@/routes';
import type { BreadcrumbItem } from '@/types';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { 
    ArrowLeft,
    CheckCircle2, 
    XCircle, 
    Clock, 
    Activity,
    FileText,
    AlertCircle
} from 'lucide-vue-next';

const props = defineProps<{
    job: {
        id: number;
        form_type: string;
        method: string;
        status: string;
        counts: {
            total: number;
            success: number;
            failed: number;
        };
        created_at: string;
        user: {
            name: string;
        };
        job_rows: Array<{
            id: number;
            row_number: number;
            pid_masked: string;
            nap_response_code: string | null;
            error_message: string | null;
        }>;
    };
}>();

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: dashboard().url,
    },
    {
        title: `Job #${props.job.id}`,
        href: '#',
    },
];

const getStatusColor = (status: string) => {
    switch (status) {
        case 'completed': return 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400';
        case 'failed': return 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400';
        case 'processing': return 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400';
        default: return 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-400';
    }
};

const getStatusIcon = (status: string) => {
    switch (status) {
        case 'completed': return CheckCircle2;
        case 'failed': return XCircle;
        case 'processing': return Activity;
        default: return Clock;
    }
};
</script>

<template>
    <Head :title="`Job #${job.id} | NAPExpress`" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-6 p-6 font-['Outfit']">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-4">
                    <Link :href="dashboard().url">
                        <Button variant="outline" size="icon" class="h-8 w-8 rounded-full">
                            <ArrowLeft class="h-4 w-4" />
                        </Button>
                    </Link>
                    <h2 class="text-2xl font-bold tracking-tight">Reporting Job #{{ job.id }}</h2>
                    <Badge variant="secondary" :class="['flex items-center gap-1 px-3 py-1', getStatusColor(job.status)]">
                        <component :is="getStatusIcon(job.status)" class="h-4 w-4" />
                        {{ job.status.charAt(0).toUpperCase() + job.status.slice(1) }}
                    </Badge>
                </div>
                <div class="text-sm text-slate-500">
                    Started by <span class="font-medium text-slate-900 dark:text-white">{{ job.user.name }}</span> on {{ new Date(job.created_at).toLocaleString() }}
                </div>
            </div>

            <div class="grid gap-6 md:grid-cols-3">
                <Card class="md:col-span-1 border-slate-200 shadow-sm dark:border-slate-800">
                    <CardHeader>
                        <CardTitle class="text-lg">Job Details</CardTitle>
                        <CardDescription>Overview of the reporting parameters</CardDescription>
                    </CardHeader>
                    <CardContent class="grid gap-4">
                        <div class="flex justify-between py-2 border-b border-slate-100 dark:border-slate-800">
                            <span class="text-slate-500">Form Type</span>
                            <span class="font-medium">{{ job.form_type }}</span>
                        </div>
                        <div class="flex justify-between py-2 border-b border-slate-100 dark:border-slate-800">
                            <span class="text-slate-500">Method</span>
                            <span class="font-medium">{{ job.method }}</span>
                        </div>
                        <div class="flex justify-between py-2 border-b border-slate-100 dark:border-slate-800">
                            <span class="text-slate-500">Total Records</span>
                            <span class="font-medium">{{ job.counts.total }}</span>
                        </div>
                        <div class="flex justify-between py-2 border-b border-slate-100 dark:border-slate-800">
                            <span class="text-slate-500">Successful</span>
                            <span class="font-medium text-green-600">{{ job.counts.success }}</span>
                        </div>
                        <div class="flex justify-between py-2">
                            <span class="text-slate-500">Failed</span>
                            <span class="font-medium text-red-600">{{ job.counts.failed }}</span>
                        </div>

                        <div v-if="job.status === 'processing'" class="mt-4 p-4 rounded-lg bg-blue-50 border border-blue-100 dark:bg-blue-900/10 dark:border-blue-900/30">
                            <div class="flex items-center gap-2 text-blue-800 dark:text-blue-300 mb-2">
                                <Activity class="h-4 w-4 animate-spin" />
                                <span class="text-sm font-semibold">Worker Active</span>
                            </div>
                            <div class="h-2 w-full bg-blue-200 rounded-full overflow-hidden dark:bg-blue-900/50">
                                <div class="h-full bg-blue-600 transition-all duration-500" :style="{ width: `${(job.counts.success / job.counts.total) * 100}%` }"></div>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <Card class="md:col-span-2 border-slate-200 shadow-sm dark:border-slate-800">
                    <CardHeader>
                        <CardTitle class="text-lg">Record Logs</CardTitle>
                        <CardDescription>Detailed status of each processed record</CardDescription>
                    </CardHeader>
                    <CardContent class="p-0">
                        <div class="overflow-x-auto">
                            <table class="w-full text-left text-sm">
                                <thead class="bg-slate-50 border-b border-slate-200 dark:bg-slate-900/50 dark:border-slate-800">
                                    <tr>
                                        <th class="px-6 py-3 font-semibold text-slate-600 dark:text-slate-400">Row</th>
                                        <th class="px-6 py-3 font-semibold text-slate-600 dark:text-slate-400">Masked PID</th>
                                        <th class="px-6 py-3 font-semibold text-slate-600 dark:text-slate-400">Response Code</th>
                                        <th class="px-6 py-3 font-semibold text-slate-600 dark:text-slate-400">Status</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                                    <tr v-for="row in job.job_rows" :key="row.id" class="hover:bg-slate-50/50 transition-colors">
                                        <td class="px-6 py-3">#{{ row.row_number }}</td>
                                        <td class="px-6 py-3 font-mono text-xs">{{ row.pid_masked }}</td>
                                        <td class="px-6 py-3">
                                            <span v-if="row.nap_response_code" class="text-xs font-medium text-slate-900 dark:text-white bg-slate-100 dark:bg-slate-800 px-2 py-0.5 rounded">
                                                {{ row.nap_response_code }}
                                            </span>
                                            <span v-else class="text-slate-400 italic">Pending...</span>
                                        </td>
                                        <td class="px-6 py-3">
                                            <div v-if="row.error_message" class="flex items-center gap-1.5 text-red-600">
                                                <AlertCircle class="h-3.5 w-3.5" />
                                                <span class="text-xs">{{ row.error_message }}</span>
                                            </div>
                                            <div v-else-if="row.nap_response_code" class="flex items-center gap-1.5 text-green-600">
                                                <CheckCircle2 class="h-3.5 w-3.5" />
                                                <span class="text-xs">Success</span>
                                            </div>
                                            <div v-else class="flex items-center gap-1.5 text-slate-400">
                                                <Clock class="h-3.5 w-3.5" />
                                                <span class="text-xs">Queued</span>
                                            </div>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </div>
    </AppLayout>
</template>
