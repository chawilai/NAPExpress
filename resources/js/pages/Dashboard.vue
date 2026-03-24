<script setup lang="ts">
import { ref } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { dashboard } from '@/routes';
import type { BreadcrumbItem } from '@/types';
import { downloadTemplate as downloadTemplateRoute, store as storeJob } from '@/routes/jobs';
import { show as showJobRoute } from '@/routes/jobs';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { 
    Plus, 
    FileText, 
    CheckCircle2, 
    XCircle, 
    Clock, 
    Activity,
    ArrowUpRight,
    Search,
    Filter,
    Download,
    Upload,
    Loader2
} from 'lucide-vue-next';
import { Input } from '@/components/ui/input';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Label } from '@/components/ui/label';

const props = defineProps<{
    jobs: {
        data: Array<{
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
        }>;
        links: Array<any>;
    };
}>();

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: dashboard(),
    },
];

const isCreateModalOpen = ref(false);

const form = useForm({
    form_type: 'Reach RR',
    method: 'Playwright',
    file: null as File | null,
});

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

const downloadTemplate = () => {
    window.open(downloadTemplateRoute.url({ query: { form_type: form.form_type } }), '_blank');
};

const submitJob = () => {
    form.post(storeJob().url, {
        onSuccess: () => {
            isCreateModalOpen.value = false;
            form.reset();
        },
    });
};

const handleFileChange = (event: Event) => {
    const target = event.target as HTMLInputElement;
    if (target.files && target.files.length > 0) {
        form.file = target.files[0];
    }
};
</script>

<template>
    <Head title="Dashboard | NAPExpress" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-6 p-6 font-['Outfit']">
            <!-- Header Stats -->
            <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
                <Card class="border-slate-200 shadow-sm dark:border-slate-800">
                    <CardHeader class="flex flex-row items-center justify-between pb-2 shadow-none">
                        <CardTitle class="text-sm font-medium text-slate-500">Total Reports</CardTitle>
                        <FileText class="h-4 w-4 text-slate-400" />
                    </CardHeader>
                    <CardContent>
                        <div class="text-2xl font-bold">1,284</div>
                        <p class="text-xs text-slate-500 mt-1">
                            <span class="text-teal-600 font-medium">+12%</span> from last month
                        </p>
                    </CardContent>
                </Card>
                <Card class="border-slate-200 shadow-sm dark:border-slate-800">
                    <CardHeader class="flex flex-row items-center justify-between pb-2 shadow-none">
                        <CardTitle class="text-sm font-medium text-slate-500">Success Rate</CardTitle>
                        <CheckCircle2 class="h-4 w-4 text-teal-500" />
                    </CardHeader>
                    <CardContent>
                        <div class="text-2xl font-bold">98.2%</div>
                        <p class="text-xs text-slate-500 mt-1">
                            <span class="text-teal-600 font-medium">+0.4%</span> improvement
                        </p>
                    </CardContent>
                </Card>
                <Card class="border-slate-200 shadow-sm dark:border-slate-800">
                    <CardHeader class="flex flex-row items-center justify-between pb-2 shadow-none">
                        <CardTitle class="text-sm font-medium text-slate-500">Active Workers</CardTitle>
                        <Activity class="h-4 w-4 text-blue-500" />
                    </CardHeader>
                    <CardContent>
                        <div class="text-2xl font-bold">4</div>
                        <p class="text-xs text-slate-500 mt-1">
                            Current Playwright instances
                        </p>
                    </CardContent>
                </Card>
                <Card class="border-slate-200 shadow-sm dark:border-slate-800">
                    <CardHeader class="flex flex-row items-center justify-between pb-2 shadow-none">
                        <CardTitle class="text-sm font-medium text-slate-500">Pending Jobs</CardTitle>
                        <Clock class="h-4 w-4 text-orange-500" />
                    </CardHeader>
                    <CardContent>
                        <div class="text-2xl font-bold">12</div>
                        <p class="text-xs text-slate-500 mt-1">
                            Estimated wait: 5 mins
                        </p>
                    </CardContent>
                </Card>
            </div>

            <!-- Main Content Area -->
            <div class="flex flex-col gap-4">
                <div class="flex items-center justify-between gap-4">
                    <h2 class="text-2xl font-semibold tracking-tight">Recent Reporting Jobs</h2>
                    <div class="flex items-center gap-2">
                        <div class="relative w-64 hidden md:block">
                            <Search class="absolute left-2.5 top-2.5 h-4 w-4 text-slate-400" />
                            <Input placeholder="Search jobs..." class="pl-9 h-9 border-slate-200 dark:border-sidebar-border" />
                        </div>
                        
                        <Dialog v-model:open="isCreateModalOpen">
                            <DialogTrigger as-child>
                                <Button size="sm" class="h-9 bg-teal-600 hover:bg-teal-700 text-white">
                                    <Plus class="mr-2 h-4 w-4" />
                                    New Reporting Job
                                </Button>
                            </DialogTrigger>
                            <DialogContent class="sm:max-w-[500px] font-['Outfit'] shadow-2xl">
                                <DialogHeader>
                                    <DialogTitle class="text-2xl">Create New Reporting Job</DialogTitle>
                                    <DialogDescription>
                                        Select the form type and upload your Excel file to start automated reporting.
                                    </DialogDescription>
                                </DialogHeader>
                                
                                <div class="grid gap-6 py-4">
                                    <div class="grid gap-2">
                                        <Label for="form-type">Report Type</Label>
                                        <Select v-model="form.form_type">
                                            <SelectTrigger id="form-type">
                                                <SelectValue placeholder="Select a report type" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem value="Reach RR">Reach RR (AIDS Zero)</SelectItem>
                                                <SelectItem value="Lab CD4/VL">Lab CD4/VL Results</SelectItem>
                                                <SelectItem value="VCT">VCT Screening</SelectItem>
                                                <SelectItem value="PrEP">PrEP Follow-up</SelectItem>
                                            </SelectContent>
                                        </Select>
                                    </div>

                                    <div class="grid gap-2">
                                        <Label for="method">Reporting Method</Label>
                                        <Select v-model="form.method">
                                            <SelectTrigger id="method">
                                                <SelectValue placeholder="Select method" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem value="Playwright">Automated Browser (Playwright)</SelectItem>
                                                <SelectItem value="API">Direct NHSO API (Requires Lab API Key)</SelectItem>
                                            </SelectContent>
                                        </Select>
                                    </div>

                                    <div class="flex flex-col gap-3 rounded-lg border border-teal-100 bg-teal-50/50 p-4 dark:border-teal-900/30 dark:bg-teal-900/10">
                                        <div class="flex items-center justify-between">
                                            <div class="flex items-center gap-2 text-sm font-medium text-teal-800 dark:text-teal-300">
                                                <FileText class="h-4 w-4" />
                                                Need a template?
                                            </div>
                                            <Button variant="ghost" size="sm" class="h-8 text-teal-700 hover:bg-teal-100 dark:text-teal-400 dark:hover:bg-teal-900/30" @click="downloadTemplate">
                                                <Download class="mr-2 h-4 w-4" />
                                                Download
                                            </Button>
                                        </div>
                                        <p class="text-xs text-teal-700/70 dark:text-teal-400/70 leading-relaxed">
                                            Use our standardized template to ensure data compatibility. Fill in the required patient IDs and service dates.
                                        </p>
                                    </div>

                                    <div class="grid gap-2">
                                        <Label for="file">Data Source (Excel)</Label>
                                        <div class="flex items-center justify-center rounded-lg border-2 border-dashed border-slate-200 py-8 dark:border-slate-800">
                                            <div class="flex flex-col items-center gap-2">
                                                <div class="rounded-full bg-slate-100 p-3 dark:bg-slate-800">
                                                    <Upload class="h-6 w-6 text-slate-500" />
                                                </div>
                                                <div class="text-center">
                                                    <label for="file-upload" class="cursor-pointer font-medium text-teal-600 hover:text-teal-500 dark:text-teal-400">
                                                        Click to upload
                                                        <input id="file-upload" type="file" class="sr-only" @change="handleFileChange" accept=".xlsx,.xls,.csv" />
                                                    </label>
                                                    <p class="text-xs text-slate-500">XLSX, XLS or CSV (Max 10MB)</p>
                                                </div>
                                                <div v-if="form.file" class="mt-2 flex items-center gap-2 rounded-md bg-white px-3 py-1 text-sm border shadow-sm dark:bg-slate-900">
                                                    <FileText class="h-4 w-4 text-teal-600" />
                                                    <span class="max-w-[200px] truncate">{{ form.file.name }}</span>
                                                    <XCircle class="h-4 w-4 text-slate-400 cursor-pointer" @click="form.file = null" />
                                                </div>
                                            </div>
                                        </div>
                                        <p v-if="form.errors.file" class="text-xs text-red-500">{{ form.errors.file }}</p>
                                    </div>
                                </div>
                                
                                <DialogFooter>
                                    <Button variant="outline" @click="isCreateModalOpen = false">Cancel</Button>
                                    <Button class="bg-teal-600 hover:bg-teal-700 text-white" :disabled="form.processing || !form.file" @click="submitJob">
                                        <Loader2 v-if="form.processing" class="mr-2 h-4 w-4 animate-spin" />
                                        Launch Reporting Job
                                    </Button>
                                </DialogFooter>
                            </DialogContent>
                        </Dialog>
                    </div>
                </div>

                <Card class="border-slate-200 shadow-sm dark:border-slate-800 overflow-hidden">
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-sm">
                            <thead class="bg-slate-50 border-b border-slate-200 dark:bg-slate-900/50 dark:border-slate-800">
                                <tr>
                                    <th class="px-6 py-4 font-semibold text-slate-600 dark:text-slate-400">Job ID</th>
                                    <th class="px-6 py-4 font-semibold text-slate-600 dark:text-slate-400">Form Type</th>
                                    <th class="px-6 py-4 font-semibold text-slate-600 dark:text-slate-400">Method</th>
                                    <th class="px-6 py-4 font-semibold text-slate-600 dark:text-slate-400">Status</th>
                                    <th class="px-6 py-4 font-semibold text-slate-600 dark:text-slate-400">Records</th>
                                    <th class="px-6 py-4 font-semibold text-slate-600 dark:text-slate-400">Created At</th>
                                    <th class="px-6 py-4 font-semibold text-slate-600 dark:text-slate-400">Staff</th>
                                    <th class="px-6 py-4"></th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                                <tr v-for="job in jobs.data" :key="job.id" class="hover:bg-slate-50/50 transition-colors dark:hover:bg-slate-900/30">
                                    <td class="px-6 py-4 font-medium text-slate-900 dark:text-white">#{{ job.id }}</td>
                                    <td class="px-6 py-4">
                                        <div class="flex flex-col">
                                            <span class="font-medium">{{ job.form_type }}</span>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 text-slate-600 dark:text-slate-400">{{ job.method }}</td>
                                    <td class="px-6 py-4">
                                        <Badge variant="secondary" :class="['flex w-fit items-center gap-1 px-2 py-0.5 font-normal', getStatusColor(job.status)]">
                                            <component :is="getStatusIcon(job.status)" class="h-3 w-3" />
                                            {{ job.status.charAt(0) + job.status.slice(1) }}
                                        </Badge>
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="text-xs text-slate-500">
                                            <span class="font-bold text-teal-600">{{ job.counts?.success || 0 }}</span> / {{ job.counts?.total || 0 }}
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 text-slate-500">{{ new Date(job.created_at).toLocaleDateString() }}</td>
                                    <td class="px-6 py-4 text-slate-500">{{ job.user.name }}</td>
                                    <td class="px-6 py-4 text-right">
                                        <Link :href="showJobRoute.url(job.id)">
                                            <Button variant="ghost" size="sm" class="h-8 w-8 p-0">
                                                <ArrowUpRight class="h-4 w-4 text-slate-400" />
                                            </Button>
                                        </Link>
                                    </td>
                                </tr>
                                <tr v-if="jobs.data.length === 0">
                                    <td colspan="8" class="px-6 py-12 text-center text-slate-500 dark:text-slate-400">
                                        <div class="flex flex-col items-center gap-2">
                                            <Clock class="h-8 w-8 text-slate-300" />
                                            <p>No reporting jobs found. Create your first job to get started.</p>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </Card>
                
                <!-- Simple Pagination Info (Mockup for now) -->
                <div class="flex items-center justify-between text-xs text-slate-500 px-2">
                    <p>Showing 1 to {{ jobs.data.length }} of {{ jobs.data.length }} jobs</p>
                    <div class="flex gap-2">
                        <Button variant="outline" size="sm" disabled class="h-8">Previous</Button>
                        <Button variant="outline" size="sm" disabled class="h-8">Next</Button>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
