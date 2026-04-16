<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import {
    Search,
    Filter,
    MapPin,
    Phone,
    Mail,
    Building2,
    Users,
    ChevronLeft,
    ChevronRight,
    X,
    ExternalLink,
} from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';
import SearchableSelect from '@/components/SearchableSelect.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';

interface Provider {
    id: number;
    hcode: string;
    name: string;
    affiliation: string | null;
    phone: string | null;
    uc_email: string | null;
    subdistrict: string | null;
    district: string | null;
    province: string | null;
    postal_code: string | null;
    coordinators_count: number;
}

interface PaginatedProviders {
    data: Provider[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number | null;
    to: number | null;
    links: Array<{ url: string | null; label: string; active: boolean }>;
}

interface TypeCode {
    code: string;
    name: string;
    count: number;
}

const props = defineProps<{
    providers: PaginatedProviders;
    filters: {
        q?: string;
        province?: string;
        district?: string;
        affiliation?: string;
        type_code?: string;
        has_email?: boolean;
        has_coordinator?: boolean;
        hiv_only?: boolean;
        sort?: string;
        per_page?: number;
    };
    facets: {
        provinces: Record<string, number>;
        affiliations: Record<string, number>;
        type_codes: TypeCode[];
    };
    totals: {
        all: number;
        hiv_ecosystem: number;
    };
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'หน่วยบริการ CPP', href: '/cpp-providers' },
];

const q = ref(props.filters.q ?? '');
const province = ref(props.filters.province ?? '');
const affiliation = ref(props.filters.affiliation ?? '');
const typeCode = ref(props.filters.type_code ?? '');
const hasEmail = ref(!!props.filters.has_email);
const hasCoordinator = ref(!!props.filters.has_coordinator);
const hivOnly = ref(!!props.filters.hiv_only);

// Options for searchable selects
const provinceOptions = computed(() =>
    Object.entries(props.facets.provinces ?? {}).map(([name, count]) => ({
        value: name,
        label: `${name} (${count})`,
    }))
);

const affiliationOptions = computed(() =>
    Object.entries(props.facets.affiliations ?? {}).map(([name, count]) => ({
        value: name,
        label: `${name} (${count})`,
    }))
);

const typeCodeOptions = computed(() =>
    (props.facets.type_codes ?? []).map((t) => ({
        value: t.code,
        label: `[${t.code}] ${(t.name ?? '').substring(0, 60)} (${t.count})`,
        sublabel: t.name && t.name.length > 60 ? t.name : undefined,
    }))
);

const hasActiveFilter = computed(
    () =>
        !!q.value ||
        !!province.value ||
        !!affiliation.value ||
        !!typeCode.value ||
        hasEmail.value ||
        hasCoordinator.value ||
        hivOnly.value
);

let searchTimer: ReturnType<typeof setTimeout> | null = null;

function applyFilters() {
    router.get(
        '/cpp-providers',
        {
            q: q.value || undefined,
            province: province.value || undefined,
            affiliation: affiliation.value || undefined,
            type_code: typeCode.value || undefined,
            has_email: hasEmail.value || undefined,
            has_coordinator: hasCoordinator.value || undefined,
            hiv_only: hivOnly.value || undefined,
        },
        { preserveState: true, preserveScroll: true, replace: true }
    );
}

watch(q, () => {
    if (searchTimer) {
        clearTimeout(searchTimer);
    }

    searchTimer = setTimeout(applyFilters, 400);
});

watch([province, affiliation, typeCode, hasEmail, hasCoordinator, hivOnly], () => {
    applyFilters();
});

function resetFilters() {
    q.value = '';
    province.value = '';
    affiliation.value = '';
    typeCode.value = '';
    hasEmail.value = false;
    hasCoordinator.value = false;
    hivOnly.value = false;
}

function formatAddress(p: Provider): string {
    const parts = [p.subdistrict, p.district, p.province, p.postal_code].filter(Boolean);

    return parts.join(' ');
}

function affiliationColor(a: string | null): string {
    if (!a) {
        return 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300';
    }

    if (a.includes('เอกชน')) {
        return 'bg-blue-100 text-blue-700 dark:bg-blue-950 dark:text-blue-300';
    }

    if (a.includes('สธ')) {
        return 'bg-green-100 text-green-700 dark:bg-green-950 dark:text-green-300';
    }

    return 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300';
}
</script>

<template>
    <Head title="หน่วยบริการ CPP" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-4 p-4 md:p-6">
            <!-- Header + totals -->
            <div class="flex flex-col gap-2 md:flex-row md:items-end md:justify-between">
                <div>
                    <h1 class="text-2xl font-bold tracking-tight text-slate-900 dark:text-white">
                        หน่วยบริการ CPP
                    </h1>
                    <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                        ฐานข้อมูลหน่วยบริการภายใต้เครือข่าย สปสช. ทั้งหมด {{ totals.all.toLocaleString() }} แห่ง
                        · กลุ่ม HIV ecosystem {{ totals.hiv_ecosystem }} แห่ง
                    </p>
                </div>
                <div class="flex items-center gap-2 text-sm text-slate-500 dark:text-slate-400">
                    <Filter class="h-4 w-4" />
                    แสดง <strong class="text-slate-900 dark:text-white">{{ providers.total.toLocaleString() }}</strong> รายการ
                </div>
            </div>

            <!-- Filter Panel -->
            <Card>
                <CardContent class="p-4">
                    <div class="grid gap-3 md:grid-cols-4">
                        <!-- Search -->
                        <div class="md:col-span-2">
                            <Label class="mb-1 text-xs">ค้นหา (ชื่อ / รหัส / เบอร์)</Label>
                            <div class="relative">
                                <Search class="absolute top-2.5 left-3 h-4 w-4 text-slate-400" />
                                <Input
                                    v-model="q"
                                    placeholder="เช่น แคร์แมท, 41936, 0812..."
                                    class="pl-10"
                                />
                            </div>
                        </div>

                        <!-- Province -->
                        <div>
                            <Label class="mb-1 text-xs">
                                จังหวัด
                                <span class="text-slate-400">({{ provinceOptions.length }})</span>
                            </Label>
                            <SearchableSelect
                                v-model="province"
                                :options="provinceOptions"
                                placeholder="ทุกจังหวัด"
                                search-placeholder="พิมพ์ค้นหาจังหวัด..."
                            />
                        </div>

                        <!-- Affiliation -->
                        <div>
                            <Label class="mb-1 text-xs">
                                สังกัด
                                <span class="text-slate-400">({{ affiliationOptions.length }})</span>
                            </Label>
                            <SearchableSelect
                                v-model="affiliation"
                                :options="affiliationOptions"
                                placeholder="ทุกสังกัด"
                                search-placeholder="พิมพ์ค้นหาสังกัด..."
                            />
                        </div>
                    </div>

                    <div class="mt-3 grid gap-3 md:grid-cols-4">
                        <!-- Type code -->
                        <div class="md:col-span-2">
                            <Label class="mb-1 text-xs">
                                ประเภทหน่วยบริการ
                                <span class="text-slate-400">({{ typeCodeOptions.length }})</span>
                            </Label>
                            <SearchableSelect
                                v-model="typeCode"
                                :options="typeCodeOptions"
                                placeholder="ทุกประเภท"
                                search-placeholder="พิมพ์ค้นหา เช่น HIV, เวชกรรม..."
                            />
                        </div>

                        <!-- Checkboxes -->
                        <div class="flex flex-col gap-2 md:col-span-2">
                            <Label class="mb-1 text-xs">ตัวกรองเพิ่มเติม</Label>
                            <div class="flex flex-wrap items-center gap-4">
                                <div class="flex items-center gap-2">
                                    <Checkbox id="hiv_only" v-model="hivOnly" />
                                    <label for="hiv_only" class="cursor-pointer text-sm">
                                        🎯 HIV ecosystem เท่านั้น
                                    </label>
                                </div>
                                <div class="flex items-center gap-2">
                                    <Checkbox id="has_email" v-model="hasEmail" />
                                    <label for="has_email" class="cursor-pointer text-sm">
                                        📧 มี email
                                    </label>
                                </div>
                                <div class="flex items-center gap-2">
                                    <Checkbox id="has_coord" v-model="hasCoordinator" />
                                    <label for="has_coord" class="cursor-pointer text-sm">
                                        👥 มีผู้ประสานงาน
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="mt-3 flex flex-wrap items-center justify-between gap-2">
                        <div class="flex flex-wrap items-center gap-2 text-sm">
                            <Badge
                                v-if="hasActiveFilter"
                                class="border-teal-300 bg-teal-50 text-teal-700 dark:border-teal-800 dark:bg-teal-950/40 dark:text-teal-400"
                                variant="outline"
                            >
                                <Filter class="mr-1 h-3 w-3" />
                                Filter active
                            </Badge>
                            <span class="font-medium text-slate-700 dark:text-slate-300">
                                พบ <span class="text-lg font-bold text-teal-600 dark:text-teal-400">{{ providers.total.toLocaleString() }}</span>
                                <span class="text-slate-500">
                                    จาก {{ totals.all.toLocaleString() }} หน่วยบริการ
                                </span>
                            </span>
                        </div>
                        <Button
                            v-if="hasActiveFilter"
                            variant="ghost"
                            size="sm"
                            @click="resetFilters"
                        >
                            <X class="mr-1 h-4 w-4" />
                            ล้างตัวกรอง
                        </Button>
                    </div>
                </CardContent>
            </Card>

            <!-- Results table -->
            <Card>
                <CardContent class="p-0">
                    <div class="overflow-x-auto">
                        <table class="w-full">
                            <thead class="border-b border-slate-200 bg-slate-50 text-left text-xs font-medium text-slate-500 uppercase dark:border-slate-800 dark:bg-slate-900/50">
                                <tr>
                                    <th class="px-4 py-3">Hcode</th>
                                    <th class="px-4 py-3">ชื่อ</th>
                                    <th class="px-4 py-3 hidden md:table-cell">สังกัด</th>
                                    <th class="px-4 py-3 hidden lg:table-cell">ที่อยู่</th>
                                    <th class="px-4 py-3 hidden md:table-cell">ติดต่อ</th>
                                    <th class="px-4 py-3 text-right">ดูรายละเอียด</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr
                                    v-for="p in providers.data"
                                    :key="p.id"
                                    class="border-b border-slate-100 text-sm hover:bg-slate-50 dark:border-slate-800 dark:hover:bg-slate-900/50"
                                >
                                    <td class="px-4 py-3 font-mono text-xs font-medium text-slate-700 dark:text-slate-300">
                                        {{ p.hcode }}
                                    </td>
                                    <td class="px-4 py-3">
                                        <div class="font-medium text-slate-900 dark:text-white">
                                            {{ p.name }}
                                        </div>
                                        <div class="mt-0.5 flex items-center gap-2 text-xs text-slate-500 md:hidden">
                                            <MapPin class="h-3 w-3" />
                                            {{ p.province ?? '-' }}
                                        </div>
                                    </td>
                                    <td class="px-4 py-3 hidden md:table-cell">
                                        <Badge
                                            v-if="p.affiliation"
                                            :class="affiliationColor(p.affiliation)"
                                            class="text-xs"
                                            variant="secondary"
                                        >
                                            {{ p.affiliation }}
                                        </Badge>
                                        <span v-else class="text-xs text-slate-400">-</span>
                                    </td>
                                    <td class="px-4 py-3 hidden lg:table-cell text-xs text-slate-600 dark:text-slate-400">
                                        {{ formatAddress(p) || '-' }}
                                    </td>
                                    <td class="px-4 py-3 hidden md:table-cell text-xs">
                                        <div v-if="p.phone" class="flex items-center gap-1 text-slate-600 dark:text-slate-400">
                                            <Phone class="h-3 w-3" />
                                            {{ p.phone }}
                                        </div>
                                        <div v-if="p.uc_email" class="mt-0.5 flex items-center gap-1 text-slate-500">
                                            <Mail class="h-3 w-3" />
                                            {{ p.uc_email }}
                                        </div>
                                        <div v-if="p.coordinators_count > 0" class="mt-0.5 flex items-center gap-1 text-teal-600">
                                            <Users class="h-3 w-3" />
                                            {{ p.coordinators_count }} ผู้ประสานงาน
                                        </div>
                                    </td>
                                    <td class="px-4 py-3 text-right">
                                        <Button as-child size="sm" variant="outline">
                                            <a
                                                :href="`/cpp-providers/${p.hcode}`"
                                                target="_blank"
                                                rel="noopener"
                                            >
                                                ดู
                                                <ExternalLink class="ml-1 h-3 w-3" />
                                            </a>
                                        </Button>
                                    </td>
                                </tr>

                                <tr v-if="providers.data.length === 0">
                                    <td colspan="6" class="px-4 py-12 text-center text-sm text-slate-500">
                                        ไม่พบหน่วยบริการที่ตรงกับเงื่อนไข
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </CardContent>
            </Card>

            <!-- Pagination -->
            <div v-if="providers.last_page > 1" class="flex flex-col items-center justify-between gap-3 sm:flex-row">
                <div class="text-sm text-slate-500 dark:text-slate-400">
                    {{ providers.from }}-{{ providers.to }} จาก {{ providers.total.toLocaleString() }}
                </div>
                <div class="flex items-center gap-1">
                    <Button
                        v-for="(link, i) in providers.links"
                        :key="i"
                        as-child
                        size="sm"
                        :variant="link.active ? 'default' : 'outline'"
                        :disabled="!link.url"
                        class="min-w-9"
                    >
                        <Link v-if="link.url" :href="link.url" preserve-scroll>
                            <span v-if="link.label.includes('Previous') || link.label === '&laquo; Previous'">
                                <ChevronLeft class="h-4 w-4" />
                            </span>
                            <span v-else-if="link.label.includes('Next') || link.label === 'Next &raquo;'">
                                <ChevronRight class="h-4 w-4" />
                            </span>
                            <span v-else v-html="link.label"></span>
                        </Link>
                        <span v-else class="px-2 opacity-40" v-html="link.label"></span>
                    </Button>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
