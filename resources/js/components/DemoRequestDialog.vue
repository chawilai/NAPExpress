<script setup lang="ts">
import { CheckCircle2, Loader2, Sparkles } from 'lucide-vue-next';
import { ref } from 'vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
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
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';

const open = defineModel<boolean>('open', { default: false });

const workTypeOptions = [
    { value: 'RR', label: '🔄 Reach / Outreach (RR)', desc: 'บันทึกการออกไปพบผู้รับบริการ' },
    { value: 'VCT', label: '💬 VCT Counseling', desc: 'ให้คำปรึกษาและตรวจ HIV' },
    { value: 'Lab', label: '🧪 Lab HIV Testing', desc: 'ตรวจ lab HIV / STI' },
    { value: 'Result', label: '📋 HIV Result Recording', desc: 'บันทึกผลตรวจ' },
    { value: 'PrEP', label: '💊 PrEP / PEP', desc: 'ยาป้องกันก่อน/หลังสัมผัส' },
    { value: 'HIVST', label: '📦 HIVST Kit Distribution', desc: 'แจกชุดตรวจด้วยตนเอง' },
    { value: 'Other', label: '✨ อื่นๆ', desc: 'งานอื่นที่เกี่ยวข้อง' },
];

const provinces = [
    'กรุงเทพมหานคร', 'เชียงใหม่', 'เชียงราย', 'ลำปาง', 'ลำพูน', 'พะเยา', 'แพร่', 'น่าน', 'แม่ฮ่องสอน',
    'นครราชสีมา', 'ขอนแก่น', 'อุดรธานี', 'อุบลราชธานี', 'ร้อยเอ็ด', 'มหาสารคาม', 'กาฬสินธุ์',
    'ชลบุรี', 'ระยอง', 'จันทบุรี', 'ตราด', 'ปราจีนบุรี', 'ฉะเชิงเทรา', 'นครปฐม', 'สมุทรปราการ',
    'สงขลา', 'ภูเก็ต', 'สุราษฎร์ธานี', 'นครศรีธรรมราช', 'ตรัง', 'พัทลุง', 'ปัตตานี', 'ยะลา', 'นราธิวาส',
    'อื่นๆ',
];

const form = ref({
    org_name: '',
    province: '',
    work_types: [] as string[],
    cases_per_month: null as number | null,
    contact_name: '',
    contact_phone: '',
    contact_email: '',
    notes: '',
});

const submitting = ref(false);
const submitted = ref(false);
const errors = ref<Record<string, string>>({});

function toggleWorkType(value: string) {
    const idx = form.value.work_types.indexOf(value);

    if (idx >= 0) {
        form.value.work_types.splice(idx, 1);
    } else {
        form.value.work_types.push(value);
    }
}

function isSelected(value: string): boolean {
    return form.value.work_types.includes(value);
}

function resetForm() {
    form.value = {
        org_name: '',
        province: '',
        work_types: [],
        cases_per_month: null,
        contact_name: '',
        contact_phone: '',
        contact_email: '',
        notes: '',
    };
    submitted.value = false;
    errors.value = {};
}

function closeDialog() {
    open.value = false;

    // Reset after close transition
    setTimeout(resetForm, 300);
}

async function submit() {
    submitting.value = true;
    errors.value = {};

    try {
        const res = await fetch('/api/demo-request', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify(form.value),
        });

        if (res.status === 422) {
            const data = await res.json();
            const flat: Record<string, string> = {};

            for (const [k, v] of Object.entries(data.errors ?? {})) {
                flat[k] = Array.isArray(v) ? (v[0] as string) : String(v);
            }

            errors.value = flat;
            return;
        }

        if (!res.ok) {
            errors.value.general = 'เกิดข้อผิดพลาด ลองใหม่อีกครั้ง';
            return;
        }

        submitted.value = true;
    } catch (e) {
        errors.value.general = 'เชื่อมต่อไม่ได้ กรุณาลองใหม่';
    } finally {
        submitting.value = false;
    }
}
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent class="max-h-[90vh] overflow-y-auto sm:max-w-2xl">
            <!-- Success state -->
            <div v-if="submitted" class="flex flex-col items-center py-6 text-center">
                <div class="mb-4 flex h-16 w-16 items-center justify-center rounded-full bg-teal-100 dark:bg-teal-950">
                    <CheckCircle2 class="h-10 w-10 text-teal-500" />
                </div>
                <DialogTitle class="text-2xl">ได้รับคำขอแล้ว ✅</DialogTitle>
                <DialogDescription class="mt-3 max-w-md">
                    ขอบคุณที่สนใจ AutoNAP ครับ ทีมงานจะติดต่อกลับภายใน <strong>24 ชั่วโมง</strong>
                    เพื่อนัดหมาย demo 15 นาที
                </DialogDescription>
                <Button class="mt-6" @click="closeDialog">ปิด</Button>
            </div>

            <!-- Form state -->
            <template v-else>
                <DialogHeader>
                    <DialogTitle class="flex items-center gap-2 text-2xl">
                        <Sparkles class="h-5 w-5 text-teal-500" />
                        นัดคุย Demo 15 นาที
                    </DialogTitle>
                    <DialogDescription>
                        กรอกข้อมูลเพื่อให้ทีม AutoNAP นัดหมายคุยกับคุณ — ไม่มีค่าใช้จ่าย
                        และไม่ผูกมัดครับ ทีมจะติดต่อกลับภายใน 24 ชั่วโมง
                    </DialogDescription>
                </DialogHeader>

                <form class="space-y-5" @submit.prevent="submit">
                    <!-- Organization -->
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <Label for="org_name" class="mb-1.5">
                                ชื่อองค์กร <span class="text-rose-500">*</span>
                            </Label>
                            <Input
                                id="org_name"
                                v-model="form.org_name"
                                placeholder="เช่น มูลนิธิ/คลินิก/ชมรม..."
                                required
                            />
                            <p v-if="errors.org_name" class="mt-1 text-xs text-rose-500">
                                {{ errors.org_name }}
                            </p>
                        </div>
                        <div>
                            <Label for="province" class="mb-1.5">จังหวัด</Label>
                            <Select v-model="form.province">
                                <SelectTrigger id="province">
                                    <SelectValue placeholder="เลือกจังหวัด" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem v-for="p in provinces" :key="p" :value="p">
                                        {{ p }}
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                            <p v-if="errors.province" class="mt-1 text-xs text-rose-500">
                                {{ errors.province }}
                            </p>
                        </div>
                    </div>

                    <!-- Work types (multi select) -->
                    <div>
                        <Label class="mb-2 block">
                            ทำงานรูปแบบไหน? (เลือกได้มากกว่า 1) <span class="text-rose-500">*</span>
                        </Label>
                        <div class="grid gap-2 sm:grid-cols-2">
                            <button
                                v-for="wt in workTypeOptions"
                                :key="wt.value"
                                type="button"
                                :class="[
                                    'flex items-start gap-2 rounded-lg border p-3 text-left text-sm transition-colors',
                                    isSelected(wt.value)
                                        ? 'border-teal-500 bg-teal-50 dark:border-teal-400 dark:bg-teal-950/30'
                                        : 'border-slate-200 bg-white hover:border-teal-300 dark:border-slate-800 dark:bg-slate-950',
                                ]"
                                @click="toggleWorkType(wt.value)"
                            >
                                <Checkbox
                                    :model-value="isSelected(wt.value)"
                                    class="pointer-events-none mt-0.5"
                                    @update:model-value="toggleWorkType(wt.value)"
                                />
                                <div class="flex-1">
                                    <div class="font-medium">{{ wt.label }}</div>
                                    <div class="mt-0.5 text-xs text-slate-500">{{ wt.desc }}</div>
                                </div>
                            </button>
                        </div>
                        <p v-if="errors.work_types" class="mt-1 text-xs text-rose-500">
                            {{ errors.work_types }}
                        </p>
                    </div>

                    <!-- Cases per month -->
                    <div>
                        <Label for="cases" class="mb-1.5">จำนวนเคสโดยประมาณต่อเดือน</Label>
                        <Input
                            id="cases"
                            v-model.number="form.cases_per_month"
                            type="number"
                            min="0"
                            placeholder="เช่น 500, 1000, 3000"
                        />
                        <p class="mt-1 text-xs text-slate-500">
                            ใช้คำนวณ package ที่เหมาะสมกับองค์กรของคุณ (optional)
                        </p>
                    </div>

                    <!-- Contact info -->
                    <div class="rounded-lg border border-slate-200 bg-slate-50 p-4 dark:border-slate-800 dark:bg-slate-900/50">
                        <div class="mb-3 text-sm font-semibold text-slate-700 dark:text-slate-300">
                            ข้อมูลติดต่อกลับ
                        </div>
                        <div class="grid gap-4 sm:grid-cols-2">
                            <div>
                                <Label for="contact_name" class="mb-1.5">
                                    ชื่อผู้ติดต่อ <span class="text-rose-500">*</span>
                                </Label>
                                <Input
                                    id="contact_name"
                                    v-model="form.contact_name"
                                    placeholder="คุณ..."
                                    required
                                />
                                <p v-if="errors.contact_name" class="mt-1 text-xs text-rose-500">
                                    {{ errors.contact_name }}
                                </p>
                            </div>
                            <div>
                                <Label for="contact_phone" class="mb-1.5">
                                    เบอร์โทร <span class="text-rose-500">*</span>
                                </Label>
                                <Input
                                    id="contact_phone"
                                    v-model="form.contact_phone"
                                    type="tel"
                                    placeholder="08X-XXX-XXXX"
                                    required
                                />
                                <p v-if="errors.contact_phone" class="mt-1 text-xs text-rose-500">
                                    {{ errors.contact_phone }}
                                </p>
                            </div>
                        </div>
                        <div class="mt-3">
                            <Label for="contact_email" class="mb-1.5">
                                อีเมล <span class="text-xs font-normal text-slate-500">(ไม่บังคับ)</span>
                            </Label>
                            <Input
                                id="contact_email"
                                v-model="form.contact_email"
                                type="email"
                                placeholder="you@example.com"
                            />
                            <p v-if="errors.contact_email" class="mt-1 text-xs text-rose-500">
                                {{ errors.contact_email }}
                            </p>
                        </div>
                    </div>

                    <!-- Notes -->
                    <div>
                        <Label for="notes" class="mb-1.5">
                            หมายเหตุเพิ่มเติม <span class="text-xs font-normal text-slate-500">(ไม่บังคับ)</span>
                        </Label>
                        <textarea
                            id="notes"
                            v-model="form.notes"
                            rows="3"
                            placeholder="เช่น ใช้ระบบอะไรอยู่, ปัญหาที่เจอ, ช่วงเวลาที่สะดวกคุย..."
                            class="flex w-full rounded-md border border-slate-200 bg-white px-3 py-2 text-sm placeholder:text-slate-400 focus-visible:ring-2 focus-visible:ring-teal-500 focus-visible:outline-none dark:border-slate-800 dark:bg-slate-950"
                        ></textarea>
                    </div>

                    <!-- General error -->
                    <div v-if="errors.general" class="rounded-lg bg-rose-50 p-3 text-sm text-rose-700 dark:bg-rose-950/20 dark:text-rose-300">
                        ⚠️ {{ errors.general }}
                    </div>

                    <DialogFooter class="gap-2 sm:gap-0">
                        <Button type="button" variant="outline" @click="closeDialog">ยกเลิก</Button>
                        <Button type="submit" :disabled="submitting">
                            <Loader2 v-if="submitting" class="mr-2 h-4 w-4 animate-spin" />
                            {{ submitting ? 'กำลังส่ง...' : 'ส่งคำขอ Demo' }}
                        </Button>
                    </DialogFooter>

                    <p class="text-xs text-slate-500">
                        🔒 ข้อมูลของคุณอยู่ภายใต้
                        <a href="/privacy" class="text-teal-600 hover:underline" target="_blank">
                            นโยบายความเป็นส่วนตัว
                        </a>
                        ใช้เฉพาะเพื่อติดต่อกลับเท่านั้น
                    </p>
                </form>
            </template>
        </DialogContent>
    </Dialog>
</template>
