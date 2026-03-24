<script setup lang="ts">
import type { HTMLAttributes } from 'vue';

defineOptions({
    inheritAttrs: false,
});

type Props = {
    className?: HTMLAttributes['class'];
};

defineProps<Props>();
</script>

<template>
    <svg
        xmlns="http://www.w3.org/2000/svg"
        viewBox="0 0 40 42"
        :class="className"
        v-bind="$attrs"
    >
        <path
            fill="currentColor"
            d="M10 32V10h4.5l11 15V10h4.5v22h-4.5l-11-15v15H10z"
        />
        <path
            fill="currentColor"
            fill-opacity="0.3"
            d="M32 10l6 11-6 11V10z"
        />
    </svg>
</template>
