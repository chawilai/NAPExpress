<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import {
    CheckCircle2,
    Zap,
    Shield,
    BarChart3,
    Database,
} from 'lucide-vue-next';
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

defineProps<{
    canRegister: boolean;
}>();

const features = [
    {
        title: 'Seamless Automation',
        description:
            'AutoNAP handles the tedious Playwright automation, filling out 12+ NAP form types automatically from your Excel data.',
        icon: Zap,
        color: 'text-yellow-500',
    },
    {
        title: 'Data Accuracy',
        description:
            'Built-in validation ensures your data meets NAP standards before submission, reducing errors and rejected claims.',
        icon: Shield,
        color: 'text-blue-500',
    },
    {
        title: 'Real-time Monitoring',
        description:
            'Watch your reporting progress in real-time with Ably-powered live updates and comprehensive job reports.',
        icon: BarChart3,
        color: 'text-teal-500',
    },
];
</script>

<template>
    <Head title="AutoNAP — Smart AIDS Reporting">
        <link rel="preconnect" href="https://fonts.googleapis.com" />
        <link
            rel="preconnect"
            href="https://fonts.gstatic.com"
            crossorigin=""
        />
        <link
            href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap"
            rel="stylesheet"
        />
    </Head>

    <div class="min-h-screen bg-slate-50 font-['Outfit'] dark:bg-slate-950">
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
                        alt="NAPExpress"
                        class="h-8 w-auto md:h-10"
                    />
                    <span
                        class="hidden text-xl font-bold tracking-tight text-slate-900 md:block dark:text-white"
                    >
                        NAPExpress
                    </span>
                </div>

                <div class="flex items-center gap-4">
                    <template v-if="$page.props.auth.user">
                        <Button as-child variant="ghost">
                            <Link :href="dashboard()">Dashboard</Link>
                        </Button>
                    </template>
                    <template v-else>
                        <Button
                            as-child
                            variant="ghost"
                            class="hidden sm:inline-flex"
                        >
                            <Link :href="login()">Log in</Link>
                        </Button>
                        <Button v-if="canRegister" as-child>
                            <Link :href="register()">Get Started</Link>
                        </Button>
                    </template>
                </div>
            </div>
        </nav>

        <main>
            <!-- Hero Section -->
            <section
                class="relative overflow-hidden pt-16 pb-24 lg:pt-32 lg:pb-32"
            >
                <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                    <div class="grid items-center gap-12 lg:grid-cols-2">
                        <div class="flex flex-col items-start gap-6">
                            <Badge
                                variant="outline"
                                class="border-teal-200 bg-teal-50 px-3 py-1 text-teal-700 dark:border-teal-900 dark:bg-teal-950/20 dark:text-teal-400"
                            >
                                <Zap class="mr-1 h-3.5 w-3.5" />
                                Smart Automation for Clinics
                            </Badge>

                            <h1
                                class="text-4xl font-bold tracking-tight text-balance text-slate-900 sm:text-6xl dark:text-white"
                            >
                                Automate Your
                                <span
                                    class="bg-linear-to-r from-teal-500 to-blue-600 bg-clip-text text-transparent"
                                    >NAP Reporting</span
                                >
                                with Confidence
                            </h1>

                            <p
                                class="text-lg text-pretty text-slate-600 dark:text-slate-400"
                            >
                                Say goodbye to manual data entry for HIV/AIDS
                                reporting. Upload your Excel templates and let
                                AutoNAP handle the rest. Efficient, accurate,
                                and built for modern clinics.
                            </p>

                            <div
                                class="flex flex-col gap-4 sm:flex-row sm:items-center"
                            >
                                <Button
                                    size="lg"
                                    as-child
                                    class="h-12 px-8 text-base"
                                >
                                    <Link :href="register()"
                                        >Start Reporting Now</Link
                                    >
                                </Button>
                                <Button
                                    size="lg"
                                    variant="outline"
                                    class="h-12 px-8 text-base"
                                >
                                    View Demo
                                </Button>
                            </div>

                            <div
                                class="mt-8 flex items-center gap-6 text-sm text-slate-500 dark:text-slate-400"
                            >
                                <div class="flex items-center gap-1.5">
                                    <CheckCircle2
                                        class="h-4 w-4 text-teal-500"
                                    />
                                    No manual re-typing
                                </div>
                                <div class="flex items-center gap-1.5">
                                    <CheckCircle2
                                        class="h-4 w-4 text-teal-500"
                                    />
                                    Secure & Masked PII
                                </div>
                            </div>
                        </div>

                        <div class="relative">
                            <div
                                class="absolute -inset-1 rounded-3xl bg-linear-to-r from-teal-500 to-blue-600 opacity-20 blur-3xl lg:opacity-30"
                            ></div>
                            <div
                                class="relative overflow-hidden rounded-2xl border border-slate-200 bg-white p-2 shadow-2xl dark:border-slate-800 dark:bg-slate-900"
                            >
                                <img
                                    src="/assets/hero.png"
                                    alt="NAPExpress Dashboard Preview"
                                    class="rounded-lg shadow-sm"
                                />
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Features Section -->
            <section class="bg-white py-24 dark:bg-slate-900/50">
                <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                    <div class="mb-16 flex flex-col items-center text-center">
                        <h2
                            class="text-3xl font-bold tracking-tight text-slate-900 sm:text-4xl dark:text-white"
                        >
                            Why Choose NAPExpress?
                        </h2>
                        <div
                            class="mt-4 h-1.5 w-20 rounded-full bg-teal-500"
                        ></div>
                        <p
                            class="mt-6 max-w-2xl text-lg text-slate-600 dark:text-slate-400"
                        >
                            We've built a specialized tool to solve the specific
                            challenges of reporting to the National AIDS
                            Program.
                        </p>
                    </div>

                    <div class="grid gap-8 md:grid-cols-3">
                        <Card
                            v-for="(feature, index) in features"
                            :key="index"
                            class="relative overflow-hidden border-slate-200 transition-all hover:border-teal-400 hover:shadow-lg dark:border-slate-800 dark:hover:border-teal-700"
                        >
                            <CardHeader>
                                <div
                                    :class="[
                                        feature.color,
                                        'mb-4 flex h-12 w-12 items-center justify-center rounded-lg bg-slate-50 dark:bg-slate-900',
                                    ]"
                                >
                                    <component
                                        :is="feature.icon"
                                        class="h-6 w-6"
                                    />
                                </div>
                                <CardTitle class="text-xl font-bold">{{
                                    feature.title
                                }}</CardTitle>
                            </CardHeader>
                            <CardContent>
                                <CardDescription
                                    class="text-base text-slate-600 dark:text-slate-400"
                                >
                                    {{ feature.description }}
                                </CardDescription>
                            </CardContent>
                        </Card>
                    </div>
                </div>
            </section>

            <!-- Stats/Trust Section -->
            <section class="py-24">
                <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                    <div class="grid items-center gap-12 lg:grid-cols-2">
                        <div class="order-2 lg:order-1">
                            <div class="grid grid-cols-2 gap-4">
                                <div
                                    class="rounded-2xl border border-slate-200 bg-white p-8 text-center dark:border-slate-800 dark:bg-slate-900"
                                >
                                    <div
                                        class="text-4xl font-bold text-teal-600 dark:text-teal-400"
                                    >
                                        85%
                                    </div>
                                    <div
                                        class="mt-2 text-sm font-medium text-slate-500"
                                    >
                                        Time Saved
                                    </div>
                                </div>
                                <div
                                    class="rounded-2xl border border-slate-200 bg-white p-8 text-center dark:border-slate-800 dark:bg-slate-900"
                                >
                                    <div
                                        class="text-4xl font-bold text-blue-600 dark:text-blue-400"
                                    >
                                        100%
                                    </div>
                                    <div
                                        class="mt-2 text-sm font-medium text-slate-500"
                                    >
                                        Claim Accuracy
                                    </div>
                                </div>
                                <div
                                    class="col-span-2 rounded-2xl border border-slate-200 bg-white p-8 text-center dark:border-slate-800 dark:bg-slate-900"
                                >
                                    <div
                                        class="text-4xl font-bold text-slate-900 dark:text-white"
                                    >
                                        12+
                                    </div>
                                    <div
                                        class="mt-2 text-sm font-medium text-slate-500"
                                    >
                                        Form Types Supported
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="order-1 flex flex-col gap-6 lg:order-2">
                            <h2
                                class="text-3xl font-bold tracking-tight text-slate-900 sm:text-4xl dark:text-white"
                            >
                                Tailored for KPLHS & ACTSE Clinic Network
                            </h2>
                            <p
                                class="text-lg text-slate-600 dark:text-slate-400"
                            >
                                Whether you're part of the CAREMAT network or an
                                independent clinic, NAPExpress provides the
                                bridge you need to report data without the
                                manual headache.
                            </p>
                            <ul class="space-y-4">
                                <li
                                    v-for="item in [
                                        'Reach RR/TTR',
                                        'CD4/VL Lab API',
                                        'PrEP/VCT Registration',
                                        'HIVST Distribution',
                                    ]"
                                    :key="item"
                                    class="flex items-center gap-3 font-medium text-slate-700 dark:text-slate-200"
                                >
                                    <div
                                        class="flex h-6 w-6 items-center justify-center rounded-full bg-teal-100 dark:bg-teal-900/40"
                                    >
                                        <Database
                                            class="h-3.5 w-3.5 text-teal-600 dark:text-teal-400"
                                        />
                                    </div>
                                    {{ item }}
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>
            </section>

            <!-- CTA Section -->
            <section
                class="relative overflow-hidden bg-slate-900 py-24 text-white"
            >
                <div class="absolute inset-0 opacity-10 blur-3xl">
                    <div
                        class="absolute -top-1/2 -left-1/4 h-full w-full bg-teal-500"
                    ></div>
                </div>
                <div
                    class="relative mx-auto max-w-7xl px-4 text-center sm:px-6 lg:px-8"
                >
                    <h2 class="text-3xl font-bold tracking-tight sm:text-4xl">
                        Ready to automate your clinic reporting?
                    </h2>
                    <p class="mx-auto mt-6 max-w-2xl text-lg text-slate-400">
                        Join other clinics saving hundreds of hours each month.
                        Start your free trial today.
                    </p>
                    <div
                        class="mt-10 flex flex-col justify-center gap-4 sm:flex-row"
                    >
                        <Button
                            size="lg"
                            as-child
                            class="h-12 border-0 bg-teal-500 px-10 text-white hover:bg-teal-600"
                        >
                            <Link :href="register()"
                                >Register Organization</Link
                            >
                        </Button>
                        <Button
                            size="lg"
                            variant="outline"
                            class="h-12 border-slate-700 px-10 text-slate-300 hover:bg-slate-800"
                        >
                            Contact Support
                        </Button>
                    </div>
                </div>
            </section>
        </main>

        <!-- Footer -->
        <footer
            class="border-t border-slate-200 bg-white py-12 dark:border-slate-800 dark:bg-slate-950"
        >
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div
                    class="flex flex-col items-center justify-between gap-8 md:flex-row"
                >
                    <div class="flex items-center gap-2">
                        <img
                            src="/assets/logo.png"
                            alt="NAPExpress"
                            class="h-6 w-auto"
                        />
                        <span class="text-lg font-bold">NAPExpress</span>
                    </div>
                    <div class="text-sm text-slate-500 dark:text-slate-400">
                        &copy; 2026 NAPExpress. Developed for the KPLHS network.
                    </div>
                    <div
                        class="flex gap-6 text-sm text-slate-500 dark:text-slate-400"
                    >
                        <a href="#" class="hover:text-teal-500"
                            >Privacy Policy</a
                        >
                        <a href="#" class="hover:text-teal-500"
                            >Terms of Service</a
                        >
                    </div>
                </div>
            </div>
        </footer>
    </div>
</template>

<style>
/* Custom animations or refinements if needed */
.text-balance {
    text-wrap: balance;
}
.text-pretty {
    text-wrap: pretty;
}
</style>
