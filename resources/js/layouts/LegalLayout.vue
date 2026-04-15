<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ArrowLeft } from 'lucide-vue-next';
import { onMounted, ref } from 'vue';
import { Button } from '@/components/ui/button';

defineProps<{
    title: string;
    subtitle?: string;
    effectiveDate: string;
    version: string;
    toc?: { id: string; label: string }[];
}>();

const activeSection = ref<string>('');

onMounted(() => {
    const observer = new IntersectionObserver(
        (entries) => {
            entries.forEach((entry) => {
                if (entry.isIntersecting) {
                    activeSection.value = entry.target.id;
                }
            });
        },
        { rootMargin: '-100px 0px -60% 0px' }
    );

    document.querySelectorAll('section[id]').forEach((el) => observer.observe(el));
});

function scrollTo(id: string) {
    const el = document.getElementById(id);

    if (el) {
        el.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }
}
</script>

<template>
    <Head :title="title">
        <link rel="preconnect" href="https://fonts.googleapis.com" />
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin="" />
        <link
            href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans+Thai:wght@300;400;500;600;700&family=Outfit:wght@300;400;500;600;700&display=swap"
            rel="stylesheet"
        />
    </Head>

    <div class="min-h-screen bg-slate-50 font-['IBM_Plex_Sans_Thai','Outfit',sans-serif] dark:bg-slate-950">
        <!-- Navigation (reuse Welcome nav style) -->
        <nav class="sticky top-0 z-50 border-b border-slate-200/50 bg-white/80 backdrop-blur-md dark:border-slate-800/50 dark:bg-slate-950/80">
            <div class="mx-auto flex h-16 max-w-7xl items-center justify-between px-4 sm:px-6 lg:px-8">
                <Link href="/" class="flex items-center gap-2">
                    <img src="/assets/logo.png" alt="AutoNAP" class="h-8 w-auto md:h-10" />
                    <span class="hidden text-xl font-bold tracking-tight text-slate-900 md:block dark:text-white">
                        AutoNAP
                    </span>
                </Link>

                <Button as-child variant="ghost" size="sm">
                    <Link href="/">
                        <ArrowLeft class="mr-1 h-4 w-4" />
                        กลับหน้าหลัก
                    </Link>
                </Button>
            </div>
        </nav>

        <!-- Hero -->
        <header class="relative overflow-hidden border-b border-slate-200 bg-white py-16 dark:border-slate-800 dark:bg-slate-900">
            <div class="absolute inset-0 opacity-10">
                <div class="absolute -top-1/2 left-1/4 h-full w-1/2 bg-linear-to-br from-teal-400 to-blue-500 blur-3xl"></div>
            </div>
            <div class="relative mx-auto max-w-4xl px-4 sm:px-6 lg:px-8">
                <h1 class="text-4xl font-bold tracking-tight text-balance text-slate-900 md:text-5xl dark:text-white">
                    {{ title }}
                </h1>
                <p v-if="subtitle" class="mt-4 text-lg text-pretty text-slate-600 dark:text-slate-400">
                    {{ subtitle }}
                </p>
                <div class="mt-6 flex flex-wrap gap-x-6 gap-y-2 text-sm text-slate-500 dark:text-slate-400">
                    <div><strong>วันที่มีผลบังคับใช้:</strong> {{ effectiveDate }}</div>
                    <div><strong>เวอร์ชัน:</strong> {{ version }}</div>
                </div>
            </div>
        </header>

        <!-- Content with sticky TOC -->
        <main class="mx-auto max-w-7xl px-4 py-12 sm:px-6 lg:px-8">
            <div class="grid gap-8 lg:grid-cols-[250px_1fr]">
                <!-- TOC Sidebar (desktop only) -->
                <aside v-if="toc && toc.length" class="hidden lg:block">
                    <div class="sticky top-24">
                        <h2 class="mb-4 text-xs font-semibold tracking-wider text-slate-500 uppercase">
                            สารบัญ
                        </h2>
                        <nav class="space-y-1">
                            <button
                                v-for="item in toc"
                                :key="item.id"
                                type="button"
                                :class="[
                                    'block w-full rounded-lg px-3 py-2 text-left text-sm transition-colors',
                                    activeSection === item.id
                                        ? 'bg-teal-50 font-medium text-teal-700 dark:bg-teal-950/30 dark:text-teal-400'
                                        : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900 dark:text-slate-400 dark:hover:bg-slate-800 dark:hover:text-white',
                                ]"
                                @click="scrollTo(item.id)"
                            >
                                {{ item.label }}
                            </button>
                        </nav>
                    </div>
                </aside>

                <!-- Main content -->
                <article class="legal-content mx-auto w-full max-w-3xl">
                    <slot />
                </article>
            </div>
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
    </div>
</template>

<style>
/* Typography for legal content */
.legal-content {
    color: rgb(51 65 85);
    font-size: 1rem;
    line-height: 1.75;
}

.dark .legal-content {
    color: rgb(203 213 225);
}

.legal-content h2 {
    font-size: 1.75rem;
    font-weight: 700;
    letter-spacing: -0.01em;
    color: rgb(15 23 42);
    margin-top: 3rem;
    margin-bottom: 1rem;
    padding-bottom: 0.5rem;
    border-bottom: 2px solid rgb(226 232 240);
}

.dark .legal-content h2 {
    color: rgb(248 250 252);
    border-bottom-color: rgb(30 41 59);
}

.legal-content h3 {
    font-size: 1.25rem;
    font-weight: 600;
    color: rgb(15 23 42);
    margin-top: 2rem;
    margin-bottom: 0.75rem;
}

.dark .legal-content h3 {
    color: rgb(248 250 252);
}

.legal-content h4 {
    font-size: 1.1rem;
    font-weight: 600;
    color: rgb(30 41 59);
    margin-top: 1.5rem;
    margin-bottom: 0.5rem;
}

.dark .legal-content h4 {
    color: rgb(226 232 240);
}

.legal-content p {
    margin-top: 1rem;
    margin-bottom: 1rem;
}

.legal-content ul,
.legal-content ol {
    margin-top: 1rem;
    margin-bottom: 1rem;
    padding-left: 1.5rem;
}

.legal-content ul {
    list-style-type: disc;
}

.legal-content ol {
    list-style-type: decimal;
}

.legal-content li {
    margin-top: 0.375rem;
    margin-bottom: 0.375rem;
}

.legal-content li::marker {
    color: rgb(20 184 166);
}

.legal-content strong {
    font-weight: 600;
    color: rgb(15 23 42);
}

.dark .legal-content strong {
    color: rgb(248 250 252);
}

.legal-content a {
    color: rgb(13 148 136);
    text-decoration: underline;
    text-underline-offset: 2px;
}

.legal-content a:hover {
    color: rgb(15 118 110);
}

.legal-content blockquote {
    margin-top: 1.5rem;
    margin-bottom: 1.5rem;
    padding: 1rem 1.25rem;
    border-left: 4px solid rgb(20 184 166);
    background: rgb(240 253 250);
    border-radius: 0 0.5rem 0.5rem 0;
}

.dark .legal-content blockquote {
    background: rgba(20, 184, 166, 0.08);
}

.legal-content blockquote p {
    margin: 0;
}

.legal-content table {
    width: 100%;
    margin-top: 1.5rem;
    margin-bottom: 1.5rem;
    border-collapse: collapse;
    font-size: 0.9rem;
}

.legal-content th,
.legal-content td {
    border: 1px solid rgb(226 232 240);
    padding: 0.625rem 0.75rem;
    text-align: left;
    vertical-align: top;
}

.dark .legal-content th,
.dark .legal-content td {
    border-color: rgb(30 41 59);
}

.legal-content th {
    background: rgb(248 250 252);
    font-weight: 600;
    color: rgb(15 23 42);
}

.dark .legal-content th {
    background: rgb(15 23 42);
    color: rgb(248 250 252);
}

.legal-content code {
    padding: 0.125rem 0.375rem;
    background: rgb(241 245 249);
    border-radius: 0.25rem;
    font-size: 0.875em;
    font-family: ui-monospace, SFMono-Regular, Menlo, monospace;
}

.dark .legal-content code {
    background: rgb(30 41 59);
}

.legal-content .callout {
    margin-top: 1.5rem;
    margin-bottom: 1.5rem;
    padding: 1.25rem 1.5rem;
    border-radius: 0.75rem;
    border: 1px solid;
}

.legal-content .callout-teal {
    background: rgb(240 253 250);
    border-color: rgb(153 246 228);
    color: rgb(19 78 74);
}

.dark .legal-content .callout-teal {
    background: rgba(20, 184, 166, 0.08);
    border-color: rgba(20, 184, 166, 0.3);
    color: rgb(153 246 228);
}

.legal-content .callout-amber {
    background: rgb(255 251 235);
    border-color: rgb(253 230 138);
    color: rgb(120 53 15);
}

.dark .legal-content .callout-amber {
    background: rgba(245, 158, 11, 0.08);
    border-color: rgba(245, 158, 11, 0.3);
    color: rgb(253 230 138);
}

.text-balance {
    text-wrap: balance;
}

.text-pretty {
    text-wrap: pretty;
}
</style>
