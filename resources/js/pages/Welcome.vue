<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import {
    CheckCircle2,
    Zap,
    Shield,
    BarChart3,
    Database,
    Lock,
    Clock,
    TrendingUp,
    Users,
    Star,
    Sparkles,
} from 'lucide-vue-next';
import { computed, ref } from 'vue';
import DemoRequestDialog from '@/components/DemoRequestDialog.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { dashboard, login, register } from '@/routes';

const demoDialogOpen = ref(false);

function openDemoDialog() {
    demoDialogOpen.value = true;
}

defineProps<{
    canRegister: boolean;
}>();

// ROI Calculator
const casesPerMonth = ref(1000);
const staffCost = computed(() => Math.max(0, casesPerMonth.value * 15)); // ~15 บาท/เคส manual cost equivalent
const autoNapCost = computed(() => {
    if (casesPerMonth.value <= 500) return 1990;
    if (casesPerMonth.value <= 1500) return 3990;
    if (casesPerMonth.value <= 3500) return 5990;
    if (casesPerMonth.value <= 8000) return 12990;
    return 12990;
});
const saved = computed(() => Math.max(0, staffCost.value - autoNapCost.value));
const savedPerYear = computed(() => saved.value * 12);
const roiPercent = computed(() =>
    autoNapCost.value > 0 ? Math.round((saved.value / autoNapCost.value) * 100) : 0
);

const painPoints = [
    {
        title: 'ใช้เวลาบันทึก 1-3 นาที/เคส',
        description: 'พยาบาลหรือเจ้าหน้าที่ต้องนั่งกรอกฟอร์ม NAP ด้วยตัวเอง ทุกเคส',
    },
    {
        title: 'จ้างพนักงานบันทึก 1-2 คน',
        description: 'ค่าจ้าง 15,000-30,000 บาท/เดือน เฉพาะสำหรับกรอกข้อมูลอย่างเดียว',
    },
    {
        title: 'เคลมตก UIC ถูกชิง',
        description: 'บันทึกเลตเกิน deadline = เคลมเงินจากรัฐไม่ได้',
    },
    {
        title: 'Human error พิมพ์ผิด',
        description: 'รหัส KP, อาชีพ, วันที่ผิด = ถูก reject ต้องแก้ใหม่',
    },
];

const features = [
    {
        title: 'ประหยัดเวลา 10 เท่า',
        description:
            'จาก 3 นาที/เคส เหลือ 15 วินาที — พนักงาน 1 คนบันทึกได้เท่าทีมทั้งแผนก',
        icon: Zap,
        color: 'text-yellow-500',
    },
    {
        title: 'Zero Data Retention',
        description:
            'เราไม่เก็บข้อมูลผู้ป่วย — ประมวลผลเสร็จแล้วลบทิ้ง เก็บแค่ xxxx1234 กับรหัส NAP เพื่อ audit เท่านั้น',
        icon: Lock,
        color: 'text-teal-500',
    },
    {
        title: 'Login ผ่าน ThaID',
        description:
            'ไม่ต้องฝาก password ไว้กับเรา ผู้ใช้ยืนยันตัวตนและอนุญาตทุกครั้งผ่านแอป ThaID ของกรมการปกครอง',
        icon: Shield,
        color: 'text-blue-500',
        logo: '/assets/thaid-logo.webp',
    },
    {
        title: 'Real-time Dashboard',
        description:
            'ติดตามสถานะทุกเคสแบบ live พร้อม email report หลังทำงานเสร็จทุกรอบ',
        icon: BarChart3,
        color: 'text-purple-500',
    },
    {
        title: 'PDPA Compliant',
        description:
            'Data localization ในไทย, เข้ารหัสข้อมูล, audit log ครบ, มี DPA template พร้อมเซ็น',
        icon: Database,
        color: 'text-green-500',
    },
    {
        title: 'Automation Tool มาตรฐาน',
        description:
            'เป็น Robotic Process Automation (RPA) แบบเดียวกับที่ธนาคาร/บริษัทบัญชีใช้ — ระบบทำหน้าที่เป็นผู้ช่วยของเจ้าหน้าที่ ทำงานในนามของผู้ใช้เอง',
        icon: CheckCircle2,
        color: 'text-emerald-500',
    },
];

const pricingTiers = [
    {
        name: 'RR Basic',
        tagline: 'สำหรับชมรม/กลุ่ม outreach',
        price: '790',
        listPrice: '1,490',
        quota: '300 เคส/เดือน',
        features: [
            'RR form บันทึกอัตโนมัติ',
            'CSV upload',
            'Email report',
            'Dashboard',
            'Community support',
        ],
        cta: 'เริ่มทดลอง',
        popular: false,
    },
    {
        name: 'Growth',
        tagline: 'สำหรับมูลนิธิขนาดกลาง',
        price: '1,995',
        listPrice: '3,990',
        quota: '1,500 เคส/เดือน',
        features: [
            'RR + VCT + Lab + Result',
            'Callback API',
            'Priority queue',
            'Line/Email support 12x5',
            '3 users',
        ],
        cta: 'เลือก Growth',
        popular: false,
    },
    {
        name: 'Scale',
        tagline: 'สำหรับคลินิกใหญ่',
        price: '2,995',
        listPrice: '5,990',
        quota: '3,500 เคส/เดือน',
        badge: 'คุ้มที่สุด',
        features: [
            '🚀 2.3× เคสจาก Growth (คุ้มกว่า)',
            'Unlimited users',
            'Priority processing (x2 เร็วกว่า)',
            'Custom integration',
            'SLA 99% + 24x7 support',
            'Advanced reporting + audit export',
            'Overage ลดเหลือ ฿2.5/เคส',
        ],
        cta: 'เลือก Scale',
        popular: true,
    },
    {
        name: 'Enterprise',
        tagline: 'เครือข่าย/รพ.',
        price: '6,495',
        listPrice: '12,990',
        quota: '8,000 เคส/เดือน',
        features: [
            'Dedicated worker (ไม่ต้องรอคิว)',
            'On-premise option',
            'Custom form + white-label',
            'Account manager',
            'Multi-site billing',
            'Overage ฿2/เคส',
        ],
        cta: 'ติดต่อเรา',
        popular: false,
    },
];

const testimonials = [
    {
        name: 'ผู้จัดการคลินิก',
        org: 'คลินิก HIV ภาคเหนือ',
        quote: 'เคยใช้เวลาบันทึก NAP ทั้งวัน ตอนนี้เสร็จใน 1 ชั่วโมง ทีมมีเวลาไปดูแลคนไข้มากขึ้น',
    },
    {
        name: 'ผู้ประสานงาน',
        org: 'มูลนิธิ HIV ภาคเหนือ',
        quote: 'ประหยัดค่าจ้างพนักงานบันทึก 1 คน เดือนละเกือบ 20,000 — ใช้งบไปพัฒนา outreach แทน',
    },
    {
        name: 'ผู้อำนวยการ',
        org: 'สมาคม HIV กรุงเทพฯ',
        quote: 'UIC ไม่หลุดแล้ว บันทึกเร็วทันเดดไลน์ทุกเดือน — เคลมเต็มตลอด 6 เดือน',
    },
];

const stats = [
    { value: '17+', label: 'Sites ใช้งานจริง', icon: Users },
    { value: '10x', label: 'เร็วกว่าบันทึกเอง', icon: Zap },
    { value: '0', label: 'ข้อมูลที่เก็บ (Zero Retention)', icon: Lock },
    { value: '99%+', label: 'Uptime', icon: TrendingUp },
];
</script>

<template>
    <Head title="AutoNAP — บันทึก NAP อัตโนมัติ ปลอดภัย เร็วกว่า 10 เท่า">
        <link rel="preconnect" href="https://fonts.googleapis.com" />
        <link
            rel="preconnect"
            href="https://fonts.gstatic.com"
            crossorigin=""
        />
        <link
            href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans+Thai:wght@300;400;500;600;700&family=Outfit:wght@300;400;500;600;700&display=swap"
            rel="stylesheet"
        />
    </Head>

    <div class="min-h-screen bg-slate-50 font-['IBM_Plex_Sans_Thai','Outfit',sans-serif] dark:bg-slate-950">
        <!-- Navigation -->
        <nav
            class="sticky top-0 z-50 border-b border-slate-200/50 bg-white/80 backdrop-blur-md dark:border-slate-800/50 dark:bg-slate-950/80"
        >
            <div
                class="mx-auto flex h-16 max-w-7xl items-center justify-between px-4 sm:px-6 lg:px-8"
            >
                <div class="flex items-center gap-2">
                    <img
                        src="/assets/logo.png"
                        alt="AutoNAP"
                        class="h-8 w-auto md:h-10"
                    />
                    <span
                        class="hidden text-xl font-bold tracking-tight text-slate-900 md:block dark:text-white"
                    >
                        AutoNAP
                    </span>
                </div>

                <div class="hidden items-center gap-6 md:flex">
                    <a href="#features" class="text-sm font-medium text-slate-600 hover:text-teal-600 dark:text-slate-300">จุดเด่น</a>
                    <a href="#pricing" class="text-sm font-medium text-slate-600 hover:text-teal-600 dark:text-slate-300">ราคา</a>
                    <a href="#roi" class="text-sm font-medium text-slate-600 hover:text-teal-600 dark:text-slate-300">คำนวณ ROI</a>
                    <a href="#faq" class="text-sm font-medium text-slate-600 hover:text-teal-600 dark:text-slate-300">FAQ</a>
                </div>

                <div class="flex items-center gap-3">
                    <template v-if="$page.props.auth.user">
                        <Button as-child variant="ghost">
                            <Link :href="dashboard()">Dashboard</Link>
                        </Button>
                    </template>
                    <template v-else>
                        <Button as-child variant="ghost" class="hidden sm:inline-flex">
                            <Link :href="login()">เข้าสู่ระบบ</Link>
                        </Button>
                        <Button v-if="canRegister" as-child>
                            <Link :href="register()">เริ่มทดลอง</Link>
                        </Button>
                    </template>
                </div>
            </div>
        </nav>

        <main>
            <!-- Hero Section -->
            <section class="relative overflow-hidden pt-16 pb-24 lg:pt-24 lg:pb-28">
                <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                    <div class="grid items-center gap-12 lg:grid-cols-2">
                        <div class="flex flex-col items-start gap-6">
                            <Badge
                                variant="outline"
                                class="border-teal-200 bg-teal-50 px-3 py-1 text-teal-700 dark:border-teal-900 dark:bg-teal-950/20 dark:text-teal-400"
                            >
                                <Sparkles class="mr-1 h-3.5 w-3.5" />
                                Founding 50 — เปิดรับถึง 31 ก.ค. ส่วนลด 50%
                            </Badge>

                            <h1 class="text-4xl font-bold tracking-tight text-balance text-slate-900 sm:text-5xl lg:text-6xl dark:text-white">
                                บันทึก NAP
                                <span class="bg-linear-to-r from-teal-500 to-blue-600 bg-clip-text text-transparent">
                                    อัตโนมัติ
                                </span>
                                <br />เร็วกว่า 10 เท่า
                            </h1>

                            <p class="text-lg text-pretty text-slate-600 dark:text-slate-400">
                                ลดเวลาบันทึกจาก <strong>3 นาที เหลือ 15 วินาที/เคส</strong>
                                — ใช้แล้วโดยคลินิก HIV <strong>17 แห่ง</strong>ทั่วประเทศ
                                ในเครือข่าย สปสช.
                            </p>

                            <div class="flex flex-col gap-4 sm:flex-row sm:items-center">
                                <Button size="lg" as-child class="h-12 px-8 text-base">
                                    <Link :href="register()">เริ่มทดลองฟรี 30 วัน</Link>
                                </Button>
                                <Button
                                    size="lg"
                                    variant="outline"
                                    class="h-12 px-8 text-base"
                                    @click="openDemoDialog"
                                >
                                    นัดคุย Demo 15 นาที
                                </Button>
                            </div>

                            <div class="mt-4 flex flex-wrap items-center gap-x-6 gap-y-2 text-sm text-slate-500 dark:text-slate-400">
                                <div class="flex items-center gap-1.5">
                                    <CheckCircle2 class="h-4 w-4 text-teal-500" />
                                    ไม่ต้องใส่บัตรเครดิต
                                </div>
                                <div class="flex items-center gap-1.5">
                                    <CheckCircle2 class="h-4 w-4 text-teal-500" />
                                    PDPA compliant
                                </div>
                                <div class="flex items-center gap-1.5">
                                    <CheckCircle2 class="h-4 w-4 text-teal-500" />
                                    Zero data retention
                                </div>
                            </div>
                        </div>

                        <div class="relative">
                            <div class="absolute -inset-1 rounded-3xl bg-linear-to-r from-teal-500 to-blue-600 opacity-20 blur-3xl lg:opacity-30"></div>
                            <div class="relative overflow-hidden rounded-2xl border border-slate-200 bg-white p-2 shadow-2xl dark:border-slate-800 dark:bg-slate-900">
                                <img
                                    src="/assets/hero.png"
                                    alt="AutoNAP Dashboard Preview"
                                    class="rounded-lg shadow-sm"
                                />
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Social Proof / Stats -->
            <section class="border-y border-slate-200 bg-white py-12 dark:border-slate-800 dark:bg-slate-900/50">
                <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                    <p class="mb-8 text-center text-sm font-medium text-slate-500 dark:text-slate-400">
                        คลินิกในเครือข่ายใหญ่ของประเทศใช้ AutoNAP แล้ว
                    </p>
                    <div class="grid grid-cols-2 gap-6 md:grid-cols-4">
                        <div
                            v-for="stat in stats"
                            :key="stat.label"
                            class="flex flex-col items-center text-center"
                        >
                            <component :is="stat.icon" class="mb-2 h-8 w-8 text-teal-500" />
                            <div class="text-3xl font-bold text-slate-900 dark:text-white">
                                {{ stat.value }}
                            </div>
                            <div class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                                {{ stat.label }}
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Pain Points -->
            <section class="py-20">
                <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                    <div class="mb-12 text-center">
                        <h2 class="text-3xl font-bold tracking-tight text-slate-900 sm:text-4xl dark:text-white">
                            ปัญหาที่คุณเจอทุกเดือน
                        </h2>
                        <p class="mt-4 text-lg text-slate-600 dark:text-slate-400">
                            AutoNAP ถูกออกแบบมาเพื่อแก้ปัญหาเหล่านี้โดยเฉพาะ
                        </p>
                    </div>
                    <div class="grid gap-6 md:grid-cols-2 lg:grid-cols-4">
                        <Card
                            v-for="pain in painPoints"
                            :key="pain.title"
                            class="border-rose-200 bg-rose-50/50 dark:border-rose-900/40 dark:bg-rose-950/10"
                        >
                            <CardHeader>
                                <div class="mb-2 text-2xl">⚠️</div>
                                <CardTitle class="text-lg">{{ pain.title }}</CardTitle>
                            </CardHeader>
                            <CardContent>
                                <p class="text-sm text-slate-600 dark:text-slate-400">
                                    {{ pain.description }}
                                </p>
                            </CardContent>
                        </Card>
                    </div>
                </div>
            </section>

            <!-- Features / Why AutoNAP -->
            <section id="features" class="bg-white py-20 dark:bg-slate-900/50">
                <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                    <div class="mb-16 flex flex-col items-center text-center">
                        <h2 class="text-3xl font-bold tracking-tight text-slate-900 sm:text-4xl dark:text-white">
                            ทำไมเลือก AutoNAP?
                        </h2>
                        <div class="mt-4 h-1.5 w-20 rounded-full bg-teal-500"></div>
                        <p class="mt-6 max-w-2xl text-lg text-slate-600 dark:text-slate-400">
                            เราสร้างระบบเฉพาะทางเพื่อแก้ปัญหาการบันทึก NAP
                            ให้กับคลินิกภายใต้เครือข่าย สปสช.
                        </p>
                    </div>

                    <div class="grid gap-8 md:grid-cols-2 lg:grid-cols-3">
                        <Card
                            v-for="feature in features"
                            :key="feature.title"
                            class="relative overflow-hidden border-slate-200 transition-all hover:border-teal-400 hover:shadow-lg dark:border-slate-800 dark:hover:border-teal-700"
                        >
                            <CardHeader>
                                <div class="mb-4 flex items-center gap-3">
                                    <div
                                        :class="[
                                            feature.color,
                                            'flex h-12 w-12 items-center justify-center rounded-lg bg-slate-50 dark:bg-slate-900',
                                        ]"
                                    >
                                        <component :is="feature.icon" class="h-6 w-6" />
                                    </div>
                                    <img
                                        v-if="feature.logo"
                                        :src="feature.logo"
                                        :alt="feature.title"
                                        class="h-12 w-12 rounded-lg object-contain"
                                    />
                                </div>
                                <CardTitle class="text-xl font-bold">{{ feature.title }}</CardTitle>
                            </CardHeader>
                            <CardContent>
                                <CardDescription class="text-base text-slate-600 dark:text-slate-400">
                                    {{ feature.description }}
                                </CardDescription>
                            </CardContent>
                        </Card>
                    </div>
                </div>
            </section>

            <!-- ROI Calculator -->
            <section id="roi" class="py-20">
                <div class="mx-auto max-w-4xl px-4 sm:px-6 lg:px-8">
                    <div class="mb-10 text-center">
                        <h2 class="text-3xl font-bold tracking-tight text-slate-900 sm:text-4xl dark:text-white">
                            คำนวณ ROI ของคลินิกคุณ
                        </h2>
                        <p class="mt-4 text-lg text-slate-600 dark:text-slate-400">
                            เลื่อนเพื่อดูว่าคุณจะประหยัดได้เท่าไร
                        </p>
                    </div>

                    <Card class="border-teal-200 shadow-xl dark:border-teal-900">
                        <CardContent class="p-8">
                            <div class="mb-6">
                                <label class="mb-2 block text-sm font-medium text-slate-700 dark:text-slate-300">
                                    จำนวนเคส/เดือน:
                                    <span class="ml-2 text-2xl font-bold text-teal-600">
                                        {{ casesPerMonth.toLocaleString() }}
                                    </span>
                                </label>
                                <input
                                    v-model.number="casesPerMonth"
                                    type="range"
                                    min="100"
                                    max="6000"
                                    step="100"
                                    class="h-2 w-full cursor-pointer rounded-lg bg-slate-200 accent-teal-500 dark:bg-slate-700"
                                />
                                <div class="mt-1 flex justify-between text-xs text-slate-500">
                                    <span>100</span>
                                    <span>6,000+</span>
                                </div>
                            </div>

                            <div class="grid gap-4 sm:grid-cols-3">
                                <div class="rounded-xl bg-rose-50 p-5 dark:bg-rose-950/20">
                                    <div class="text-xs font-medium text-rose-700 dark:text-rose-400">ต้นทุนเดิม/เดือน</div>
                                    <div class="mt-1 text-2xl font-bold text-rose-600 dark:text-rose-300">
                                        ฿{{ staffCost.toLocaleString() }}
                                    </div>
                                    <div class="mt-1 text-xs text-rose-500">พนักงาน + เคลมตก</div>
                                </div>
                                <div class="rounded-xl bg-blue-50 p-5 dark:bg-blue-950/20">
                                    <div class="text-xs font-medium text-blue-700 dark:text-blue-400">AutoNAP/เดือน</div>
                                    <div class="mt-1 text-2xl font-bold text-blue-600 dark:text-blue-300">
                                        ฿{{ autoNapCost.toLocaleString() }}
                                    </div>
                                    <div class="mt-1 text-xs text-blue-500">List price (ไม่รวม Founding discount)</div>
                                </div>
                                <div class="rounded-xl bg-teal-50 p-5 dark:bg-teal-950/20">
                                    <div class="text-xs font-medium text-teal-700 dark:text-teal-400">ประหยัด/เดือน</div>
                                    <div class="mt-1 text-2xl font-bold text-teal-600 dark:text-teal-300">
                                        ฿{{ saved.toLocaleString() }}
                                    </div>
                                    <div class="mt-1 text-xs text-teal-500">ROI {{ roiPercent }}%</div>
                                </div>
                            </div>

                            <div class="mt-6 rounded-xl bg-linear-to-r from-teal-500 to-blue-600 p-5 text-center text-white">
                                <div class="text-xs font-medium uppercase">ประหยัดรวม/ปี</div>
                                <div class="mt-1 text-4xl font-bold">
                                    ฿{{ savedPerYear.toLocaleString() }}
                                </div>
                                <div class="mt-2 text-sm opacity-90">
                                    = ค่าจ้างพนักงาน ~{{ Math.floor(savedPerYear / 18000) }} เดือน
                                </div>
                            </div>

                            <Button size="lg" as-child class="mt-6 h-12 w-full">
                                <Link :href="register()">เริ่มทดลอง 30 วันฟรี</Link>
                            </Button>
                        </CardContent>
                    </Card>
                </div>
            </section>

            <!-- Pricing -->
            <section id="pricing" class="bg-white py-20 dark:bg-slate-900/50">
                <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                    <div class="mb-12 text-center">
                        <Badge variant="outline" class="mb-4 border-teal-200 bg-teal-50 text-teal-700 dark:border-teal-900 dark:bg-teal-950/20 dark:text-teal-400">
                            <Sparkles class="mr-1 h-3.5 w-3.5" />
                            Founding 50 — ลด 50% ล็อค 12 เดือน
                        </Badge>
                        <h2 class="text-3xl font-bold tracking-tight text-slate-900 sm:text-4xl dark:text-white">
                            แพ็คเกจและราคา
                        </h2>
                        <p class="mt-4 text-lg text-slate-600 dark:text-slate-400">
                            ราคาพิเศษสำหรับ 50 ศูนย์แรก — ปิดรับ 31 ก.ค. 2569
                        </p>
                        <div class="mt-6 inline-flex items-center gap-2 rounded-full border border-teal-200 bg-teal-50 px-4 py-2 text-sm text-teal-700 dark:border-teal-900 dark:bg-teal-950/20 dark:text-teal-400">
                            <CheckCircle2 class="h-4 w-4" />
                            <span>
                                <strong>1 เคส = 1 ผู้รับบริการ</strong> บันทึก form ไหนก็ได้ (RR / VCT / Lab / Result / PrEP) ไม่คิดเพิ่ม
                            </span>
                        </div>
                    </div>

                    <div class="grid gap-6 md:grid-cols-2 lg:grid-cols-4">
                        <Card
                            v-for="tier in pricingTiers"
                            :key="tier.name"
                            :class="[
                                'relative flex flex-col transition-all hover:shadow-lg',
                                tier.popular
                                    ? 'border-2 border-teal-500 shadow-xl dark:border-teal-400'
                                    : 'border-slate-200 dark:border-slate-800',
                            ]"
                        >
                            <div
                                v-if="tier.popular"
                                class="absolute -top-3 left-1/2 -translate-x-1/2 rounded-full bg-teal-500 px-3 py-1 text-xs font-bold text-white"
                            >
                                {{ tier.badge || 'แนะนำ' }}
                            </div>
                            <CardHeader>
                                <CardTitle class="text-2xl font-bold">{{ tier.name }}</CardTitle>
                                <CardDescription class="text-sm">{{ tier.tagline }}</CardDescription>
                                <div class="mt-4">
                                    <span class="text-sm text-slate-400 line-through">฿{{ tier.listPrice }}</span>
                                    <div class="flex items-baseline gap-1">
                                        <span class="text-4xl font-bold text-slate-900 dark:text-white">฿{{ tier.price }}</span>
                                        <span class="text-sm text-slate-500">/เดือน</span>
                                    </div>
                                    <div class="mt-1 text-xs font-medium text-teal-600 dark:text-teal-400">
                                        Founding price (ล็อค 12 เดือน)
                                    </div>
                                </div>
                                <div class="mt-2 text-sm font-medium text-slate-600 dark:text-slate-400">
                                    👥 {{ tier.quota }}
                                </div>
                                <div class="mt-1 text-xs text-slate-500 dark:text-slate-500">
                                    บันทึก form ได้ไม่จำกัดต่อเคส
                                </div>
                            </CardHeader>
                            <CardContent class="flex flex-1 flex-col">
                                <ul class="mb-6 flex-1 space-y-2">
                                    <li
                                        v-for="f in tier.features"
                                        :key="f"
                                        class="flex items-start gap-2 text-sm text-slate-600 dark:text-slate-400"
                                    >
                                        <CheckCircle2 class="mt-0.5 h-4 w-4 flex-shrink-0 text-teal-500" />
                                        {{ f }}
                                    </li>
                                </ul>
                                <Button
                                    as-child
                                    :variant="tier.popular ? 'default' : 'outline'"
                                    class="w-full"
                                >
                                    <Link :href="register()">{{ tier.cta }}</Link>
                                </Button>
                            </CardContent>
                        </Card>
                    </div>

                    <p class="mt-8 text-center text-sm text-slate-500 dark:text-slate-400">
                        💡 จ่ายล่วงหน้า 3 เดือน = ฟรี 1 เดือน • 12 เดือน = ฟรี 3 เดือน<br />
                        🎁 <strong>ไม่จำกัด form</strong> — บันทึก RR, VCT, Lab, Result ต่อเคสเดิมได้ไม่คิดเพิ่ม<br />
                        📞 สำหรับเครือข่ายหลายสาขา / รพ. ติดต่อเพื่อขอราคาพิเศษ
                    </p>
                </div>
            </section>

            <!-- Testimonials -->
            <section class="py-20">
                <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                    <div class="mb-12 text-center">
                        <h2 class="text-3xl font-bold tracking-tight text-slate-900 sm:text-4xl dark:text-white">
                            ลูกค้าบอกอะไรกับเรา
                        </h2>
                    </div>
                    <div class="grid gap-6 md:grid-cols-3">
                        <Card
                            v-for="t in testimonials"
                            :key="t.org"
                            class="border-slate-200 dark:border-slate-800"
                        >
                            <CardContent class="p-6">
                                <div class="mb-3 flex gap-0.5">
                                    <Star v-for="i in 5" :key="i" class="h-4 w-4 fill-yellow-400 text-yellow-400" />
                                </div>
                                <p class="mb-4 text-slate-700 dark:text-slate-300">
                                    "{{ t.quote }}"
                                </p>
                                <div class="border-t border-slate-100 pt-3 dark:border-slate-800">
                                    <div class="font-semibold text-slate-900 dark:text-white">{{ t.name }}</div>
                                    <div class="text-sm text-slate-500">{{ t.org }}</div>
                                </div>
                            </CardContent>
                        </Card>
                    </div>
                </div>
            </section>

            <!-- FAQ -->
            <section id="faq" class="bg-white py-20 dark:bg-slate-900/50">
                <div class="mx-auto max-w-3xl px-4 sm:px-6 lg:px-8">
                    <h2 class="mb-12 text-center text-3xl font-bold tracking-tight text-slate-900 sm:text-4xl dark:text-white">
                        คำถามที่พบบ่อย
                    </h2>
                    <div class="space-y-6">
                        <div>
                            <h3 class="mb-2 text-lg font-semibold text-slate-900 dark:text-white">
                                AutoNAP ทำงานกับระบบ สปสช. อย่างไร?
                            </h3>
                            <p class="text-slate-600 dark:text-slate-400">
                                AutoNAP เป็น Robotic Process Automation (RPA) แบบเดียวกับที่ธนาคารและบริษัทบัญชีใช้
                                ระบบทำงานเป็น "ผู้ช่วยเสมือน" ของเจ้าหน้าที่ — Login ผ่าน ThaID ของผู้ใช้เอง หรือ credentials ของคลินิก
                                แล้วกรอกฟอร์มในสิ่งที่เจ้าหน้าที่ทำเองได้อยู่แล้ว เพียงแต่ทำเร็วและต่อเนื่องกว่า
                            </p>
                        </div>
                        <div>
                            <h3 class="mb-2 text-lg font-semibold text-slate-900 dark:text-white">
                                ข้อมูลผู้ป่วยของเราปลอดภัยไหม?
                            </h3>
                            <p class="text-slate-600 dark:text-slate-400">
                                <strong>ปลอดภัยมาก เพราะเราไม่เก็บข้อมูลผู้ป่วยเลย</strong> —
                                CSV ที่คุณ upload ถูกประมวลผลแล้วลบทันที เก็บไว้แค่ 4 ตัวท้ายของเลขบัตร
                                กับรหัส NAP เพื่อ audit เท่านั้น (90 วันแล้วลบอัตโนมัติ)
                            </p>
                        </div>
                        <div>
                            <h3 class="mb-2 text-lg font-semibold text-slate-900 dark:text-white">
                                1 เคสนับยังไง? ถ้าบันทึกหลาย form ต่อผู้รับบริการคนเดียวกัน?
                            </h3>
                            <p class="text-slate-600 dark:text-slate-400">
                                <strong>1 เคส = 1 ผู้รับบริการ (patient visit)</strong> ไม่ว่าคุณจะบันทึก
                                RR อย่างเดียว, RR+VCT, หรือ RR+VCT+Lab+Result ครบวงจร
                                <strong>นับเป็น 1 เคสเท่านั้น</strong> ไม่คิดเพิ่มตาม form
                                <br /><br />
                                เพิ่มการบันทึก Result ทีหลัง (เช่น รอผล lab) ก็ไม่นับซ้ำ — ถือเป็นการ
                                update ของเคสเดิม คุณได้ flexibility เต็มในการบันทึกตามจังหวะงานจริง
                            </p>
                        </div>
                        <div>
                            <h3 class="mb-2 text-lg font-semibold text-slate-900 dark:text-white">
                                ใช้ร่วมกับระบบคลินิกใดได้บ้าง?
                            </h3>
                            <p class="text-slate-600 dark:text-slate-400">
                                AutoNAP ออกแบบเป็น <strong>input-agnostic</strong> — ใช้ได้โดยไม่ต้องเปลี่ยนระบบเดิมของคุณ
                                รองรับ 5 ช่องทาง: <strong>CSV/Excel upload</strong>, Web form บน AutoNAP,
                                Google Sheets sync, Open API/Webhook และ Custom integration
                                <br /><br />
                                Integration พร้อมใช้งาน: <strong>ACTSE Clinic</strong> (ระบบจัดการคลินิกในเครือ) และ CAREMAT
                                ส่วนระบบอื่นสามารถเริ่มจาก CSV upload ได้ทันที ไม่ต้องรอ IT ของลูกค้าเซ็ตอัพ
                            </p>
                        </div>
                        <div>
                            <h3 class="mb-2 text-lg font-semibold text-slate-900 dark:text-white">
                                ยกเลิกได้ทุกเมื่อไหม?
                            </h3>
                            <p class="text-slate-600 dark:text-slate-400">
                                ได้ครับ — ไม่มีสัญญาผูกมัด ยกเลิกเมื่อไหร่ก็ได้
                                ถ้าอยู่ในช่วง Founding 50 (ล็อคราคา 12 เดือน) ยกเลิกได้แต่ไม่คืนเงินที่จ่ายล่วงหน้า
                            </p>
                        </div>
                        <div>
                            <h3 class="mb-2 text-lg font-semibold text-slate-900 dark:text-white">
                                มี DPA (Data Processing Agreement) ไหม?
                            </h3>
                            <p class="text-slate-600 dark:text-slate-400">
                                มีครับ — template ตาม PDPA พร้อมให้ทนายของคลินิก review ก่อนเซ็น
                                สามารถขอได้ฟรีหลังสมัคร
                            </p>
                        </div>
                    </div>
                </div>
            </section>

            <!-- CTA Section -->
            <section class="relative overflow-hidden bg-slate-900 py-20 text-white">
                <div class="absolute inset-0 opacity-10 blur-3xl">
                    <div class="absolute -top-1/2 -left-1/4 h-full w-full bg-teal-500"></div>
                </div>
                <div class="relative mx-auto max-w-4xl px-4 text-center sm:px-6 lg:px-8">
                    <Badge class="mb-4 bg-teal-500 text-white">
                        <Clock class="mr-1 h-3.5 w-3.5" />
                        Founding 50 ปิดรับ 31 ก.ค. 2569
                    </Badge>
                    <h2 class="text-3xl font-bold tracking-tight sm:text-4xl">
                        พร้อมแล้วที่จะประหยัดเวลา<br />และเงินเดือนละหลายหมื่น?
                    </h2>
                    <p class="mx-auto mt-6 max-w-2xl text-lg text-slate-300">
                        เริ่มใช้งานฟรี 30 วัน ไม่ต้องใส่บัตรเครดิต —
                        ล็อคราคา Founding 50% off ตลอด 12 เดือน
                    </p>
                    <div class="mt-10 flex flex-col justify-center gap-4 sm:flex-row">
                        <Button
                            size="lg"
                            as-child
                            class="h-12 border-0 bg-teal-500 px-10 text-white hover:bg-teal-600"
                        >
                            <Link :href="register()">เริ่มทดลองฟรี 30 วัน</Link>
                        </Button>
                        <Button
                            size="lg"
                            variant="outline"
                            class="h-12 border-slate-700 px-10 text-slate-300 hover:bg-slate-800"
                            @click="openDemoDialog"
                        >
                            นัดคุย Demo 15 นาที
                        </Button>
                    </div>
                </div>
            </section>
        </main>

        <!-- Footer -->
        <footer class="border-t border-slate-200 bg-white py-12 dark:border-slate-800 dark:bg-slate-950">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div class="flex flex-col items-center justify-between gap-8 md:flex-row">
                    <div class="flex items-center gap-2">
                        <img src="/assets/logo.png" alt="AutoNAP" class="h-6 w-auto" />
                        <span class="text-lg font-bold">AutoNAP</span>
                    </div>
                    <div class="text-sm text-slate-500 dark:text-slate-400">
                        &copy; 2026 AutoNAP. สำหรับเครือข่ายคลินิก HIV ภายใต้ สปสช.
                    </div>
                    <div class="flex gap-6 text-sm text-slate-500 dark:text-slate-400">
                        <Link href="/privacy" class="hover:text-teal-500">Privacy Policy</Link>
                        <Link href="/terms" class="hover:text-teal-500">Terms of Service</Link>
                        <a href="mailto:privacy@autonap.co.th" class="hover:text-teal-500">ติดต่อ</a>
                    </div>
                </div>
            </div>
        </footer>

        <DemoRequestDialog v-model:open="demoDialogOpen" />
    </div>
</template>

<style>
.text-balance {
    text-wrap: balance;
}
.text-pretty {
    text-wrap: pretty;
}
</style>
