<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import {
    Activity,
    CheckCircle2,
    ChevronLeft,
    ChevronRight,
    Download,
    ExternalLink,
    FileText,
    Filter,
    Search,
    TrendingUp,
    XCircle,
    Plus,
    Monitor,
    X,
} from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import SearchableSelect from '@/components/SearchableSelect.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';

interface Summary {
    total_jobs: number;
    total_records: number;
    total_success: number;
    total_failed: number;
    success_rate: number;
    period: string;
    date_range: { from: string; to: string };
}

interface HistoryRow {
    id: number;
    job_id: string;
    site: string;
    form_type: string;
    fy: string;
    total: number;
    success: number;
    failed: number;
    status: string;
    started_at: string | null;
    finished_at: string | null;
    created_at: string;
}

interface PaginatedHistory {
    data: HistoryRow[];
    current_page: number;
    last_page: number;
    total: number;
    from: number | null;
    to: number | null;
    links: Array<{ url: string | null; label: string; active: boolean }>;
}

interface Template {
    name: string;
    filename: string;
    description: string;
}

const props = defineProps<{
    summary: Summary;
    history: PaginatedHistory;
    facets: {
        sites: string[];
        form_types: string[];
    };
    filters: {
        q?: string;
        form_type?: string;
        status?: string;
        period?: string;
    };
    templates: Template[];
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
];

const q = ref(props.filters.q ?? '');
const formType = ref(props.filters.form_type ?? '');
const status = ref(props.filters.status ?? '');
const period = ref(props.filters.period ?? 'month');

const periodOptions = [
    { value: 'today', label: 'วันนี้' },
    { value: 'yesterday', label: 'เมื่อวาน' },
    { value: 'week', label: 'สัปดาห์นี้' },
    { value: 'month', label: 'เดือนนี้' },
    { value: 'all', label: 'ทั้งหมด' },
];

const formTypeOptions = computed(() =>
    props.facets.form_types.map((t) => ({ value: t, label: t }))
);

const statusOptions = [
    { value: 'completed', label: '✅ completed' },
    { value: 'running', label: '🔄 running' },
    { value: 'pending', label: '⏳ pending' },
    { value: 'failed', label: '❌ failed' },
];

let timer: ReturnType<typeof setTimeout> | null = null;

function applyFilters() {
    router.get(
        '/dashboard',
        {
            q: q.value || undefined,
            form_type: formType.value || undefined,
            status: status.value || undefined,
            period: period.value,
        },
        { preserveState: true, preserveScroll: true, replace: true }
    );
}

watch(q, () => {
    if (timer) {
        clearTimeout(timer);
    }

    timer = setTimeout(applyFilters, 400);
});

watch([formType, status, period], () => applyFilters());

function resetFilters() {
    q.value = '';
    formType.value = '';
    status.value = '';
    period.value = 'month';
}

const hasActiveFilter = computed(
    () => !!q.value || !!formType.value || !!status.value || period.value !== 'month'
);

function statusBadgeClass(s: string): string {
    const map: Record<string, string> = {
        completed: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300',
        running: 'bg-blue-100 text-blue-700 dark:bg-blue-950 dark:text-blue-300',
        pending: 'bg-amber-100 text-amber-700 dark:bg-amber-950 dark:text-amber-300',
        failed: 'bg-rose-100 text-rose-700 dark:bg-rose-950 dark:text-rose-300',
    };
    return map[s] ?? 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300';
}

function formatDuration(start: string | null, end: string | null): string {
    if (!start || !end) {
        return '-';
    }

    const diff = (new Date(end).getTime() - new Date(start).getTime()) / 1000;

    if (diff < 60) {
        return `${Math.round(diff)} วิ`;
    }

    if (diff < 3600) {
        return `${Math.round(diff / 60)} นาที`;
    }

    return `${(diff / 3600).toFixed(1)} ชม.`;
}

function formatDate(s: string | null): string {
    if (!s) {
        return '-';
    }

    return new Date(s).toLocaleDateString('th-TH', {
        day: '2-digit',
        month: 'short',
        year: '2-digit',
    });
}
</script>

<template>
    <Head title="Dashboard" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6 p-4 md:p-6">
            <!-- Header + CTAs -->
            <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                <div>
                    <h1 class="text-2xl font-bold tracking-tight text-slate-900 dark:text-white">
                        Dashboard
                    </h1>
                    <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                        สรุปการทำงานของ AutoNAP และประวัติ request ย้อนหลัง
                    </p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <Button as-child variant="outline">
                        <a href="/autonap" target="_blank" rel="noopener">
                            <Monitor class="mr-1.5 h-4 w-4" />
                            Realtime Monitor
                            <ExternalLink class="ml-1 h-3 w-3" />
                        </a>
                    </Button>
                    <Button as-child>
                        <a href="/jobs">
                            <Plus class="mr-1.5 h-4 w-4" />
                            ส่ง Request ใหม่
                        </a>
                    </Button>
                </div>
            </div>

            <!-- Summary Cards -->
            <div class="grid gap-4 md:grid-cols-4">
                <Card>
                    <CardContent class="p-5">
                        <div class="flex items-center justify-between">
                            <div class="text-xs font-medium text-slate-500 uppercase">
                                Total Jobs
                            </div>
                            <Activity class="h-4 w-4 text-teal-500" />
                        </div>
                        <div class="mt-2 text-3xl font-bold text-slate-900 dark:text-white">
                            {{ summary.total_jobs.toLocaleString() }}
                        </div>
                        <div class="mt-1 text-xs text-slate-500">
                            {{ summary.date_range.from }} → {{ summary.date_range.to }}
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardContent class="p-5">
                        <div class="flex items-center justify-between">
                            <div class="text-xs font-medium text-slate-500 uppercase">
                                Total Records
                            </div>
                            <FileText class="h-4 w-4 text-blue-500" />
                        </div>
                        <div class="mt-2 text-3xl font-bold text-slate-900 dark:text-white">
                            {{ summary.total_records.toLocaleString() }}
                        </div>
                        <div class="mt-1 text-xs text-slate-500">
                            บันทึกเข้า NAP ทั้งหมด
                        </div>
                    </CardContent>
                </Card>

                <Card class="border-emerald-200 bg-emerald-50/50 dark:border-emerald-900 dark:bg-emerald-950/10">
                    <CardContent class="p-5">
                        <div class="flex items-center justify-between">
                            <div class="text-xs font-medium text-emerald-700 dark:text-emerald-400 uppercase">
                                Success Rate
                            </div>
                            <TrendingUp class="h-4 w-4 text-emerald-500" />
                        </div>
                        <div class="mt-2 text-3xl font-bold text-emerald-600 dark:text-emerald-400">
                            {{ summary.success_rate }}%
                        </div>
                        <div class="mt-1 text-xs text-slate-500">
                            {{ summary.total_success.toLocaleString() }} สำเร็จ
                        </div>
                    </CardContent>
                </Card>

                <Card :class="summary.total_failed > 0 ? 'border-rose-200 bg-rose-50/50 dark:border-rose-900 dark:bg-rose-950/10' : ''">
                    <CardContent class="p-5">
                        <div class="flex items-center justify-between">
                            <div class="text-xs font-medium text-slate-500 uppercase">
                                Failed
                            </div>
                            <XCircle class="h-4 w-4 text-rose-500" />
                        </div>
                        <div
                            class="mt-2 text-3xl font-bold"
                            :class="summary.total_failed > 0 ? 'text-rose-600 dark:text-rose-400' : 'text-slate-900 dark:text-white'"
                        >
                            {{ summary.total_failed.toLocaleString() }}
                        </div>
                        <div class="mt-1 text-xs text-slate-500">
                            จาก {{ summary.total_records.toLocaleString() }} records
                        </div>
                    </CardContent>
                </Card>
            </div>

            <!-- CSV Templates Section -->
            <Card>
                <CardHeader>
                    <CardTitle class="flex items-center gap-2 text-lg">
                        <Download class="h-5 w-5 text-teal-500" />
                        CSV Templates
                    </CardTitle>
                    <CardDescription>
                        ดาวน์โหลด template สำหรับเตรียมข้อมูลก่อน upload
                        (UTF-8 BOM, Excel-ready)
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    <div class="grid gap-3 md:grid-cols-2">
                        <a
                            v-for="tpl in templates"
                            :key="tpl.filename"
                            :href="`/dashboard/templates/${tpl.filename}`"
                            class="group flex items-center gap-3 rounded-lg border border-slate-200 p-4 transition-colors hover:border-teal-400 hover:bg-teal-50/30 dark:border-slate-800 dark:hover:border-teal-700 dark:hover:bg-teal-950/10"
                            download
                        >
                            <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-teal-100 text-teal-600 dark:bg-teal-950 dark:text-teal-400">
                                <FileText class="h-5 w-5" />
                            </div>
                            <div class="flex-1 min-w-0">
                                <div class="font-semibold text-slate-900 group-hover:text-teal-700 dark:text-white dark:group-hover:text-teal-400">
                                    {{ tpl.name }}
                                </div>
                                <div class="text-xs text-slate-500">
                                    {{ tpl.description }}
                                </div>
                                <div class="mt-1 font-mono text-xs text-slate-400">
                                    {{ tpl.filename }}
                                </div>
                            </div>
                            <Download class="h-4 w-4 text-slate-400 group-hover:text-teal-500" />
                        </a>
                    </div>
                </CardContent>
            </Card>

            <!-- History + Filters -->
            <Card>
                <CardHeader>
                    <CardTitle class="text-lg">ประวัติ Request</CardTitle>
                    <CardDescription>
                        ตรวจสอบ request ย้อนหลัง, สถานะ, และสถิติของแต่ละ job
                    </CardDescription>
                </CardHeader>
                <CardContent class="space-y-4">
                    <!-- Filters -->
                    <div class="grid gap-3 md:grid-cols-4">
                        <div class="md:col-span-2">
                            <div class="relative">
                                <Search class="absolute top-2.5 left-3 h-4 w-4 text-slate-400" />
                                <Input
                                    v-model="q"
                                    placeholder="ค้นหา site / job_id / form type..."
                                    class="pl-10"
                                />
                            </div>
                        </div>
                        <div>
                            <SearchableSelect
                                v-model="formType"
                                :options="formTypeOptions"
                                placeholder="ทุก form type"
                                search-placeholder="ค้นหา..."
                            />
                        </div>
                        <div>
                            <SearchableSelect
                                v-model="status"
                                :options="statusOptions"
                                placeholder="ทุก status"
                                search-placeholder="ค้นหา..."
                            />
                        </div>
                    </div>

                    <div class="flex flex-wrap items-center gap-2">
                        <span class="text-xs text-slate-500">Period:</span>
                        <Button
                            v-for="opt in periodOptions"
                            :key="opt.value"
                            size="sm"
                            :variant="period === opt.value ? 'default' : 'outline'"
                            @click="period = opt.value"
                        >
                            {{ opt.label }}
                        </Button>
                        <div class="flex-1"></div>
                        <Badge v-if="hasActiveFilter" variant="outline">
                            <Filter class="mr-1 h-3 w-3" /> Filter active
                        </Badge>
                        <Button v-if="hasActiveFilter" size="sm" variant="ghost" @click="resetFilters">
                            <X class="mr-1 h-4 w-4" />
                            ล้าง
                        </Button>
                    </div>

                    <!-- Table -->
                    <div class="overflow-x-auto rounded-lg border border-slate-200 dark:border-slate-800">
                        <table class="w-full text-sm">
                            <thead class="border-b border-slate-200 bg-slate-50 text-left text-xs font-medium text-slate-500 uppercase dark:border-slate-800 dark:bg-slate-900/50">
                                <tr>
                                    <th class="px-3 py-2">Job ID</th>
                                    <th class="px-3 py-2">Site</th>
                                    <th class="px-3 py-2">Form</th>
                                    <th class="px-3 py-2 text-right">Total</th>
                                    <th class="px-3 py-2 text-right">Success</th>
                                    <th class="px-3 py-2 text-right">Failed</th>
                                    <th class="px-3 py-2">Status</th>
                                    <th class="px-3 py-2">Date</th>
                                    <th class="px-3 py-2 text-right">Duration</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr
                                    v-for="row in history.data"
                                    :key="row.id"
                                    class="border-b border-slate-100 hover:bg-slate-50 dark:border-slate-800 dark:hover:bg-slate-900/50"
                                >
                                    <td class="px-3 py-2 font-mono text-xs text-slate-700 dark:text-slate-300">
                                        {{ row.job_id }}
                                    </td>
                                    <td class="px-3 py-2 font-medium">{{ row.site }}</td>
                                    <td class="px-3 py-2">
                                        <Badge variant="outline" class="text-xs">{{ row.form_type }}</Badge>
                                    </td>
                                    <td class="px-3 py-2 text-right font-mono">{{ row.total }}</td>
                                    <td class="px-3 py-2 text-right font-mono text-emerald-600">{{ row.success }}</td>
                                    <td
                                        class="px-3 py-2 text-right font-mono"
                                        :class="row.failed > 0 ? 'text-rose-600 font-semibold' : ''"
                                    >
                                        {{ row.failed }}
                                    </td>
                                    <td class="px-3 py-2">
                                        <Badge :class="statusBadgeClass(row.status)" variant="outline">
                                            {{ row.status }}
                                        </Badge>
                                    </td>
                                    <td class="px-3 py-2 text-xs text-slate-500">
                                        {{ formatDate(row.started_at || row.created_at) }}
                                    </td>
                                    <td class="px-3 py-2 text-right text-xs text-slate-500">
                                        {{ formatDuration(row.started_at, row.finished_at) }}
                                    </td>
                                </tr>
                                <tr v-if="history.data.length === 0">
                                    <td colspan="9" class="px-3 py-12 text-center text-sm text-slate-500">
                                        <FileText class="mx-auto mb-2 h-8 w-8 text-slate-300" />
                                        ยังไม่มี request ในช่วงเวลานี้
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination -->
                    <div v-if="history.last_page > 1" class="flex items-center justify-between">
                        <div class="text-xs text-slate-500">
                            {{ history.from }}-{{ history.to }} จาก {{ history.total.toLocaleString() }}
                        </div>
                        <div class="flex items-center gap-1">
                            <Button
                                v-for="(link, i) in history.links"
                                :key="i"
                                as-child
                                size="sm"
                                :variant="link.active ? 'default' : 'outline'"
                                :disabled="!link.url"
                                class="min-w-9"
                            >
                                <Link v-if="link.url" :href="link.url" preserve-scroll>
                                    <ChevronLeft v-if="link.label.includes('Previous')" class="h-4 w-4" />
                                    <ChevronRight v-else-if="link.label.includes('Next')" class="h-4 w-4" />
                                    <span v-else v-html="link.label"></span>
                                </Link>
                                <span v-else class="opacity-40"><span v-html="link.label"></span></span>
                            </Button>
                        </div>
                    </div>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>
