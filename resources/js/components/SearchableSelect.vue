<script setup lang="ts">
import { Check, ChevronDown, Search, X } from 'lucide-vue-next';
import { computed, nextTick, onMounted, onUnmounted, ref, watch } from 'vue';

interface Option {
    value: string;
    label: string;
    sublabel?: string;
}

const props = withDefaults(
    defineProps<{
        options: Option[];
        placeholder?: string;
        searchPlaceholder?: string;
        emptyMessage?: string;
        clearable?: boolean;
    }>(),
    {
        placeholder: 'เลือก...',
        searchPlaceholder: 'ค้นหา...',
        emptyMessage: 'ไม่พบข้อมูล',
        clearable: true,
    }
);

const model = defineModel<string>({ default: '' });

const open = ref(false);
const query = ref('');
const wrapper = ref<HTMLElement | null>(null);
const searchInput = ref<HTMLInputElement | null>(null);

const normalizedQuery = computed(() => query.value.trim().toLowerCase());

const filtered = computed(() => {
    if (!normalizedQuery.value) {
        return props.options;
    }

    const q = normalizedQuery.value;

    return props.options.filter(
        (o) =>
            o.label.toLowerCase().includes(q) ||
            o.value.toLowerCase().includes(q) ||
            (o.sublabel && o.sublabel.toLowerCase().includes(q))
    );
});

const selectedLabel = computed(() => {
    const found = props.options.find((o) => o.value === model.value);
    return found?.label ?? '';
});

function toggle() {
    open.value = !open.value;

    if (open.value) {
        nextTick(() => {
            searchInput.value?.focus();
        });
    }
}

function pick(value: string) {
    model.value = value;
    open.value = false;
    query.value = '';
}

function clear(e: Event) {
    e.stopPropagation();
    model.value = '';
    query.value = '';
}

function handleClickOutside(e: MouseEvent) {
    if (wrapper.value && !wrapper.value.contains(e.target as Node)) {
        open.value = false;
    }
}

function handleKeydown(e: KeyboardEvent) {
    if (e.key === 'Escape') {
        open.value = false;
        query.value = '';
    }
}

watch(open, (val) => {
    if (!val) {
        query.value = '';
    }
});

onMounted(() => {
    document.addEventListener('click', handleClickOutside);
    document.addEventListener('keydown', handleKeydown);
});

onUnmounted(() => {
    document.removeEventListener('click', handleClickOutside);
    document.removeEventListener('keydown', handleKeydown);
});
</script>

<template>
    <div ref="wrapper" class="relative">
        <button
            type="button"
            class="flex h-10 w-full items-center justify-between rounded-md border border-slate-200 bg-white px-3 py-2 text-sm ring-offset-white transition-colors hover:border-slate-300 focus:outline-none focus:ring-2 focus:ring-teal-500 focus:ring-offset-2 dark:border-slate-800 dark:bg-slate-950 dark:hover:border-slate-700"
            @click="toggle"
        >
            <span
                :class="[
                    'truncate text-left',
                    selectedLabel ? 'text-slate-900 dark:text-white' : 'text-slate-400',
                ]"
            >
                {{ selectedLabel || placeholder }}
            </span>
            <div class="ml-2 flex items-center gap-1">
                <button
                    v-if="clearable && model"
                    type="button"
                    class="rounded p-0.5 text-slate-400 hover:bg-slate-100 hover:text-slate-700 dark:hover:bg-slate-800"
                    @click="clear"
                >
                    <X class="h-3.5 w-3.5" />
                </button>
                <ChevronDown
                    :class="[
                        'h-4 w-4 text-slate-400 transition-transform',
                        open && 'rotate-180',
                    ]"
                />
            </div>
        </button>

        <Transition
            enter-active-class="transition duration-150 ease-out"
            enter-from-class="opacity-0 translate-y-1"
            enter-to-class="opacity-100 translate-y-0"
            leave-active-class="transition duration-100 ease-in"
            leave-from-class="opacity-100 translate-y-0"
            leave-to-class="opacity-0 translate-y-1"
        >
            <div
                v-if="open"
                class="absolute left-0 right-0 z-50 mt-1 overflow-hidden rounded-md border border-slate-200 bg-white shadow-lg dark:border-slate-800 dark:bg-slate-950"
            >
                <!-- Search input -->
                <div class="relative border-b border-slate-100 dark:border-slate-800">
                    <Search class="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
                    <input
                        ref="searchInput"
                        v-model="query"
                        type="text"
                        :placeholder="searchPlaceholder"
                        class="w-full bg-transparent py-2.5 pl-9 pr-3 text-sm placeholder:text-slate-400 focus:outline-none"
                    />
                </div>

                <!-- Options list -->
                <div class="max-h-72 overflow-y-auto py-1">
                    <div
                        v-if="filtered.length === 0"
                        class="px-3 py-6 text-center text-sm text-slate-500"
                    >
                        {{ emptyMessage }}
                    </div>

                    <button
                        v-for="opt in filtered"
                        :key="opt.value"
                        type="button"
                        :class="[
                            'flex w-full items-start gap-2 px-3 py-2 text-left text-sm transition-colors',
                            model === opt.value
                                ? 'bg-teal-50 text-teal-700 dark:bg-teal-950/30 dark:text-teal-400'
                                : 'text-slate-700 hover:bg-slate-50 dark:text-slate-300 dark:hover:bg-slate-800',
                        ]"
                        @click="pick(opt.value)"
                    >
                        <Check
                            :class="[
                                'mt-0.5 h-4 w-4 flex-shrink-0',
                                model === opt.value ? 'opacity-100' : 'opacity-0',
                            ]"
                        />
                        <div class="min-w-0 flex-1">
                            <div class="truncate">{{ opt.label }}</div>
                            <div v-if="opt.sublabel" class="truncate text-xs text-slate-500">
                                {{ opt.sublabel }}
                            </div>
                        </div>
                    </button>
                </div>

                <div
                    v-if="filtered.length > 0"
                    class="border-t border-slate-100 px-3 py-1.5 text-right text-xs text-slate-400 dark:border-slate-800"
                >
                    {{ filtered.length }} {{ filtered.length === options.length ? 'รายการ' : `จาก ${options.length}` }}
                </div>
            </div>
        </Transition>
    </div>
</template>
