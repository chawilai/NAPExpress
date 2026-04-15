<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import {
    AlertTriangle,
    Check,
    Copy,
    Key,
    Plus,
    Trash2,
    Shield,
} from 'lucide-vue-next';
import { ref } from 'vue';
import Heading from '@/components/Heading.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import type { BreadcrumbItem } from '@/types';

interface ApiClient {
    id: number;
    name: string;
    client_id: string;
    client_secret_prefix: string;
    allowed_ips: string | null;
    last_used_at: string | null;
    revoked_at: string | null;
    created_at: string;
    is_active: boolean;
}

interface NewCredentials {
    name: string;
    client_id: string;
    client_secret: string;
}

const props = defineProps<{
    clients: ApiClient[];
    newCredentials: NewCredentials | null;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Settings', href: '/settings/profile' },
    { title: 'API Tokens', href: '/settings/api-tokens' },
];

const createOpen = ref(false);
const credentialsOpen = ref(!!props.newCredentials);
const copiedField = ref<string>('');

const form = useForm({
    name: '',
    allowed_ips: '',
});

function submit() {
    form.post('/settings/api-tokens', {
        preserveScroll: true,
        onSuccess: () => {
            form.reset();
            createOpen.value = false;
            credentialsOpen.value = true;
        },
    });
}

function copy(value: string, field: string) {
    navigator.clipboard.writeText(value);
    copiedField.value = field;
    setTimeout(() => {
        copiedField.value = '';
    }, 2000);
}

function revoke(client: ApiClient) {
    if (!confirm(`ยืนยันการยกเลิก "${client.name}"? การกระทำนี้ไม่สามารถย้อนกลับได้`)) {
        return;
    }

    router.delete(`/settings/api-tokens/${client.id}`, {
        preserveScroll: true,
    });
}

function formatDate(dateStr: string | null): string {
    if (!dateStr) {
        return '-';
    }

    const d = new Date(dateStr);
    return d.toLocaleDateString('th-TH', {
        day: 'numeric',
        month: 'short',
        year: 'numeric',
    });
}

function closeCredentials() {
    credentialsOpen.value = false;
    router.visit('/settings/api-tokens', { preserveScroll: true });
}
</script>

<template>
    <Head title="API Tokens" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <SettingsLayout>
            <div class="space-y-6">
                <Heading
                    title="API Tokens"
                    description="จัดการ API credentials สำหรับระบบภายนอก (เช่น ACTSE Clinic, CAREMAT) เชื่อมต่อกับ AutoNAP"
                />

                <!-- Create button -->
                <div class="flex items-center justify-between">
                    <div class="text-sm text-slate-500 dark:text-slate-400">
                        {{ clients.filter((c) => c.is_active).length }} active tokens
                        · {{ clients.filter((c) => !c.is_active).length }} revoked
                    </div>
                    <Button @click="createOpen = true">
                        <Plus class="mr-1 h-4 w-4" />
                        สร้าง API Client ใหม่
                    </Button>
                </div>

                <!-- Client list -->
                <div v-if="clients.length === 0" class="rounded-lg border border-dashed border-slate-200 p-12 text-center dark:border-slate-800">
                    <Key class="mx-auto h-10 w-10 text-slate-400" />
                    <h3 class="mt-3 text-sm font-semibold text-slate-900 dark:text-white">
                        ยังไม่มี API Client
                    </h3>
                    <p class="mt-1 text-sm text-slate-500">
                        สร้าง API client เพื่อให้ระบบภายนอก (เช่น ACTSE Clinic) เชื่อมต่อกับ AutoNAP
                    </p>
                    <Button class="mt-4" @click="createOpen = true">
                        <Plus class="mr-1 h-4 w-4" />
                        สร้าง API Client แรก
                    </Button>
                </div>

                <div v-else class="space-y-3">
                    <Card
                        v-for="client in clients"
                        :key="client.id"
                        :class="[
                            !client.is_active && 'opacity-60',
                        ]"
                    >
                        <CardContent class="p-4">
                            <div class="flex items-start justify-between gap-4">
                                <div class="min-w-0 flex-1">
                                    <div class="flex items-center gap-2">
                                        <h3 class="font-semibold text-slate-900 dark:text-white">
                                            {{ client.name }}
                                        </h3>
                                        <Badge v-if="client.is_active" class="bg-emerald-100 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-400" variant="outline">
                                            Active
                                        </Badge>
                                        <Badge v-else variant="outline" class="text-slate-500">
                                            Revoked
                                        </Badge>
                                    </div>
                                    <div class="mt-2 space-y-1 font-mono text-xs">
                                        <div class="flex items-center gap-2 text-slate-700 dark:text-slate-300">
                                            <span class="text-slate-500">client_id:</span>
                                            <code class="rounded bg-slate-100 px-2 py-0.5 dark:bg-slate-800">
                                                {{ client.client_id }}
                                            </code>
                                            <button
                                                type="button"
                                                class="text-slate-400 hover:text-slate-700"
                                                title="คัดลอก"
                                                @click="copy(client.client_id, `cid-${client.id}`)"
                                            >
                                                <Check v-if="copiedField === `cid-${client.id}`" class="h-3.5 w-3.5 text-emerald-500" />
                                                <Copy v-else class="h-3.5 w-3.5" />
                                            </button>
                                        </div>
                                        <div class="text-slate-500">
                                            secret: <code>{{ client.client_secret_prefix }}</code> <span class="italic text-slate-400">(ซ่อนไว้)</span>
                                        </div>
                                    </div>
                                    <div class="mt-3 flex flex-wrap gap-x-4 gap-y-1 text-xs text-slate-500">
                                        <span>สร้างเมื่อ: {{ formatDate(client.created_at) }}</span>
                                        <span v-if="client.last_used_at">ใช้ล่าสุด: {{ formatDate(client.last_used_at) }}</span>
                                        <span v-if="client.allowed_ips">🌐 IP: {{ client.allowed_ips }}</span>
                                    </div>
                                </div>
                                <Button
                                    v-if="client.is_active"
                                    variant="ghost"
                                    size="sm"
                                    class="text-rose-600 hover:bg-rose-50 hover:text-rose-700 dark:hover:bg-rose-950/30"
                                    @click="revoke(client)"
                                >
                                    <Trash2 class="mr-1 h-4 w-4" />
                                    Revoke
                                </Button>
                            </div>
                        </CardContent>
                    </Card>
                </div>

                <!-- Documentation link -->
                <Card class="border-teal-200 bg-teal-50/50 dark:border-teal-900 dark:bg-teal-950/10">
                    <CardHeader class="pb-3">
                        <CardTitle class="flex items-center gap-2 text-base">
                            <Shield class="h-4 w-4 text-teal-500" />
                            วิธีใช้งาน API Token
                        </CardTitle>
                    </CardHeader>
                    <CardContent class="space-y-3 text-sm text-slate-700 dark:text-slate-300">
                        <p>
                            API Client ใช้ <strong>OAuth2 Client Credentials flow</strong> —
                            ระบบภายนอกส่ง <code>client_id</code> + <code>client_secret</code>
                            เพื่อแลกเป็น short-lived access token (1 ชั่วโมง) แล้วนำ access token
                            ไปใช้ในการเรียก API
                        </p>
                        <div class="rounded-lg bg-slate-900 p-3 font-mono text-xs text-slate-200">
                            <div class="mb-2 text-teal-400"># 1. ขอ access token</div>
                            <div>curl -X POST https://autonap.actse-clinic.com/api/auth/token \</div>
                            <div>&nbsp;&nbsp;-H "Content-Type: application/json" \</div>
                            <div>&nbsp;&nbsp;-d '{"client_id":"acs_...","client_secret":"acsk_..."}'</div>
                            <div class="mt-2 text-teal-400"># ผลลัพธ์</div>
                            <div>{"access_token":"at_xxx","token_type":"Bearer","expires_in":3600}</div>
                            <div class="mt-3 text-teal-400"># 2. ใช้ access token</div>
                            <div>curl -H "Authorization: Bearer at_xxx" \</div>
                            <div>&nbsp;&nbsp;https://autonap.actse-clinic.com/api/auth/me</div>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </SettingsLayout>
    </AppLayout>

    <!-- Create dialog -->
    <Dialog v-model:open="createOpen">
        <DialogContent>
            <DialogHeader>
                <DialogTitle>สร้าง API Client ใหม่</DialogTitle>
                <DialogDescription>
                    ตั้งชื่อให้จำง่าย เช่น "ACTSE Clinic production"
                    ระบบจะสร้าง client_id + client_secret ให้โดยอัตโนมัติ
                </DialogDescription>
            </DialogHeader>
            <form class="space-y-4" @submit.prevent="submit">
                <div>
                    <Label for="name">ชื่อ <span class="text-rose-500">*</span></Label>
                    <Input
                        id="name"
                        v-model="form.name"
                        placeholder="ACTSE Clinic production"
                        class="mt-1.5"
                        required
                    />
                    <p v-if="form.errors.name" class="mt-1 text-xs text-rose-500">
                        {{ form.errors.name }}
                    </p>
                </div>
                <div>
                    <Label for="allowed_ips">IP Whitelist <span class="text-xs text-slate-500">(ไม่บังคับ)</span></Label>
                    <Input
                        id="allowed_ips"
                        v-model="form.allowed_ips"
                        placeholder="103.117.148.89, 43.229.150.41"
                        class="mt-1.5"
                    />
                    <p class="mt-1 text-xs text-slate-500">
                        คั่นด้วยคอมม่า — ระบบจะปฏิเสธ request จาก IP นอก list นี้
                    </p>
                </div>
                <DialogFooter>
                    <Button type="button" variant="outline" @click="createOpen = false">
                        ยกเลิก
                    </Button>
                    <Button type="submit" :disabled="form.processing">
                        สร้าง
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>

    <!-- Show credentials (ONCE) -->
    <Dialog v-model:open="credentialsOpen">
        <DialogContent class="sm:max-w-xl">
            <DialogHeader>
                <DialogTitle class="flex items-center gap-2">
                    <Check class="h-5 w-5 text-emerald-500" />
                    สร้าง "{{ newCredentials?.name }}" สำเร็จ
                </DialogTitle>
                <DialogDescription>
                    เก็บ client_secret ไว้ให้ปลอดภัย — <strong class="text-rose-600">แสดงเพียงครั้งเดียวเท่านั้น</strong>
                </DialogDescription>
            </DialogHeader>

            <div class="space-y-4">
                <div class="rounded-lg border border-amber-300 bg-amber-50 p-3 dark:border-amber-900 dark:bg-amber-950/20">
                    <div class="flex items-start gap-2 text-sm text-amber-800 dark:text-amber-300">
                        <AlertTriangle class="mt-0.5 h-4 w-4 flex-shrink-0" />
                        <div>
                            <strong>คัดลอกค่าเหล่านี้ไปเก็บไว้</strong> — ปิดหน้าต่างนี้ไปแล้วจะไม่สามารถดู client_secret ได้อีก
                            ถ้าหายต้องสร้างใหม่เท่านั้น
                        </div>
                    </div>
                </div>

                <div>
                    <Label class="text-xs text-slate-500">Client ID</Label>
                    <div class="mt-1 flex items-center gap-2">
                        <code class="flex-1 truncate rounded-md border border-slate-200 bg-slate-50 px-3 py-2 font-mono text-sm dark:border-slate-800 dark:bg-slate-900">
                            {{ newCredentials?.client_id }}
                        </code>
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            @click="copy(newCredentials?.client_id || '', 'new-cid')"
                        >
                            <Check v-if="copiedField === 'new-cid'" class="h-4 w-4 text-emerald-500" />
                            <Copy v-else class="h-4 w-4" />
                        </Button>
                    </div>
                </div>

                <div>
                    <Label class="text-xs text-slate-500">
                        Client Secret
                        <span class="ml-1 font-semibold text-rose-600">⚠️ แสดงครั้งเดียว</span>
                    </Label>
                    <div class="mt-1 flex items-center gap-2">
                        <code class="flex-1 truncate rounded-md border border-amber-300 bg-amber-50 px-3 py-2 font-mono text-sm dark:border-amber-900 dark:bg-amber-950/20">
                            {{ newCredentials?.client_secret }}
                        </code>
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            @click="copy(newCredentials?.client_secret || '', 'new-secret')"
                        >
                            <Check v-if="copiedField === 'new-secret'" class="h-4 w-4 text-emerald-500" />
                            <Copy v-else class="h-4 w-4" />
                        </Button>
                    </div>
                </div>
            </div>

            <DialogFooter>
                <Button @click="closeCredentials">เก็บแล้ว ปิดหน้าต่าง</Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
