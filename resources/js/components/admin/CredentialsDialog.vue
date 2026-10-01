<script setup lang="ts">
import { Check, Copy } from '@lucide/vue';
import { useClipboard } from '@vueuse/core';
import { computed, ref } from 'vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';

export type CompanyCredentials = {
    company: string;
    name: string;
    login_url: string;
    email: string;
    password: string;
};

const props = defineProps<{
    credentials: CompanyCredentials;
}>();

const open = defineModel<boolean>('open', { default: false });

const { copy, isSupported } = useClipboard({ legacy: true });

/** Which value was copied last, so only its button shows the tick. */
const copiedKey = ref<string | null>(null);
let resetTimer: ReturnType<typeof setTimeout> | undefined;

async function copyValue(key: string, value: string): Promise<void> {
    await copy(value);
    copiedKey.value = key;
    clearTimeout(resetTimer);
    resetTimer = setTimeout(() => (copiedKey.value = null), 2000);
}

const fields = computed(() => [
    {
        key: 'login_url',
        label: 'Login URL',
        value: props.credentials.login_url,
    },
    { key: 'email', label: 'Email', value: props.credentials.email },
    { key: 'password', label: 'Password', value: props.credentials.password },
]);

/** Ready to paste into WhatsApp or an email. */
const message = computed(() =>
    [
        `Hello ${props.credentials.name},`,
        '',
        `Your HRMS account for ${props.credentials.company} is ready.`,
        '',
        `Login URL: ${props.credentials.login_url}`,
        `Email: ${props.credentials.email}`,
        `Password: ${props.credentials.password}`,
        '',
        'Please change your password after you sign in.',
    ].join('\n'),
);
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent class="sm:max-w-lg">
            <DialogHeader>
                <DialogTitle>Share login details</DialogTitle>
                <DialogDescription>
                    Send these to {{ credentials.name }}. The password is shown
                    only now; it cannot be seen again after you close this.
                </DialogDescription>
            </DialogHeader>

            <dl class="flex flex-col gap-3">
                <div
                    v-for="field in fields"
                    :key="field.key"
                    class="flex flex-col gap-1"
                >
                    <dt class="text-xs font-medium text-muted-foreground">
                        {{ field.label }}
                    </dt>
                    <dd
                        class="flex items-center gap-2 rounded-md border bg-muted/40 py-1 pr-1 pl-3"
                    >
                        <span
                            class="min-w-0 flex-1 truncate font-mono text-sm select-all"
                        >
                            {{ field.value }}
                        </span>
                        <Button
                            v-if="isSupported"
                            type="button"
                            variant="ghost"
                            size="icon"
                            class="size-8 shrink-0"
                            :aria-label="`Copy ${field.label.toLowerCase()}`"
                            @click="copyValue(field.key, field.value)"
                        >
                            <Check
                                v-if="copiedKey === field.key"
                                class="text-positive"
                            />
                            <Copy v-else />
                        </Button>
                    </dd>
                </div>
            </dl>

            <DialogFooter class="gap-2">
                <Button type="button" variant="outline" @click="open = false">
                    Done
                </Button>
                <Button
                    v-if="isSupported"
                    type="button"
                    @click="copyValue('all', message)"
                >
                    <Check v-if="copiedKey === 'all'" />
                    <Copy v-else />
                    {{ copiedKey === 'all' ? 'Copied' : 'Copy all details' }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
