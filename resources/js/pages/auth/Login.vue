<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { Loader2, Mail, Lock } from 'lucide-vue-next';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardFooter,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { login, register } from '@/routes';

defineProps<{
    status?: string;
}>();

const form = useForm({
    email: '',
    password: '',
    remember: false,
});

const submit = () => {
    form.post(login(), {
        onFinish: () => {
            form.reset('password');
        },
    });
};
</script>

<template>
    <Head title="Login to AutoNAP" />

    <div
        class="flex min-h-screen items-center justify-center bg-slate-50 px-4 py-12 font-['Outfit'] sm:px-6 lg:px-8 dark:bg-slate-950"
    >
        <div class="w-full max-w-md space-y-10">
            <div class="flex flex-col items-center">
                <Link :href="'/'" class="flex items-center gap-2.5">
                    <img
                        src="/assets/logo.png"
                        alt="NAPExpress"
                        class="h-12 w-auto"
                    />
                    <span
                        class="text-2xl font-bold tracking-tight text-slate-900 dark:text-white"
                        >NAPExpress</span
                    >
                </Link>
                <h2
                    class="mt-8 text-center text-3xl font-extrabold tracking-tight text-slate-900 dark:text-white"
                >
                    Welcome back
                </h2>
                <p
                    class="mt-3 text-center text-sm text-slate-600 dark:text-slate-400"
                >
                    Sign in to your account to manage your NAP reports
                </p>
            </div>

            <Card
                class="border-slate-200 shadow-xl dark:border-slate-800 dark:bg-slate-900"
            >
                <form @submit.prevent="submit">
                    <CardHeader class="space-y-2 pt-8 pb-4">
                        <CardTitle class="text-2xl">Login</CardTitle>
                        <CardDescription>
                            Enter your email and password below to access your
                            account.
                        </CardDescription>
                    </CardHeader>
                    <CardContent class="grid gap-6 pt-2">
                        <div
                            v-if="status"
                            class="rounded-md bg-green-50 px-3 py-2 text-sm font-medium text-green-700 dark:bg-green-950 dark:text-green-400"
                        >
                            {{ status }}
                        </div>

                        <div class="grid gap-2.5">
                            <Label for="email">Email</Label>
                            <div class="relative">
                                <Mail
                                    class="absolute top-3 left-3 h-4 w-4 text-slate-400"
                                />
                                <Input
                                    id="email"
                                    type="email"
                                    placeholder="name@organization.com"
                                    class="h-11 pl-10"
                                    v-model="form.email"
                                    required
                                    autofocus
                                    autocomplete="username"
                                />
                            </div>
                            <p
                                v-if="form.errors.email"
                                class="text-xs text-red-500"
                            >
                                {{ form.errors.email }}
                            </p>
                        </div>

                        <div class="grid gap-2.5">
                            <div class="flex items-center justify-between">
                                <Label for="password">Password</Label>
                                <a
                                    href="#"
                                    class="text-xs text-teal-600 hover:underline dark:text-teal-400"
                                >
                                    Forgot password?
                                </a>
                            </div>
                            <div class="relative">
                                <Lock
                                    class="absolute top-3 left-3 h-4 w-4 text-slate-400"
                                />
                                <Input
                                    id="password"
                                    type="password"
                                    placeholder="••••••••"
                                    class="h-11 pl-10"
                                    v-model="form.password"
                                    required
                                    autocomplete="current-password"
                                />
                            </div>
                            <p
                                v-if="form.errors.password"
                                class="text-xs text-red-500"
                            >
                                {{ form.errors.password }}
                            </p>
                        </div>

                        <div class="flex items-center space-x-2 pt-1">
                            <Checkbox
                                id="remember"
                                v-model:checked="form.remember"
                            />
                            <Label
                                for="remember"
                                class="cursor-pointer text-sm leading-none font-medium peer-disabled:cursor-not-allowed peer-disabled:opacity-70"
                            >
                                Remember me
                            </Label>
                        </div>
                    </CardContent>
                    <CardFooter class="flex flex-col gap-5 pt-6 pb-8">
                        <Button
                            class="h-11 w-full bg-teal-600 text-white hover:bg-teal-700"
                            :disabled="form.processing"
                        >
                            <Loader2
                                v-if="form.processing"
                                class="mr-2 h-4 w-4 animate-spin"
                            />
                            Sign In
                        </Button>
                        <div
                            class="text-center text-sm text-slate-600 dark:text-slate-400"
                        >
                            Don't have an account?
                            <Link
                                :href="register()"
                                class="ml-1 font-semibold text-teal-600 hover:underline dark:text-teal-400"
                            >
                                Register now
                            </Link>
                        </div>
                    </CardFooter>
                </form>
            </Card>

            <div
                class="flex justify-center text-xs text-slate-500 dark:text-slate-400"
            >
                &copy; 2026 NAPExpress. Fast. Accurate. Automated.
            </div>
        </div>
    </div>
</template>
