<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import {
    ArrowLeft,
    Building2,
    Phone,
    Mail,
    MapPin,
    Globe,
    Users,
    Shield,
    Calendar,
    Hash,
    ExternalLink,
    Clock,
} from 'lucide-vue-next';
import { computed } from 'vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';

interface NetworkType {
    id: number;
    type_code: string;
    type_name: string;
}

interface Coordinator {
    id: number;
    name: string | null;
    email: string | null;
    phone: string | null;
    mobile: string | null;
    fax: string | null;
    department: string | null;
}

interface Provider {
    id: number;
    hcode: string;
    name: string;
    registration_type: string | null;
    affiliation: string | null;
    phone: string | null;
    website: string | null;
    service_plan_level: string | null;
    operating_hours: string | null;
    address_no: string | null;
    moo: string | null;
    soi: string | null;
    road: string | null;
    subdistrict: string | null;
    district: string | null;
    province: string | null;
    postal_code: string | null;
    local_admin_area: string | null;
    uc_phone: string | null;
    quality_phone: string | null;
    referral_phone: string | null;
    uc_fax: string | null;
    uc_email: string | null;
    doc_email: string | null;
    cpp_last_updated: string | null;
    scraped_at: string | null;
    network_types: NetworkType[];
    coordinators: Coordinator[];
}

const props = defineProps<{
    provider: Provider;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'หน่วยบริการ CPP', href: '/cpp-providers' },
    { title: props.provider.name, href: `/cpp-providers/${props.provider.hcode}` },
];

const fullAddress = computed(() => {
    const p = props.provider;
    const parts: string[] = [];

    if (p.address_no) {
        parts.push(`เลขที่ ${p.address_no}`);
    }

    if (p.moo) {
        parts.push(`หมู่ ${p.moo}`);
    }

    if (p.soi) {
        parts.push(`ซอย ${p.soi}`);
    }

    if (p.road) {
        parts.push(`ถนน ${p.road}`);
    }

    if (p.subdistrict) {
        parts.push(`ตำบล${p.subdistrict}`);
    }

    if (p.district) {
        parts.push(`อำเภอ${p.district}`);
    }

    if (p.province) {
        parts.push(`จังหวัด${p.province}`);
    }

    if (p.postal_code) {
        parts.push(p.postal_code);
    }

    return parts.join(' ');
});

const isHivRelated = computed(() =>
    props.provider.network_types.some((n) => n.type_code === 'R0216') ||
    /ฟ้าสีรุ้ง|เอ็มพลัส|แคร์แมท|สวิง|ซิสเตอร์|เอ็มเฟรนด์/.test(props.provider.name)
);

const cppUrl = computed(() => `https://cpp.nhso.go.th/profile/?hcode=${props.provider.hcode}`);
</script>

<template>
    <Head :title="provider.name" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-4 p-4 md:p-6">
            <!-- Header -->
            <div class="flex flex-col gap-3">
                <Button as-child variant="ghost" size="sm" class="w-fit">
                    <Link href="/cpp-providers">
                        <ArrowLeft class="mr-1 h-4 w-4" />
                        กลับสู่รายการ
                    </Link>
                </Button>

                <div class="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
                    <div class="flex flex-col gap-2">
                        <div class="flex items-center gap-2">
                            <Hash class="h-4 w-4 text-slate-400" />
                            <span class="font-mono text-sm font-medium text-slate-500 dark:text-slate-400">
                                {{ provider.hcode }}
                            </span>
                            <Badge v-if="isHivRelated" class="bg-rose-100 text-rose-700 dark:bg-rose-950 dark:text-rose-300" variant="secondary">
                                🎯 HIV Ecosystem
                            </Badge>
                        </div>
                        <h1 class="text-2xl font-bold tracking-tight text-slate-900 dark:text-white md:text-3xl">
                            {{ provider.name }}
                        </h1>
                        <div class="flex flex-wrap items-center gap-2">
                            <Badge v-if="provider.affiliation" variant="outline">
                                {{ provider.affiliation }}
                            </Badge>
                            <Badge v-if="provider.registration_type" variant="outline" class="text-xs">
                                {{ provider.registration_type }}
                            </Badge>
                        </div>
                    </div>
                    <Button as-child variant="outline" size="sm">
                        <a :href="cppUrl" target="_blank" rel="noopener noreferrer">
                            <ExternalLink class="mr-1 h-4 w-4" />
                            ดูใน CPP ของ สปสช.
                        </a>
                    </Button>
                </div>
            </div>

            <div class="grid gap-4 lg:grid-cols-3">
                <!-- General info -->
                <Card class="lg:col-span-2">
                    <CardHeader>
                        <CardTitle class="flex items-center gap-2 text-lg">
                            <Building2 class="h-5 w-5 text-teal-500" />
                            ข้อมูลพื้นฐาน
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        <dl class="grid gap-y-3 gap-x-4 text-sm md:grid-cols-2">
                            <div>
                                <dt class="text-xs font-medium text-slate-500 uppercase">ชื่อ</dt>
                                <dd class="mt-0.5 text-slate-900 dark:text-white">{{ provider.name }}</dd>
                            </div>
                            <div>
                                <dt class="text-xs font-medium text-slate-500 uppercase">รหัสหน่วยบริการ</dt>
                                <dd class="mt-0.5 font-mono text-slate-900 dark:text-white">{{ provider.hcode }}</dd>
                            </div>
                            <div>
                                <dt class="text-xs font-medium text-slate-500 uppercase">สังกัด</dt>
                                <dd class="mt-0.5 text-slate-900 dark:text-white">{{ provider.affiliation ?? '-' }}</dd>
                            </div>
                            <div>
                                <dt class="text-xs font-medium text-slate-500 uppercase">ประเภทการขึ้นทะเบียน</dt>
                                <dd class="mt-0.5 text-slate-900 dark:text-white">{{ provider.registration_type ?? '-' }}</dd>
                            </div>
                            <div>
                                <dt class="text-xs font-medium text-slate-500 uppercase">เบอร์โทรศัพท์</dt>
                                <dd class="mt-0.5 text-slate-900 dark:text-white">
                                    <a v-if="provider.phone" :href="`tel:${provider.phone}`" class="text-teal-600 hover:underline">
                                        {{ provider.phone }}
                                    </a>
                                    <span v-else>-</span>
                                </dd>
                            </div>
                            <div>
                                <dt class="text-xs font-medium text-slate-500 uppercase">เว็บไซต์</dt>
                                <dd class="mt-0.5">
                                    <a
                                        v-if="provider.website"
                                        :href="provider.website"
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        class="text-teal-600 hover:underline"
                                    >
                                        {{ provider.website }}
                                    </a>
                                    <span v-else class="text-slate-500">-</span>
                                </dd>
                            </div>
                            <div>
                                <dt class="text-xs font-medium text-slate-500 uppercase">Service Plan Level</dt>
                                <dd class="mt-0.5 text-slate-900 dark:text-white">{{ provider.service_plan_level ?? '-' }}</dd>
                            </div>
                            <div>
                                <dt class="text-xs font-medium text-slate-500 uppercase">เวลาเปิดให้บริการ</dt>
                                <dd class="mt-0.5 text-slate-900 dark:text-white">{{ provider.operating_hours ?? '-' }}</dd>
                            </div>
                        </dl>
                    </CardContent>
                </Card>

                <!-- Summary card -->
                <Card>
                    <CardHeader>
                        <CardTitle class="text-lg">สรุป</CardTitle>
                    </CardHeader>
                    <CardContent class="space-y-3">
                        <div class="flex items-center justify-between text-sm">
                            <span class="text-slate-500">ประเภทบริการ</span>
                            <span class="font-semibold">{{ provider.network_types.length }}</span>
                        </div>
                        <div class="flex items-center justify-between text-sm">
                            <span class="text-slate-500">ผู้ประสานงาน</span>
                            <span class="font-semibold">{{ provider.coordinators.length }}</span>
                        </div>
                        <div class="flex items-center justify-between text-sm">
                            <span class="text-slate-500">อัพเดทล่าสุด (CPP)</span>
                            <span class="font-semibold">{{ provider.cpp_last_updated ?? '-' }}</span>
                        </div>
                        <div class="flex items-center justify-between text-sm">
                            <span class="text-slate-500">Scraped at</span>
                            <span class="font-semibold text-xs">
                                {{ provider.scraped_at ? new Date(provider.scraped_at).toLocaleDateString('th-TH') : '-' }}
                            </span>
                        </div>
                    </CardContent>
                </Card>
            </div>

            <!-- Address -->
            <Card>
                <CardHeader>
                    <CardTitle class="flex items-center gap-2 text-lg">
                        <MapPin class="h-5 w-5 text-teal-500" />
                        ที่อยู่
                    </CardTitle>
                </CardHeader>
                <CardContent>
                    <p class="mb-4 text-base text-slate-900 dark:text-white">
                        {{ fullAddress || '-' }}
                    </p>
                    <dl class="grid gap-y-2 gap-x-4 text-sm md:grid-cols-4">
                        <div>
                            <dt class="text-xs font-medium text-slate-500 uppercase">เลขที่</dt>
                            <dd class="text-slate-900 dark:text-white">{{ provider.address_no ?? '-' }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs font-medium text-slate-500 uppercase">หมู่</dt>
                            <dd class="text-slate-900 dark:text-white">{{ provider.moo ?? '-' }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs font-medium text-slate-500 uppercase">ซอย</dt>
                            <dd class="text-slate-900 dark:text-white">{{ provider.soi ?? '-' }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs font-medium text-slate-500 uppercase">ถนน</dt>
                            <dd class="text-slate-900 dark:text-white">{{ provider.road ?? '-' }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs font-medium text-slate-500 uppercase">ตำบล</dt>
                            <dd class="text-slate-900 dark:text-white">{{ provider.subdistrict ?? '-' }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs font-medium text-slate-500 uppercase">อำเภอ</dt>
                            <dd class="text-slate-900 dark:text-white">{{ provider.district ?? '-' }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs font-medium text-slate-500 uppercase">จังหวัด</dt>
                            <dd class="text-slate-900 dark:text-white">{{ provider.province ?? '-' }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs font-medium text-slate-500 uppercase">รหัสไปรษณีย์</dt>
                            <dd class="text-slate-900 dark:text-white">{{ provider.postal_code ?? '-' }}</dd>
                        </div>
                    </dl>
                </CardContent>
            </Card>

            <!-- Network Types -->
            <Card>
                <CardHeader>
                    <CardTitle class="flex items-center gap-2 text-lg">
                        <Shield class="h-5 w-5 text-teal-500" />
                        ประเภทหน่วยบริการ ({{ provider.network_types.length }})
                    </CardTitle>
                    <CardDescription>
                        รายการประเภทการขึ้นทะเบียนตามระบบ CPP ของ สปสช.
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    <div v-if="provider.network_types.length === 0" class="text-sm text-slate-500">
                        ไม่มีข้อมูลประเภทหน่วยบริการ
                    </div>
                    <div v-else class="space-y-2">
                        <div
                            v-for="nt in provider.network_types"
                            :key="nt.id"
                            class="flex items-start gap-3 rounded-lg border border-slate-200 p-3 dark:border-slate-800"
                        >
                            <Badge variant="outline" class="font-mono">
                                {{ nt.type_code }}
                            </Badge>
                            <div class="flex-1 text-sm text-slate-700 dark:text-slate-300">
                                {{ nt.type_name }}
                            </div>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <!-- UC Contacts -->
            <Card>
                <CardHeader>
                    <CardTitle class="flex items-center gap-2 text-lg">
                        <Phone class="h-5 w-5 text-teal-500" />
                        ข้อมูลติดต่อ UC
                    </CardTitle>
                </CardHeader>
                <CardContent>
                    <dl class="grid gap-y-3 gap-x-4 text-sm md:grid-cols-2">
                        <div>
                            <dt class="text-xs font-medium text-slate-500 uppercase">เบอร์โทรศูนย์บริการหลักประกันสุขภาพ</dt>
                            <dd class="mt-0.5 text-slate-900 dark:text-white">{{ provider.uc_phone ?? '-' }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs font-medium text-slate-500 uppercase">เบอร์โทรงานประกันคุณภาพ</dt>
                            <dd class="mt-0.5 text-slate-900 dark:text-white">{{ provider.quality_phone ?? '-' }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs font-medium text-slate-500 uppercase">เบอร์โทรศูนย์ประสานงานการส่งต่อ</dt>
                            <dd class="mt-0.5 text-slate-900 dark:text-white">{{ provider.referral_phone ?? '-' }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs font-medium text-slate-500 uppercase">โทรสาร UC</dt>
                            <dd class="mt-0.5 text-slate-900 dark:text-white">{{ provider.uc_fax ?? '-' }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs font-medium text-slate-500 uppercase">Email UC</dt>
                            <dd class="mt-0.5">
                                <a v-if="provider.uc_email" :href="`mailto:${provider.uc_email}`" class="text-teal-600 hover:underline">
                                    {{ provider.uc_email }}
                                </a>
                                <span v-else class="text-slate-500">-</span>
                            </dd>
                        </div>
                        <div>
                            <dt class="text-xs font-medium text-slate-500 uppercase">Email งานสารบรรณ</dt>
                            <dd class="mt-0.5">
                                <a v-if="provider.doc_email" :href="`mailto:${provider.doc_email}`" class="text-teal-600 hover:underline">
                                    {{ provider.doc_email }}
                                </a>
                                <span v-else class="text-slate-500">-</span>
                            </dd>
                        </div>
                    </dl>
                </CardContent>
            </Card>

            <!-- Coordinators -->
            <Card>
                <CardHeader>
                    <CardTitle class="flex items-center gap-2 text-lg">
                        <Users class="h-5 w-5 text-teal-500" />
                        ผู้ประสานงาน ({{ provider.coordinators.length }})
                    </CardTitle>
                </CardHeader>
                <CardContent>
                    <div v-if="provider.coordinators.length === 0" class="text-sm text-slate-500">
                        ไม่มีข้อมูลผู้ประสานงาน
                    </div>
                    <div v-else class="grid gap-3 md:grid-cols-2">
                        <div
                            v-for="c in provider.coordinators"
                            :key="c.id"
                            class="rounded-lg border border-slate-200 p-3 dark:border-slate-800"
                        >
                            <div class="font-medium text-slate-900 dark:text-white">
                                {{ c.name ?? '(ไม่มีชื่อ)' }}
                            </div>
                            <div v-if="c.department" class="mt-0.5 text-xs text-slate-500">
                                {{ c.department }}
                            </div>
                            <div class="mt-2 space-y-1 text-sm">
                                <div v-if="c.email" class="flex items-center gap-2 text-slate-600 dark:text-slate-400">
                                    <Mail class="h-3.5 w-3.5" />
                                    <a :href="`mailto:${c.email}`" class="text-teal-600 hover:underline">
                                        {{ c.email }}
                                    </a>
                                </div>
                                <div v-if="c.phone" class="flex items-center gap-2 text-slate-600 dark:text-slate-400">
                                    <Phone class="h-3.5 w-3.5" />
                                    <a :href="`tel:${c.phone}`" class="hover:underline">{{ c.phone }}</a>
                                </div>
                                <div v-if="c.mobile" class="flex items-center gap-2 text-slate-600 dark:text-slate-400">
                                    <Phone class="h-3.5 w-3.5" />
                                    <a :href="`tel:${c.mobile}`" class="hover:underline">{{ c.mobile }}</a>
                                    <span class="text-xs text-slate-400">(มือถือ)</span>
                                </div>
                                <div v-if="c.fax" class="text-xs text-slate-500">
                                    Fax: {{ c.fax }}
                                </div>
                            </div>
                        </div>
                    </div>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>
